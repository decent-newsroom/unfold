<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class UserDTO implements UserInterface
{
    private User $user;
    private $metadata;
    private $relays;

    public function __construct(User $user, $metadata, $relays)
    {
        $this->user = $user;
        $this->metadata = $metadata;
        $this->relays = $relays;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getDisplayName() {
        return $this->metadata->name;
    }

    /**
     * @return null|array
     */
    public function getRelays(): ?array
    {
        return $this->relays;
    }

    // Delegate UserInterface methods to the wrapped User entity
    public function getRoles(): array
    {
        return $this->user->getRoles();
    }

    public function eraseCredentials(): void
    {
        $this->metadata = null;
        $this->relays = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->user->getNpub();
    }

    public function getNpub(): string {
        return $this->user->getNpub();
    }
}
