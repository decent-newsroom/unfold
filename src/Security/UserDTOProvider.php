<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\KindsEnum;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use swentel\nostr\Key\Key;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

readonly class UserDTOProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NostrClient            $nostrClient
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
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $identifier]);
        $metadata = $relays = null;

        if (!$user) {
            // user
            $user = new User();
            $user->setNpub($identifier);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        try {
            $key = new Key();
            $pubkey = $key->convertToHex($identifier);
            $data = $this->nostrClient->getLoginData($pubkey);
            foreach ($data as $d) {
                $ev = $d->event;
                if ($ev->kind === KindsEnum::METADATA) {
                    $metadata = json_decode($ev->content);
                }
                if ($ev->kind === KindsEnum::RELAY_LIST) {
                    $relays = $ev->tags;
                }
            }
        } catch (\Exception $e) {
            // nothing to do here right now
        }

        if (is_null($metadata)) {
            // if no metadata event, use what you have
            $metadata = new \stdClass();
            $metadata->name =  substr($identifier, 0, 5) . ':' .  substr($identifier, -5);
        }
        $user->setMetadata($metadata);
        $user->setRelays($relays);

        return $user;

    }
}
