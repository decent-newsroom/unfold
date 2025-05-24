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
use swentel\nostr\Key\Key;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NostrClient
{
    private RelaySet $defaultRelaySet;

    /**
     * List of reputable relays in descending order of reputation
     */
    private const array REPUTABLE_RELAYS = [
        'wss://theforest.nostr1.com',
        'wss://relay.damus.io',
        'wss://relay.primal.net',
        'wss://nos.lol',
        'wss://relay.snort.social',
        'wss://nostr.land',
        'wss://purplepag.es',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ManagerRegistry $managerRegistry,
                                private readonly ArticleFactory $articleFactory,
                                private readonly TokenStorageInterface $tokenStorage,
                                private readonly LoggerInterface $logger)
    {
        $this->defaultRelaySet = new RelaySet();
        $this->defaultRelaySet->addRelay(new Relay('wss://theforest.nostr1.com')); // public aggregator relay
    }

    /**
     * Creates a RelaySet from a list of relay URLs
     */
    private function createRelaySet(array $relayUrls): RelaySet
    {
        $relaySet = new RelaySet();
        foreach ($relayUrls as $relayUrl) {
            $relaySet->addRelay(new Relay($relayUrl));
        }
        return $relaySet;
    }

    /**
     * Get top 3 reputable relays from an author's relay list
     */
    private function getTopReputableRelaysForAuthor(string $pubkey, int $limit = 3): array
    {
        try {
            $authorRelays = $this->getNpubRelays($pubkey);
        } catch (\Exception $e) {
            $this->logger->error('Error getting author relays', [
                'pubkey' => $pubkey,
                'error' => $e->getMessage()
            ]);
            // fall through
            $authorRelays = [];
        }
        if (empty($authorRelays)) {
            return [self::REPUTABLE_RELAYS[0]]; // Default to theforest if no author relays
        }

        $reputableAuthorRelays = [];
        foreach (self::REPUTABLE_RELAYS as $relay) {
            if (in_array($relay, $authorRelays) && count($reputableAuthorRelays) < $limit) {
                $reputableAuthorRelays[] = $relay;
            }
        }

        // If no reputable relays found in author's list, take the top 3 from author's list
        // But make sure they start with wss: and are not localhost
        if (empty($reputableAuthorRelays)) {
            $authorRelays = array_filter($authorRelays, function ($relay) {
                return str_starts_with($relay, 'wss:') && !str_contains($relay, 'localhost');
            });
            return array_slice($authorRelays, 0, $limit);
        }

        return $reputableAuthorRelays;
    }

    public function getNpubMetadata($npub): \stdClass
    {
        $this->logger->info('Getting metadata for npub', ['npub' => $npub]);
        // Npubs are converted to hex for the request down the line
        $request = $this->createNostrRequest(
            kinds: [KindsEnum::METADATA],
            filters: ['authors' => [$npub]]
        );

        $events = $this->processResponse($request->send(), function($received) {
            $this->logger->info('Getting metadata for npub', ['item' => $received]);
            return $received;
        });

        $this->logger->info('Getting metadata for npub', ['response' => $events]);

        if (empty($events)) {
            $meta = new \stdClass();
            $content = new \stdClass();
            $content->name = substr($npub, 0, 8) . '…' . substr($npub, -4);
            $meta->content = json_encode($content);
            return $meta;
        }

        // Sort by date and return newest
        usort($events, fn($a, $b) => $b->created_at <=> $a->created_at);
        return $events[0];
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
    public function getLongFormContent($from = null, $to = null): void
    {
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::LONGFORM]);
        $filter->setSince(strtotime('-1 week')); // default
        if ($from !== null) {
            $filter->setSince($from);
        }
        if ($to !== null) {
            $filter->setUntil($to);
        }
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
                $this->saveLongFormContent($filtered);
            }
        }
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

        // First try with theforest relay and any relays in $relayList
        // Add theforest relay to the list, if not already present
        if (!in_array('wss://theforest.nostr1.com', $relayList)) {
            $relayList[] = 'wss://theforest.nostr1.com';
        }
        $forestRelaySet = $this->createRelaySet($relayList);
        $response = null;
        $hasEvents = false;

        try {
            $request = new Request($forestRelaySet, $requestMessage);
            $response = $request->send();

            // Check if we got any events
            foreach ($response as $relayRes) {
                $filtered = array_filter($relayRes, function ($item) {
                    return $item->type === 'EVENT';
                });
                if (count($filtered) > 0) {
                    $this->saveLongFormContent($filtered);
                    $hasEvents = true;
                    break;
                }
            }

            // If no events found in theforest, try author's reputable relays
            if (!$hasEvents) {
                $topAuthorRelays = $this->getTopReputableRelaysForAuthor($author);
                $authorRelaySet = $this->createRelaySet($topAuthorRelays);

                $this->logger->info('No results from theforest, trying author relays', [
                    'relays' => $topAuthorRelays
                ]);

                $request = new Request($authorRelaySet, $requestMessage);
                $response = $request->send();

                foreach ($response as $relayRes) {
                    $filtered = array_filter($relayRes, function ($item) {
                        return $item->type === 'EVENT';
                    });
                    if (count($filtered) > 0) {
                        $this->saveLongFormContent($filtered);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            // If any error occurs, fall back to default relay set
            $this->logger->error('Error querying relays, falling back to defaults', [
                'error' => $e->getMessage()
            ]);
            $request = new Request($this->defaultRelaySet, $requestMessage);
            $response = $request->send();

            foreach ($response as $relayRes) {
                $filtered = array_filter($relayRes, function ($item) {
                    return $item->type === 'EVENT';
                });
                if (count($filtered) > 0) {
                    $this->saveLongFormContent($filtered);
                }
            }
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
     * @throws \Exception
     */
    public function getNpubRelays($npub): array
    {
        // Convert npub to hex
        $keys = new Key();
        $pubkey = $keys->convertToHex($npub);
        // Get relays
        $request = $this->createNostrRequest(
            kinds: [KindsEnum::RELAY_LIST],
            filters: ['authors' => [$pubkey]],
            relaySet: $this->defaultRelaySet
        );
        $response = $this->processResponse($request->send(), function($received) {
            return $received;
        });
        if (empty($response)) {
            return [];
        }
        // Sort by date and use newest
        usort($response, fn($a, $b) => $b->created_at <=> $a->created_at);
        // Process tags of the $response[0] and extract relays
        $relays = [];
        foreach ($response[0]->tags as $tag) {
            if ($tag[0] === 'r') {
                $relays[] = $tag[1];
            }
        }
        // Remove duplicates, localhost and any non-wss relays
        return array_filter(array_unique($relays), function ($relay) {
            return str_starts_with($relay, 'wss:') && !str_contains($relay, 'localhost');
        });
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
                        // throw new UnauthorizedHttpException('', 'Relay requires authentication');
                    case 'ERROR':
                    case 'NOTICE':
                        // throw new \Exception('An error occurred');
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
    public function getLongFormContentForPubkey(string $pubkey): array
    {
        $articles = [];
        $relaySet = $this->defaultRelaySet;

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
                        $article = $this->articleFactory->createFromLongFormContentEvent($item->event);
                        $articles[] = $article;
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

    public function getArticles(array $slugs): array
    {
        $articles = [];
        $subscription = new Subscription();
        $subscriptionId = $subscription->setId();
        $filter = new Filter();
        $filter->setKinds([KindsEnum::LONGFORM]);
        $filter->setTag('#d', $slugs);
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);

        try {
            $request = new Request($this->defaultRelaySet, $requestMessage);
            $response = $request->send();
            $hasEvents = false;

            // Check if we got any events
            foreach ($response as $value) {
                foreach ($value as $item) {
                    if ($item->type === 'EVENT') {
                        if (!isset($articles[$item->event->id])) {
                            $articles[$item->event->id] = $item->event;
                            $hasEvents = true;
                        }
                    }
                }
            }

            // If no articles found, try the default relay set
            if (!$hasEvents && !empty($slugs)) {
                $this->logger->info('No results from theforest, trying default relays');

                $request = new Request($this->defaultRelaySet, $requestMessage);
                $response = $request->send();

                foreach ($response as $value) {
                    foreach ($value as $item) {
                        if ($item->type === 'EVENT') {
                            if (!isset($articles[$item->event->id])) {
                                $articles[$item->event->id] = $item->event;
                            }
                        } elseif (in_array($item->type, ['AUTH', 'ERROR', 'NOTICE'])) {
                            $this->logger->error('An error while getting articles.', ['response' => $item]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error querying relays', [
                'error' => $e->getMessage()
            ]);

            // Fall back to default relay set
            $request = new Request($this->defaultRelaySet, $requestMessage);
            $response = $request->send();

            foreach ($response as $value) {
                foreach ($value as $item) {
                    if ($item->type === 'EVENT') {
                        if (!isset($articles[$item->event->id])) {
                            $articles[$item->event->id] = $item->event;
                        }
                    }
                }
            }
        }

        return $articles;
    }

    private function createNostrRequest(array $kinds, array $filters = [], ?RelaySet $relaySet = null): Request
    {
        $subscription = new Subscription();
        $filter = new Filter();
        $filter->setKinds($kinds);

        foreach ($filters as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($filter, $method)) {
                $filter->$method($value);
            }
        }

        $requestMessage = new RequestMessage($subscription->getId(), [$filter]);
        return new Request($relaySet ?? $this->defaultRelaySet, $requestMessage);
    }

    private function processResponse(array $response, callable $eventHandler): array
    {
        $results = [];
        foreach ($response as $relayRes) {
            foreach ($relayRes as $item) {
                try {
                    switch ($item->type) {
                        case 'EVENT':
                            $result = $eventHandler($item->event);
                            if ($result !== null) {
                                $results[] = $result;
                            }
                            break;
                        case 'AUTH':
                            $this->logger->warning('Relay requires authentication', ['response' => $item]);
                            break;
                        case 'ERROR':
                        case 'NOTICE':
                            $this->logger->error('Relay error/notice', ['response' => $item]);
                            break;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error processing event', [
                        'exception' => $e->getMessage(),
                        'event' => $item
                    ]);
                }
            }
        }
        return $results;
    }
}
