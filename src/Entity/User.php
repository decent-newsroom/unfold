<?php

namespace App\Entity;

use App\Repository\UserEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity storing local user representations
 */
#[ORM\Entity(repositoryClass: UserEntityRepository::class)]
#[ORM\Table(name: "app_user")]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private ?string $npub = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $roles = [];

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
}
