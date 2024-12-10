<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthorController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/p/{npub}', name: 'author-profile')]
    public function index($npub, EntityManagerInterface $entityManager): Response
    {
        $author = $entityManager->getRepository(User::class)->findOneBy(['npub' => $npub]);
        if (!$author) {
            throw new \Exception('No author found');
        }

        $articles = $entityManager->getRepository(Article::class)->findBy(['pubkey' => $npub], ['createdAt' => 'DESC']);

        return $this->render('Pages/author.html.twig', [
            'author' => $author,
            'articles' => $articles
        ]);
    }
}
