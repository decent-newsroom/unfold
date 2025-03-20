<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Event;
use App\Entity\Nzine;
use App\Enum\KindsEnum;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthorController extends AbstractController
{
    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/p/{npub}', name: 'author-profile')]
    public function index($npub, EntityManagerInterface $entityManager, NostrClient $client): Response
    {



        $meta = $client->getNpubMetadata($npub);
        $author = (array) json_decode($meta->content ?? '{}');

        // $client->getNpubLongForm($npub);

        $list = $entityManager->getRepository(Article::class)->findBy(['pubkey' => $npub, 'kind' => KindsEnum::LONGFORM], ['createdAt' => 'DESC']);

        // deduplicate by slugs
        $articles = [];
        foreach ($list as $item) {
            if (!key_exists((string) $item->getSlug(), $articles)) {
                $articles[(string) $item->getSlug()] = $item;
            }
        }

        $indices = $entityManager->getRepository(Event::class)->findBy(['pubkey' => $npub, 'kind' => KindsEnum::PUBLICATION_INDEX]);

        $nzines = $entityManager->getRepository(Nzine::class)->findBy(['editor' => $npub]);

        $nzine = $entityManager->getRepository(Nzine::class)->findBy(['npub' => $npub]);

        return $this->render('Pages/author.html.twig', [
            'author' => $author,
            'npub' => $npub,
            'articles' => $articles,
            'nzine' => $nzine,
            'nzines' => $nzines,
            'idx' => $indices
        ]);
    }
}
