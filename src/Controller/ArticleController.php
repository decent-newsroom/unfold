<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Service\NostrClient;
use App\Util\CommonMark\Converter;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArticleController  extends AbstractController
{
    /**
     * @throws InvalidArgumentException|CommonMarkException
     */
    #[Route('/article/d/{slug}', name: 'article-slug')]
    public function article(EntityManagerInterface $entityManager, CacheItemPoolInterface $articlesCache,
                            NostrClient $nostrClient, Converter $converter, $slug): Response
    {
        $article = null;
        // check if an item with same eventId already exists in the db
        $repository = $entityManager->getRepository(Article::class);
        $articles = $repository->findBy(['slug' => $slug]);
        $revisions = count($repository->findBy(['slug' => $slug]));

        if ($revisions > 1) {
            // sort articles by created at date
            usort($articles, function ($a, $b) {
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            });
            // get the last article
            $article = end($articles);
        } else {
            $article = $articles[0];
        }

        if (!$article) {
            throw $this->createNotFoundException('The article does not exist');
        }

        $cacheKey = 'article_' . $article->getId();
        $cacheItem = $articlesCache->getItem($cacheKey);
        if (!$cacheItem->isHit()) {
            $cacheItem->set($converter->convertToHtml($article->getContent()));
            $articlesCache->save($cacheItem);
        }

        // find user by npub
        try {
            $nostrClient->getMetadata([$article->getPubkey()]);
        } catch (\Exception) {
            // eh
        }
        $author = $entityManager->getRepository(User::class)->findOneBy(['npub' => $article->getPubkey()]);

        return $this->render('Pages/article.html.twig', [
            'article' => $article,
            'author' => $author,
            'content' => $cacheItem->get()
        ]);
    }

}
