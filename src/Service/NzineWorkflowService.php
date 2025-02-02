<?php

namespace App\Service;

use App\Entity\Nzine;
use App\Entity\NzineBot;
use App\Entity\Event as EventEntity;
use App\Entity\User;
use App\Enum\KindsEnum;
use App\Enum\RolesEnum;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Workflow\WorkflowInterface;
use Doctrine\ORM\EntityManagerInterface;

class NzineWorkflowService
{
    private Nzine $nzine;

    public function __construct(private readonly WorkflowInterface $nzineWorkflow,
                                private readonly NostrClient $nostrClient,
                                private readonly EncryptionService $encryptionService,
                                private readonly EntityManagerInterface $entityManager)
    {
    }

    public function init($nzine = null): Nzine
    {
        if (!is_null($nzine)) {
            $this->nzine = $nzine;
        } else {
            $this->nzine = new Nzine();
        }

        return $this->nzine;
    }

    public function createProfile($nzine, $name, $about, $user): Nzine
    {
        if (!$this->nzineWorkflow->can($nzine, 'create_profile')) {
            throw new \LogicException('Cannot create profile in the current state.');
        }

        $this->nzine = $nzine;

        // create NZine bot
        $key = new Key();
        $private_key = $key->generatePrivateKey();
        $bot = new NzineBot($this->encryptionService);
        $bot->setNsec($private_key);
        $this->entityManager->persist($bot);
        // publish bot profile
        $profileContent = [
            'name' => $name,
            'about' => $about,
            'bot' => true
        ];
        $profileEvent = new Event();
        $profileEvent->setKind(KindsEnum::METADATA->value);
        $profileEvent->setContent(json_encode($profileContent));
        $signer = new Sign();
        $signer->signEvent($profileEvent, $private_key);
        $this->nostrClient->publishEvent($profileEvent, ['wss://purplepag.es']);

        // add EDITOR role to the user
        $role = RolesEnum::EDITOR->value;
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['npub' => $user->getUserIdentifier()]);
        $user->addRole($role);
        $this->entityManager->persist($user);

        // create NZine entity
        $public_key  = $key->getPublicKey($private_key);
        $this->nzine->setNpub($public_key);
        $this->nzine->setNzineBot($bot);
        $this->nzine->setEditor($user->getUserIdentifier());
        $this->nzineWorkflow->apply($this->nzine, 'create_profile');
        $this->entityManager->persist($this->nzine);
        $this->entityManager->flush();

        return $this->nzine;
    }

    /**
     * @throws \JsonException
     */
    public function createMainIndex(Nzine $nzine, string $title, string $summary): void
    {
        if (!$this->nzineWorkflow->can($nzine, 'create_main_index')) {
            throw new \LogicException('Cannot create main index in the current state.');
        }

        $bot = $nzine->getNzineBot();
        $private_key = $bot->getNsec();

        $slugger = new AsciiSlugger();
        $slug = 'nzine-'.$slugger->slug($title)->lower().'-'.rand(10000,99999);
        // create NZine main index
        $index = new Event();
        $index->setKind(KindsEnum::PUBLICATION_INDEX->value);

        $index->addTag(['d' => $slug]);
        $index->addTag(['title' => $title]);
        $index->addTag(['summary' => $summary]);
        $index->addTag(['auto-update' => 'yes']);
        $index->addTag(['type' => 'magazine']);
        $signer = new Sign();
        $signer->signEvent($index, $private_key);
        // save to persistence, first map to EventEntity
        $serializer = new Serializer([new ObjectNormalizer()],[new JsonEncoder()]);
        $i = $serializer->deserialize($index->toJson(), EventEntity::class, 'json');
        $this->entityManager->persist($i);

        $this->nzineWorkflow->apply($nzine, 'create_main_index');
        $this->entityManager->persist($nzine);
        $this->entityManager->flush();
    }

    public function createNestedIndex(Nzine $nzine, string $categoryTitle, array $tags): void
    {
        if (!$this->nzineWorkflow->can($nzine, 'create_nested_indices')) {
            throw new \LogicException('Cannot create nested indices in the current state.');
        }

        // Example logic: Create a nested index for the category
        $nestedIndex = new EventEntity();
        // $nestedIndex->setTitle($categoryTitle);
        $nestedIndex->setTags($tags);
        $nestedIndex->setKind('30040'); // Assuming 30040 is the kind for publication indices

        $this->entityManager->persist($nestedIndex);

        $this->nzineWorkflow->apply($nzine, 'create_nested_indices');
        $this->entityManager->persist($nzine);
        $this->entityManager->flush();
    }
}

