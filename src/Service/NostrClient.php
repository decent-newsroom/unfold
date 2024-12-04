<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\KindsEnum;
use App\Factory\ArticleFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Serializer\SerializerInterface;

class NostrClient
{
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly ArticleFactory $articleFactory,
                                private readonly SerializerInterface    $serializer,
                                private readonly LoggerInterface        $logger)
    {
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
        $filter->setSince(strtotime('-1 year')); //
        $filter->setUntil(strtotime('-11 months')); //
        $requestMessage = new RequestMessage($subscriptionId, [$filter]);
        // TODO make relays configurable
        $relays = new RelaySet();
        $relays->addRelay(new Relay('wss://nos.lol')); // default relay

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
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
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
                }
            }
        }
    }
}
