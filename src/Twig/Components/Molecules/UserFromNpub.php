<?php

namespace App\Twig\Components\Molecules;

use App\Service\NostrClient;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Key\Key;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $pubkey;
    public string $npub;
    public ?array $user = null;

    public function __construct(private readonly NostrClient $nostrClient, private readonly CacheInterface $redisCache)
    {
    }

    public function mount(string $pubkey): void
    {
        $keys = new Key();
        $this->pubkey = $pubkey;
        $this->npub = $keys->convertPublicKeyToBech32($pubkey);

        try {
            $this->user = $this->redisCache->get('user_' . $this->npub, function () {
                try {
                    $meta = $this->nostrClient->getNpubMetadata($this->npub);
                    return (array) json_decode($meta->content);
                } catch (InvalidArgumentException|\Exception) {
                    return null;
                }
            });
        } catch (InvalidArgumentException $e) {
            $this->user = null;
        }
    }
}
