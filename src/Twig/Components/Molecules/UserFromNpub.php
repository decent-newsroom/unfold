<?php

namespace App\Twig\Components\Molecules;

use App\Service\NostrClient;
use Psr\Cache\InvalidArgumentException;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $npub;
    public ?array $user = null;

    public function __construct(private readonly NostrClient $nostrClient)
    {
    }

    public function mount(string $npub): void
    {
        $this->npub = $npub;
        try {
            $meta = $this->nostrClient->getNpubMetadata($npub);
            $this->user = (array) json_decode($meta->content);
        } catch (InvalidArgumentException|\Exception) {
            $this->user = null;
        }
    }
}
