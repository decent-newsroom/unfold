<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Event as EventEntity;
use App\Entity\Nzine;
use App\Entity\NzineBot;
use App\Entity\User;
use App\Enum\KindsEnum;
use App\Enum\RolesEnum;
use App\Form\NzineBotType;
use App\Form\NzineType;
use App\Service\EncryptionService;
use App\Service\NostrClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Sign\Sign;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\String\Slugger\AsciiSlugger;

class NzineController extends AbstractController
{
    /**
     * @throws \JsonException
     */
    #[Route('/nzine', name: 'nzine_index')]
    public function index(Request $request, EncryptionService $encryptionService, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(NzineBotType::class);
        $form->handleRequest($request);
        $user = $this->getUser();

        // TODO change into a workflow
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            // create NZine bot
            $key = new Key();
            $private_key = '8c55771e896581fffea62c6440e306d502630e9dbd067e484bf6fc9c83ede28c';
            // $private_key = $key->generatePrivateKey();
            // $bot = new NzineBot($encryptionService);
            // $bot->setNsec($private_key);
            $bot = $entityManager->getRepository(NzineBot::class)->find(1);
            //$entityManager->persist($bot);
            //$entityManager->flush();
            $profileContent = [
                'name' => $data['name'],
                'about' => $data['about'],
                'bot' => true
            ];

            // publish bot profile
            $profileEvent = new Event();
            $profileEvent->setKind(0);
            $profileEvent->setContent(json_encode($profileContent));
            $signer = new Sign();
            $signer->signEvent($profileEvent, $private_key);
            $eventMessage = new EventMessage($profileEvent);
            $relayUrl = 'wss://purplepag.es';
            $relay = new Relay($relayUrl);
            $relay->setMessage($eventMessage);
            // $result = $relay->send();

            // create NZine entity
            $nzine = new Nzine();
            $public_key  = $key->getPublicKey($private_key);
            $nzine->setNpub($public_key);
            $nzine->setNzineBot($bot);
            $nzine->setEditor($user->getUserIdentifier());
            // $entityManager->persist($nzine);
            // $entityManager->flush();

            // TODO add EDITOR role to the user
            $role = RolesEnum::EDITOR->value;
            $user = $entityManager->getRepository(User::class)->findOneBy(['npub' => $user->getUserIdentifier()]);
            $user->addRole($role);
            // $entityManager->persist($user);
            // $entityManager->flush();


            $slugger = new AsciiSlugger();
            $title = $profileContent['name'];
            $slug = 'nzine-'.$slugger->slug($title)->lower().'-'.rand(10000,99999);
            // create NZine main index
            $index = new Event();
            $index->setKind(KindsEnum::PUBLICATION_INDEX->value);

            $index->addTag(['d' => $slug]);
            $index->addTag(['title' => $title]);
            $index->addTag(['summary' => $profileContent['about']]);
            $index->addTag(['auto-update' => 'yes']);
            $index->addTag(['type' => 'magazine']);
            $signer = new Sign();
            $signer->signEvent($index, $private_key);
            // save to persistence, first map to EventEntity
            $serializer = new Serializer([new ObjectNormalizer()],[new JsonEncoder()]);
            $i = $serializer->deserialize($index->toJson(), EventEntity::class, 'json');
            // don't save any more for now
            $entityManager->persist($i);
            // $entityManager->flush();
            // TODO publish index to relays

            // TODO remove this, this is temporary, to not create a host of nzines
            $nzine = $entityManager->getRepository(Nzine::class)->findOneBy(['npub' => $nzine->getNpub()]);
            $nzine->setSlug($slug);
            $entityManager->persist($nzine);
            $entityManager->flush();

            return $this->redirectToRoute('nzine_edit', ['npub' => $public_key ]);
        }
        // on submit, create a key pair and save it securely
        // create a new NZine entity and link it to the key pair
        // then redirect to edit

        return $this->render('pages/nzine-editor.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/nzine/{npub}', name: 'nzine_edit')]
    public function edit(Request $request, $npub, EntityManagerInterface $entityManager,
                         ManagerRegistry $managerRegistry, NostrClient $nostrClient): Response
    {
        $nzine = $entityManager->getRepository(Nzine::class)->findOneBy(['npub' => $npub]);
        if (!$nzine) {
            throw $this->createNotFoundException('N-Zine not found');
        }
        try {
            $bot = $entityManager->getRepository(User::class)->findOneBy(['npub' => $npub]);
        } catch (\Exception $e) {
            // sth went wrong, but whatever
            $managerRegistry->resetManager();
        }

        $catForm = $this->createForm(NzineType::class, ['categories' => $nzine->getMainCategories()]);
        $catForm->handleRequest($request);
        if ($catForm->isSubmitted() && $catForm->isValid()) {
            // Process and normalize the 'tags' field
            $data = $catForm->get('categories')->getData();

            $nzine->setMainCategories($data);

//            try {
//                $entityManager->beginTransaction();
//                $entityManager->persist($nzine);
//                $entityManager->flush();
//                $entityManager->commit();
//            } catch (Exception $e) {
//                $entityManager->rollback();
//                $managerRegistry->resetManager();
//            }

            // existing indices
            $indices = $entityManager->getRepository(EventEntity::class)->findBy(['pubkey' => $npub, 'kind' => KindsEnum::PUBLICATION_INDEX]);
            // get oldest, treat it as root
            $mainIndex = $indices[0];
            // TODO create and update indices
            foreach ($data as $cat) {
                // find or create new index
                $slugger = new AsciiSlugger();
                $title = $cat['title'];
                $slug = $mainIndex->getSlug().'-'.$slugger->slug($title)->lower();
                // create category index
                $index = new Event();
                $index->setKind(KindsEnum::PUBLICATION_INDEX->value);

                $index->addTag(['d' => $slug]);
                $index->addTag(['title' => $title]);
                $index->addTag(['auto-update' => 'yes']);
                $index->addTag(['type' => 'magazine']);
                // TODO add indexed items that fall into the category

                $signer = new Sign();
                // TODO get key
                // $signer->signEvent($index, $private_key);
                // save to persistence, first map to EventEntity
                $serializer = new Serializer([new ObjectNormalizer()],[new JsonEncoder()]);
                $i = $serializer->deserialize($index->toJson(), EventEntity::class, 'json');
                // don't save any more for now
                $entityManager->persist($i);
                // $entityManager->flush();
                // TODO publish index to relays
            }

            // TODO add the new and updated indices to the main index

            // redirect to route nzine_view
            return $this->redirectToRoute('nzine_view', [
                'npub' => $nzine->getNpub(),
            ]);
        }


        return $this->render('pages/nzine-editor.html.twig', [
            'nzine' => $nzine,
            'bot' => $bot,
            'catForm' => $catForm
        ]);
    }

    /**
     * Update and (re)publish indices,
     * when you want to look for new articles or
     * when categories have changed
     * @return void
     */
    #[Route('/nzine/{npub}', name: 'nzine_update')]
    public function nzineUpdate()
    {
        // TODO make this a separate step and create all the indices and populate with articles all at once

    }


    #[Route('/nzine/v/{npub}', name: 'nzine_view')]
    public function nzineView($npub, EntityManagerInterface $entityManager) {
        $nzine = $entityManager->getRepository(Nzine::class)->findOneBy(['npub' => $npub]);
        if (!$nzine) {
            throw $this->createNotFoundException('N-Zine not found');
        }
        // Find all index events for this nzine
        $indices = $entityManager->getRepository(EventEntity::class)->findBy(['pubkey' => $npub, 'kind' => KindsEnum::PUBLICATION_INDEX]);
        // TODO Filter out the main index by the d-tag saved to entity or something
        $main = $indices[0];
        // let's pretend we have some nested indices in this zine
        $main->setTags(['a', '30040:'.$npub.':1'.$nzine->getSlug()]);
        $main->setTags(['a', '30040:'.$npub.':2'.$nzine->getSlug()]);


        return $this->render('pages/nzine.html.twig', [
            'nzine' => $nzine,
            'index' => $main
        ]);
    }

    #[Route('/nzine/v/{npub}/{cat}', name: 'nzine_category')]
    public function nzineCategory($npub, $cat, EntityManagerInterface $entityManager) {
        $nzine = $entityManager->getRepository(Nzine::class)->findOneBy(['npub' => $npub]);
        if (!$nzine) {
            throw $this->createNotFoundException('N-Zine not found');
        }
        $bot = $entityManager->getRepository(User::class)->findOneBy(['npub' => $npub]);

        $tags = [];
        foreach ($nzine->getMainCategories() as $category) {
            if (isset($category['title']) && $category['title'] === $cat) {
                $tags = $category['tags'] ?? [];
            }
        }

        $all = $entityManager->getRepository(Article::class)->findAll();
        $list = array_slice($all, 0, 100);

        $filtered = [];
        foreach ($tags as $tag) {
            $partial = array_filter($list, function($v) use ($tag) {
                /* @var Article $v */
                return in_array($tag, $v->getTopics() ?? []);
            });
            $filtered = array_merge($filtered, $partial);
        }


        return $this->render('pages/nzine.html.twig', [
            'nzine' => $nzine,
            'bot' => $bot,
            'list' => $filtered
        ]);
    }

}
