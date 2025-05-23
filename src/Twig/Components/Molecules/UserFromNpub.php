<?php

namespace App\Twig\Components\Molecules;

use App\Service\RedisCacheService;
use swentel\nostr\Key\Key;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $pubkey;
    public string $npub;
    public $user = null;

    public function __construct(private readonly RedisCacheService $redisCacheService)
    {
    }

    public function mount(string $pubkey): void
    {
        $keys = new Key();
        $this->pubkey = $pubkey;
        $this->npub = $keys->convertPublicKeyToBech32($pubkey);
        $this->user = $this->redisCacheService->getMetadata($this->npub);
    }
}
