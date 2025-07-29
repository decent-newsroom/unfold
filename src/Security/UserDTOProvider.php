<?php

namespace App\Security;

use App\Entity\User;
use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Provides user data transfer object (DTO) operations for authentication and user management.
 *
 * This class is responsible for refreshing user data from the database and cache,
 * and for determining if a given class is supported by the provider.
 */
readonly class UserDTOProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheService      $cacheService,
        private LoggerInterface        $logger
    )
    {
    }

    /**
     * Refreshes the user by reloading it from the database and updating its metadata from cache.
     *
     * @param UserInterface $user The user to refresh.
     * @return UserInterface The refreshed user instance.
     * @throws \InvalidArgumentException If the provided user is not an instance of User.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid user type.');
        }
        $this->logger->info('Refresh user.', ['user' => $user->getUserIdentifier()]);
        $freshUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['npub' => $user->getUserIdentifier()]);
        $metadata = $this->cacheService->getMetadata($user->getUserIdentifier());
        $freshUser->setMetadata($metadata);
        return $freshUser;
    }

    /**
     * @inheritDoc
     */
    public function supportsClass(string $class): bool
    {
        /**
         * Checks if the provider supports the given user class.
         *
         * @param string $class The class name to check.
         * @return bool True if the class is supported, false otherwise.
         */
        return $class === User::class;
    }

    /**
     * @inheritDoc
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $this->logger->info('Load user by identifier.', ['identifier' => $identifier]);
        // Get or create user
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $identifier]);

        if (!$user) {
            $user = new User();
            $user->setNpub($identifier);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $metadata = $this->cacheService->getMetadata($identifier);
        $user->setMetadata($metadata);
        $this->logger->debug('User metadata set.', ['metadata' => json_encode($user->getMetadata())]);

        return $user;
    }
}
