<?php

namespace App\Controller;

use App\Entity\Article;
use App\Enum\KindsEnum;
use App\Form\EditorType;
use App\Service\NostrClient;
use App\Util\CommonMark\Converter;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\Exception\CommonMarkException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

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

        $meta = $nostrClient->getNpubMetadata($article->getPubkey());
        $author = (array) json_decode($meta->content);

        return $this->render('Pages/article.html.twig', [
            'article' => $article,
            'author' => $author,
            'content' => $cacheItem->get()
        ]);
    }


    /**
     * Create new article
     */
    #[Route('/article-editor/create', name: 'editor-create')]
    #[Route('/article-editor/edit/{id}', name: 'editor-edit')]
    public function newArticle(Request $request, EntityManagerInterface $entityManager, WorkflowInterface $articlePublishingWorkflow, Article $article = null): Response
    {
        if (!$article) {
            $article = new Article();
            $article->setKind(KindsEnum::LONGFORM);
            $article->setCreatedAt(new \DateTimeImmutable());
            $formAction = $this->generateUrl('editor-create');
        } else {
            $formAction = $this->generateUrl('editor-edit', ['id' => $article->getId()]);
        }

        $form = $this->createForm(EditorType::class, $article, ['action' => $formAction]);
        $form->handleRequest($request);

        // Step 3: Check if the form is submitted and valid
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $currentPubkey = $user->getUserIdentifier();
            if ($article->getPubkey() === null) {
                $article->setPubkey($currentPubkey);
            }

            // Check which button was clicked
            if ($form->get('actions')->get('submit')->isClicked()) {
                // Save button was clicked, handle the "Publish" action
                $this->addFlash('success', 'Product published!');
            } elseif ($form->get('actions')->get('draft')->isClicked()) {
                // Save and Publish button was clicked, handle the "Draft" action
                $this->addFlash('success', 'Product saved as draft!');
            } elseif ($form->get('actions')->get('preview')->isClicked()) {
                // Preview button was clicked, handle the "Preview" action
                $article->setSig(''); // clear the sig
                $entityManager->persist($article);
                $entityManager->flush();
                return $this->redirectToRoute('article-preview', ['id' => $article->getId()]);
            }
        }

        // load template with content editor
        return $this->render('pages/editor.html.twig', [
            'article' => $article,
            'form' => $this->createForm(EditorType::class, $article)->createView(),
        ]);
    }

    /**
     * Preview article
     */
    #[Route('/article-preview/{id}', name: 'article-preview')]
    public function preview($id, EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(Article::class);
        $article = $repository->findOneBy(['id' => $id]);

        return $this->render('pages/article.html.twig', [
            'article' => $article,
            'author' => $this->getUser(),
        ]);
    }

}
