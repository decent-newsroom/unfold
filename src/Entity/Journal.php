<?php

namespace App\Entity;

use App\Repository\JournalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalRepository::class)]
class Journal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $npub = null;

    #[ORM\Column]
    private array $mainCategories = [];

    #[ORM\Column(nullable: true)]
    private ?array $lists = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $editor = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNpub(): ?string
    {
        return $this->npub;
    }

    public function setNpub(string $npub): static
    {
        $this->npub = $npub;

        return $this;
    }

    public function getMainCategories(): array
    {
        return $this->mainCategories;
    }

    public function setMainCategories(array $mainCategories): static
    {
        $this->mainCategories = $mainCategories;

        return $this;
    }

    public function getLists(): ?array
    {
        return $this->lists;
    }

    public function setLists(?array $lists): static
    {
        $this->lists = $lists;

        return $this;
    }

    public function getEditor(): ?string
    {
        return $this->editor;
    }

    public function setEditor(?string $editor): static
    {
        $this->editor = $editor;

        return $this;
    }
}
