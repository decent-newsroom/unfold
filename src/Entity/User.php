<?php

namespace App\Entity;

use App\Repository\UserEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entity storing local user representations
 */
#[ORM\Entity(repositoryClass: UserEntityRepository::class)]
#[ORM\Table(name: "app_user")]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private ?string $npub = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $roles = [];

    private $metadata = null;
    private $relays = null;

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return $roles;
    }

    public function setRoles(?array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getNpub(): ?string
    {
        return $this->npub;
    }

    public function setNpub(?string $npub): void
    {
        $this->npub = $npub;
    }

    public function eraseCredentials(): void
    {
        $this->metadata = null;
        $this->relays = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->getNpub();
    }

    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getDisplayName() {
        return $this->metadata->name;
    }

    /**
     * @param mixed $relays
     */
    public function setRelays($relays): void
    {
        $this->relays = $relays;
    }

    /**
     * @return null|array
     */
    public function getRelays(): ?array
    {
        return $this->relays;
    }
}
