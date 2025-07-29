<?php

namespace App\Twig\Components\Molecules;

use App\Service\CacheService;
use swentel\nostr\Key\Key;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $pubkey;
    public string $npub;
    public $user = null;

    public function __construct(private readonly CacheService $cacheService)
    {
    }

    public function mount(string $ident): void
    {
        // if npub doesn't start with 'npub' then assume it's a hex pubkey
        if (!str_starts_with($ident, 'npub')) {
            $keys = new Key();
            $this->pubkey = $ident;
            $this->npub = $keys->convertPublicKeyToBech32($ident);
        } else {
            $this->npub = $ident;
        }
        $this->user = $this->cacheService->getMetadata($this->npub);
    }
}
