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
    /** ['id' => 1, 'label' => 'Tab 1'] */
    public array $tabs = [];

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
                $cat = $this->entityManager->getRepository(EventEntity::class)->findOneBy(['slug' => $slug]);
            } elseif (array_key_first($tag) === 'e') {
                $cat = $this->entityManager->getRepository(EventEntity::class)->findOneBy(['id' => $tag['e']]);
                $next = count($this->tabs) + 1;
                $this->tabs[] = ['id' => $next, 'label' => $cat->getTitle()];
            }
        }
    }

    public function getTabContent(): string|array
    {
        return match ($this->activeTab) {
            1 => [
                '30023:c1e6505c02da8d1b0a5b3d6db6e19b2eb22dcd54f0e86306ec8a213902b3157e:809797',
                '30032:c1e6505c02da8d1b0a5b3d6db6e19b2eb22dcd54f0e86306ec8a213902b3157e:012025-7zsaxt'
                ],
            2 => 'This is content for Tab 2. No AJAX needed!',
            default => 'No content available.',
        };
    }
}
