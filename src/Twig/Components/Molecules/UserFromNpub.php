<?php

namespace App\Twig\Components\Molecules;

use App\Service\NostrClient;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Key\Key;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $pubkey;
    public string $npub;
    public $user = null;

    public function __construct(private readonly NostrClient $nostrClient)
    {
    }

    public function mount(string $pubkey): void
    {
        $keys = new Key();
        $this->pubkey = $pubkey;
        $this->npub = $keys->convertPublicKeyToBech32($pubkey);

        try {
            $meta = $this->nostrClient->getNpubMetadata($this->pubkey);
            $this->user = (array) json_decode($meta->content);
        } catch (InvalidArgumentException|\Exception) {
            // nothing to do
        }
    }
}
