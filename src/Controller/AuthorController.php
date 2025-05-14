<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Event;
use App\Enum\KindsEnum;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Key\Key;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AuthorController extends AbstractController
{
    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/p/{npub}', name: 'author-profile', requirements: ['npub' => '^npub1.*'])]
    public function index($npub, CacheInterface $redisCache, EntityManagerInterface $entityManager, NostrClient $client): Response
    {
        $keys = new Key();
        $pubkey = $keys->convertToHex($npub);
        $relays = [];

        try {
            $cacheKey = '0_' . $pubkey;

            $author = $redisCache->get($cacheKey, function (ItemInterface $item) use ($pubkey, $client) {
                $item->expiresAfter(3600); // 1 hour, adjust as needed

                $meta = $client->getNpubMetadata($pubkey);
                return (array) json_decode($meta->content ?? '{}');
            });

        } catch (InvalidArgumentException | \Exception $e) {
            // nothing to do
        }

        try {
            $cacheKey = '10002_' . $pubkey;

            $relays = $redisCache->get($cacheKey, function (ItemInterface $item) use ($pubkey, $client) {
                $item->expiresAfter(3600); // 1 hour, adjust as needed

                return $client->getNpubRelays($pubkey);
            });
        } catch (InvalidArgumentException | \Exception $e) {
            // nothing to do
        }

        $list = $client->getLongFormContentForPubkey($pubkey);

        // deduplicate by slugs
        $articles = [];
        foreach ($list as $item) {
            if (!key_exists((string) $item->getSlug(), $articles)) {
                $articles[(string) $item->getSlug()] = $item;
            }
        }

        // $indices = $entityManager->getRepository(Event::class)->findBy(['pubkey' => $pubkey, 'kind' => KindsEnum::PUBLICATION_INDEX]);

        // $nzines = $entityManager->getRepository(Nzine::class)->findBy(['editor' => $pubkey]);

        // $nzine = $entityManager->getRepository(Nzine::class)->findBy(['npub' => $npub]);

        return $this->render('Pages/author.html.twig', [
            'author' => $author,
            'npub' => $npub,
            'articles' => $articles,
            'nzine' => null,
            'nzines' => null,
            'idx' => null,
            'relays' => $relays
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/p/{pubkey}', name: 'author-redirect')]
    public function authorRedirect($pubkey): Response
    {
        $keys = new Key();

        $npub = $keys->convertPublicKeyToBech32($pubkey);

        return $this->redirectToRoute('author-profile', ['npub' => $npub]);
    }
}
