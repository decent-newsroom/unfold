<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\KindsEnum;
use App\Factory\ArticleFactory;
use App\Repository\UserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use swentel\nostr\Event\Event;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class NostrClient
{
    private $defaultRelaySet;
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ManagerRegistry $managerRegistry,
                                private readonly UserEntityRepository $userEntityRepository,
                                private readonly ArticleFactory $articleFactory,
                                private readonly SerializerInterface    $serializer,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly CacheInterface $cacheApp,
                                private readonly LoggerInterface        $logger)
    {
        // TODO configure read and write relays for logged in users from their 10002 events
        $this->defaultRelaySet = new RelaySet();
        // $this->defaultRelaySet->addRelay(new Relay('wss://relay.damus.io')); // public relay
        // $this->defaultRelaySet->addRelay(new Relay('wss://relay.primal.net')); // public relay
        $this->defaultRelaySet->addRelay(new Relay('wss://nos.lol')); // public relay
        // $this->defaultRelaySet->addRelay(new Relay('wss://relay.snort.social')); // public relay
        $this->defaultRelaySet->addRelay(new Relay('wss://theforest.nostr1.com')); // public relay
        // $this->defaultRelaySet->addRelay(new Relay('wss://purplepag.es')); // public relay
    }

    public function getLoginData($npub)
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::METADATA, KindsEnum::RELAY_LIST]);
        $filter->setAuthors([$npub]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        $request = new Request($this->defaultRelaySet, $requestMessage);

        $response = $request->send();
        // response is an n-dimensional array, where n is the number of relays in the set
        // check that response has events in the results
        foreach ($response as $relayRes) {
            $filtered = array_filter($relayRes, function ($item) {
                return $item->type === 'EVENT';
            });
            if (count($filtered) > 0) {
                return $filtered;
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function getNpubMetadata($npub)
    {
        $filter = new Filter();
        $filter->setKinds([KindsEnum::METADATA]);
        $filter->setAuthors([$npub]);
        $filters = [$filter];
        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getId(), $filters);
        $relays = [
            new Relay('wss://purplepag.es'),
            new Relay('wss://theforest.nostr1.com'),
        ];
        $relaySet = new RelaySet();
        $relaySet->setRelays($relays);

        $request = new Request($relaySet, $requestMessage);
        $response = $request->send();

        $meta = [];
        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                switch ($item->type) {
                    case 'EVENT':
                        $meta[] = $item->event;
                        break;
                    case 'AUTH':
                        throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        throw new \Exception('An error occurred');
                    default:
                        // skip
                }
            }
        }

        if (count($meta) > 0) {
            if (count($meta) > 1) {
                // sort by date and pick newest
                usort($meta, function($a, $b) {
                    return $b->created_at <=> $a->created_at;
                });
            }
            return $meta[0];
        }
        return [];
    }

    public function getNpubLongForm($npub): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::LONGFORM]);
        $filter->setAuthors([$npub]);
        $filter->setSince(strtotime('-6 months')); // too much?
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);

        // if user is logged in, use their settings
        /* @var  $user */
        $user = $this->tokenStorage->getToken()?->getUser();
        $relays = $this->defaultRelaySet;
        if ($user && $user->getRelays()) {
            $relays = new RelaySet();
            foreach ($user->getRelays() as $relayArr) {
                if ($relayArr[2] == 'write') {
                    $relays->addRelay(new Relay($relayArr[1]));
                }
            }
        }

        $request = new Request($relays, $requestMessage);

        $response = $request->send();
        // response is an n-dimensional array, where n is the number of relays in the set
        // check that response has events in the results
        foreach ($response as $relayRes) {
            $filtered = array_filter($relayRes, function ($item) {
                return $item->type === 'EVENT';
            });
            if (count($filtered) > 0) {
                $this->saveLongFormContent($filtered);
            }
        }
        // TODO handle relays that require auth
    }


    public function publishEvent(Event $event, array $relays): array
    {
        $eventMessage = new EventMessage($event);
        $relaySet = new RelaySet();
        foreach ($relays as $relayWss) {
            $relay = new Relay($relayWss);
            $relaySet->addRelay($relay);
        }
        $relaySet->setMessage($eventMessage);
        // TODO handle responses appropriately
        return $relaySet->send();
    }

    /**
     * Long-form Content
     * NIP-23
     */
    public function getLongFormContent(): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::LONGFORM]);
        // TODO make filters configurable
        $filter->setSince(strtotime('-1 week')); //
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);

        // if user is logged in, use their settings
        $user = $this->tokenStorage->getToken()?->getUser();
        $relays = $this->defaultRelaySet;
        if ($user) {
            //$relays = new RelaySet();
        }

        $request = new Request($relays, $requestMessage);

        $response = $request->send();
        // response is an n-dimensional array, where n is the number of relays in the set
        // check that response has events in the results
        foreach ($response as $relayRes) {
            $filtered = array_filter($relayRes, function ($item) {
                return $item->type === 'EVENT';
            });
            if (count($filtered) > 0) {
                $this->saveLongFormContent($filtered);
            }
        }
        // TODO handle relays that require auth
    }



    public function getLongFormFromNaddr($slug, $relayList, $author, $kind): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([$kind]);
        $filter->setAuthors([$author]);
        $filter->setTag('#d', [$slug]);

        $requestMessage = new RequestMessage($subscriptionId, [$filter]);

        $relays = $this->defaultRelaySet;
        if (!empty($relayList)) {
           // $relays->addRelay(new Relay($relayList[0]));
        }


        try {
            $request = new Request($this->defaultRelaySet, $requestMessage);
            $response = $request->send();
        } catch (\Exception $e) {
            // likely a problem with user's relays, go to defaults only
            $request = new Request($this->defaultRelaySet, $requestMessage);
            $response = $request->send();
        }

        // response is an n-dimensional array, where n is the number of relays in the set
        // check that response has events in the results
        foreach ($response as $relayRes) {
            $filtered = array_filter($relayRes, function ($item) {
                return $item->type === 'EVENT';
            });
            if (count($filtered) > 0) {
                $this->saveLongFormContent($filtered);
            }
        }
        // TODO handle relays that require auth
    }


    /**
     * User metadata
     * NIP-01
     * @throws \Exception
     */
    public function getMetadata(array $npubs): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::METADATA]);
        $filter->setAuthors($npubs);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        // TODO make relays configurable
        $relays = new RelaySet();
        $relays->addRelay(new Relay('wss://purplepag.es')); // default metadata aggregator
        // $relays->addRelay(new Relay('wss://nos.lol')); // default metadata aggregator

        $request = new Request($relays, $requestMessage);

        $response = $request->send();
        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                switch ($item->type) {
                    case 'EVENT':
                        $this->saveMetadata($item->event);
                        break;
                    case 'AUTH':
                        throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        throw new \Exception('An error occurred');
                    default:
                        // nothing to do here
                }
            }
        }

    }

    /**
     * Save user metadata
     */
    private function saveMetadata($metadata): void
    {
        try {
            $user = $this->serializer->deserialize($metadata->content, User::class, 'json');
            $user->setNpub($metadata->pubkey);
        } catch (\Exception $e) {
            $this->logger->error('Deserialization of user data failed.', ['exception' => $e]);
            return;
        }

        try {
            $this->logger->info('Saving user', ['user' => $user]);
            $this->userEntityRepository->findOrCreateByUniqueField($user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->managerRegistry->resetManager();
        }

    }

    private function saveLongFormContent(mixed $filtered): void
    {
        foreach ($filtered as $wrapper) {
            $article = $this->articleFactory->createFromLongFormContentEvent($wrapper->event);
            // check if event with same eventId already in DB
            $saved = $this->entityManager->getRepository(Article::class)->findOneBy(['eventId' => $article->getEventId()]);
            if (!$saved) {
                try {
                    $this->logger->info('Saving article', ['article' => $article]);
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                    $this->managerRegistry->resetManager();
                }
            }
        }
    }

    /**
     *
     * @return array
     */
    public function getNpubRelays($pubkey)
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::RELAY_LIST]);
        $filter->setAuthors([$pubkey]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        // TODO make relays configurable
        $relays = new RelaySet();
        $relays->addRelay(new Relay('wss://purplepag.es')); // default metadata aggregator
        $relays->addRelay(new Relay('wss://nos.lol')); // default public
        $relays->addRelay(new Relay('wss://theforest.nostr1.com')); // default public

        $request = new Request($relays, $requestMessage);

        $response = $request->send();

        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                switch ($item->type) {
                    case 'EVENT':
                        $event = $item->event;
                        $relays = [];
                        foreach ($event->tags as $tag) {
                            if ($tag[0] === 'r') {
                                // if not already listed
                                if (!in_array($tag[1], $relays)) {
                                    $relays[] = $tag[1];
                                }
                            }
                        }
                        if (!empty($relays)) {
                            return $relays;
                        }
                        break;
                    case 'AUTH':
                        throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        throw new \Exception('An error occurred');
                    default:
                        // nothing to do here
                }
            }
        }
        return [];
    }

    /**
     * @throws \Exception
     */
    public function getComments($coordinate): array
    {
        $list = [];
        $parts = explode(':', $coordinate);

        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::COMMENTS, KindsEnum::TEXT_NOTE]);
        $filter->setTag('#a', [$coordinate]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);

        $request = new Request($this->defaultRelaySet, $requestMessage);

        $response = $request->send();
        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                switch ($item->type) {
                    case 'EVENT':
                        dump($item);
                        $list[] = $item;
                        break;
                    case 'AUTH':
                        throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        throw new \Exception('An error occurred');
                    default:
                        // nothing to do here
                }
            }
        }
        return $list;
    }

    /**
     * @throws \Exception
     */
    public function getLongFormContentForPubkey(string $pubkey)
    {
        $articles = [];
        // get npub relays, then look for articles
        $relayList = $this->getNpubRelays($pubkey);
        $relaySet = $this->defaultRelaySet;
        foreach ($relayList as $r) {
            // if (str_starts_with($r, 'wss:')) $relaySet->addRelay(new Relay($r));
        }
        // look for last months long-form notes
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::LONGFORM]);
        $filter->setLimit(10);
        $filter->setAuthors([$pubkey]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        $request = new Request($relaySet, $requestMessage);

        $response = $request->send();

        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                if (is_array($item)) continue;
                switch ($item->type) {
                    case 'EVENT':
                        $eventStr = json_encode($item->event);
                        // remap to the Event class
                        $encoders = [new JsonEncoder()];
                        $normalizers = [new ObjectNormalizer()];
                        $serializer = new Serializer($normalizers, $encoders);
                        /** @var \App\Entity\Event $event */
                        $event = $serializer->deserialize($eventStr, \App\Entity\Event::class, 'json');
                        $articles[] = $event;
                        break;
                    case 'AUTH':
                        // throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        // throw new \Exception('An error occurred');
                    default:
                        // nothing to do here
                }
            }
        }
        return $articles;
    }
}
