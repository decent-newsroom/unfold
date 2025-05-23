<?php

namespace App\Security;

use App\Entity\User;
use App\Service\RedisCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

readonly class UserDTOProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RedisCacheService      $redisCacheService,
        private LoggerInterface        $logger
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid user type.');
        }
        $this->logger->info('Refresh user.', ['user' => $user->getUserIdentifier()]);
        $freshUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['npub' => $user->getUserIdentifier()]);
        $metadata = $this->redisCacheService->getMetadata($user->getUserIdentifier());
        $freshUser->setMetadata($metadata);
        return $freshUser;
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
        $this->logger->info('Load user by identifier.');
        // Get or create user
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $identifier]);

        if (!$user) {
            $user = new User();
            $user->setNpub($identifier);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $metadata = $this->redisCacheService->getMetadata($identifier);
        $user->setMetadata($metadata);
        $this->logger->debug('User metadata set.', ['metadata' => json_encode($user->getMetadata())]);

        return $user;
    }
}
