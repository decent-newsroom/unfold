<?php

namespace App\Twig\Components\Molecules;

use App\Service\NostrClient;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Key\Key;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UserFromNpub
{
    public string $pubkey;
    public string $npub;
    public $user = null;

    public function __construct(
        private readonly CacheInterface $redisCache,
        private readonly NostrClient $nostrClient)
    {
    }

    public function mount(string $pubkey): void
    {

        $keys = new Key();
        $this->pubkey = $pubkey;
        $this->npub = $keys->convertPublicKeyToBech32($pubkey);

        try {
            $cacheKey = '0_' . $this->pubkey;

            $this->user = $this->redisCache->get($cacheKey, function (ItemInterface $item) use ($pubkey) {
                $item->expiresAfter(3600); // 1 hour, adjust as needed

                $meta = $this->nostrClient->getNpubMetadata($pubkey);
                return (array) json_decode($meta->content);
            });

        } catch (InvalidArgumentException | \Exception $e) {
            // nothing to do
        }
    }
}
