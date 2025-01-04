<?php

namespace App\Twig\Components;
use App\Entity\Event as EventEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class IndexTabs
{
    use DefaultActionTrait;

    private $index;

    #[LiveProp(writable: true)]
    public int $activeTab = 1; // Default active tab

    #[LiveProp]
    public array $tabs = [
        ['id' => 1, 'label' => 'Tab 1'],
        ['id' => 2, 'label' => 'Tab 2'],
        ['id' => 3, 'label' => 'Tab 3'],
    ];

    #[LiveAction]
    public function changeTab(#[LiveArg] int $id): void
    {
        $this->activeTab = $id;
    }

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function mount(EventEntity $index): void
    {
        $this->index = $index;
        // TODO extract categories from index and feed into tabs
        foreach ($index->getTags() as $tag) {
            if (array_key_first($tag) === 'a') {
                $ref = $tag['a'];
                list($kind,$npub,$slug) = explode(':',$ref);
                // find all connected indices
                $this->entityManager->getRepository(EventEntity::class)->findOneBy(['slug' => $slug]);
            }
        }
    }

    public function getTabContent(): string
    {
        return match ($this->activeTab) {
            1 => 'This is content for Tab 1. Loaded directly in Live Component!',
            2 => 'This is content for Tab 2. No AJAX needed!',
            3 => 'This is content for Tab 3. Server-side rendering!',
            default => 'No content available.',
        };
    }
}
