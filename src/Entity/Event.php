<?php

namespace App\Entity;

class Event
{
    private string $id;
    private int $kind = 0;
    private string $pubkey;
    private string $content = '';
    private int $created_at = 0;
    private array $tags = [];
    private string $sig;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function setKind(int $kind): void
    {
        $this->kind = $kind;
    }

    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function setPubkey(string $pubkey): void
    {
        $this->pubkey = $pubkey;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function setCreatedAt(int $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getSig(): string
    {
        return $this->sig;
    }

    public function setSig(string $sig): void
    {
        $this->sig = $sig;
    }


}
