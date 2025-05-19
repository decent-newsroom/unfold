<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\KindsEnum;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use swentel\nostr\Key\Key;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

readonly class UserDTOProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NostrClient            $nostrClient,
        private LoggerInterface        $logger
    ) {}

    /**
     * @inheritDoc
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid user type.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * @inheritDoc
     */
    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }

    /**
     * @inheritDoc
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $key = new Key();
            $pubkey = $key->convertToHex($identifier);
            $data = $this->nostrClient->getLoginData($pubkey);

            $this->logger->info('Load user by identifier.', ['data' => $data]);

            $metadata = null;
            $relays = null;

            foreach ($data as $d) {
                $ev = $d->event;
                $this->logger->info('Load user by identifier event.', ['event' => $ev]);
                if ($ev->kind === KindsEnum::METADATA->value) {
                    $metadata = json_decode($ev->content);
                    $this->logger->info('Load user by identifier event.', ['metadata' => $metadata]);
                }
                if ($ev->kind === KindsEnum::RELAY_LIST->value) {
                    $relays = $ev->tags;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error getting user data.', ['exception' => $e]);
            $metadata = null;
            $relays = null;
        }

        // Fallback metadata if none fetched
        if (is_null($metadata)) {
            $metadata = new \stdClass();
            $metadata->name = substr($identifier, 0, 8) . '…' . substr($identifier, -4);
        }

        // Get or create user
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $identifier]);

        if (!$user) {
            $user = new User();
            $user->setNpub($identifier);
            $this->entityManager->persist($user);
        }

        // Update with fresh metadata/relays
        $user->setMetadata($metadata);
        $user->setRelays($relays);

        $this->entityManager->flush();

        return $user;

    }
}
