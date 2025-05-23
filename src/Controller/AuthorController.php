<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RedisCacheService;
use Elastica\Query\Terms;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use swentel\nostr\Key\Key;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthorController extends AbstractController
{
    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/p/{npub}', name: 'author-profile', requirements: ['npub' => '^npub1.*'])]
    public function index($npub, RedisCacheService $redisCacheService, FinderInterface $finder): Response
    {
        $keys = new Key();
        $pubkey = $keys->convertToHex($npub);

        $author = $redisCacheService->getMetadata($npub);
        $relays = $redisCacheService->getRelays($npub);

        // Look for articles in index, assume indexing is done regularly
        // TODO give users an option to reindex
        $query = new Terms('pubkey', [$pubkey]);
        $list = $finder->find($query, 25);

        // deduplicate by slugs
        $articles = [];
        foreach ($list as $item) {
            if (!key_exists((string) $item->getSlug(), $articles)) {
                $articles[(string) $item->getSlug()] = $item;
            }
        }

        return $this->render('Pages/author.html.twig', [
            'author' => $author,
            'npub' => $npub,
            'articles' => $articles,
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
