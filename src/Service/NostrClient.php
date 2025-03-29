<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\KindsEnum;
use App\Factory\ArticleFactory;
use App\Repository\UserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\InvalidArgumentException;
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
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

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
        $this->defaultRelaySet->addRelay(new Relay('wss://relay.damus.io')); // public relay
        $this->defaultRelaySet->addRelay(new Relay('wss://nos.lol')); // public relay
    }

    public function getLoginData($npub)
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::METADATA, KindsEnum::RELAY_LIST]);
        $filter->setAuthors([$npub]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        // use default aggregator relay
        $relay = new Relay('wss://purplepag.es');

        $request = new Request($relay, $requestMessage);

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
     * @throws InvalidArgumentException
     */
    public function getNpubMetadata($npub)
    {
        // cache metadata, only fetch new, if no cache hit
        return $this->cacheApp->get($npub.'-0', function (ItemInterface $item) use ($npub) {
            $item->expiresAfter(7000);

            $subscription = new Subscription();
            $subscriptionId = $subscription->setId();
            $filter = new Filter();
            $filter->setKinds([KindsEnum::METADATA]);
            $filter->setAuthors([$npub]);
            $requestMessage = new RequestMessage($subscriptionId, [$filter]);
            $relays = new RelaySet();
            $relays->addRelay(new Relay('wss://purplepag.es')); // default metadata aggregator
            $relays->addRelay(new Relay('wss://relay.damus.io')); // major public
            $relays->addRelay(new Relay('wss://relay.snort.social')); // major public

            $request = new Request($relays, $requestMessage);

            $response = $request->send();
            // response is an array of arrays
            foreach ($response as $value) {
                foreach ($value as $item) {
                    switch ($item->type) {
                        case 'EVENT':
                            return $item->event;
                        case 'AUTH':
                            throw new UnauthorizedHttpException('', 'Relay requires authentication');
                        case 'ERROR':
                        case 'NOTICE':
                            throw new \Exception('An error occurred');
                        default:
                            return null;
                    }
                }
            }
            return null;
        });
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
                $relays->addRelay(new Relay($relayArr[1]));
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
            $relays = new RelaySet();

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

        if (empty($relayList)) {
            $relays = $this->defaultRelaySet;
        } else {
            $relays = new RelaySet();
            $relays->addRelay(new Relay($relayList[0]));
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

    public function getProfileEvents($npub): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::METADATA, KindsEnum::FOLLOWS, KindsEnum::RELAY_LIST]);
        $filter->setAuthors([$npub]);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        // TODO make relays configurable
        $relays = new RelaySet();
        $relays->addRelay(new Relay('wss://purplepag.es')); // default metadata aggregator
        $relays->addRelay(new Relay('wss://nos.lol')); // default public

        $request = new Request($relays, $requestMessage);

        $response = $request->send();
        // response is an array of arrays
        foreach ($response as $value) {
            foreach ($value as $item) {
                switch ($item->type) {
                    case 'EVENT':
                        dump($item);
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
}
