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

class AuthorController extends AbstractController
{
    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/p/{npub}', name: 'author-profile', requirements: ['npub' => '^npub1.*'])]
    public function index($npub, EntityManagerInterface $entityManager, NostrClient $client): Response
    {
        $keys = new Key();

        $meta = $client->getNpubMetadata($npub);
        $author = (array) json_decode($meta->content ?? '{}');

        // $client->getNpubLongForm($npub);

        $pubkey = $keys->convertToHex($npub);

        $list = $entityManager->getRepository(Article::class)->findBy(['pubkey' => $pubkey, 'kind' => KindsEnum::LONGFORM], ['createdAt' => 'DESC']);

        // deduplicate by slugs
        $articles = [];
        foreach ($list as $item) {
            if (!key_exists((string) $item->getSlug(), $articles)) {
                $articles[(string) $item->getSlug()] = $item;
            }
        }

        $indices = $entityManager->getRepository(Event::class)->findBy(['pubkey' => $pubkey, 'kind' => KindsEnum::PUBLICATION_INDEX]);

        // $nzines = $entityManager->getRepository(Nzine::class)->findBy(['editor' => $pubkey]);

        // $nzine = $entityManager->getRepository(Nzine::class)->findBy(['npub' => $npub]);

        return $this->render('Pages/author.html.twig', [
            'author' => $author,
            'npub' => $npub,
            'articles' => $articles,
            'nzine' => null,
            'nzines' => null,
            'idx' => $indices
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
