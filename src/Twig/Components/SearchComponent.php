<?php

namespace App\Twig\Components;

use App\Credits\Service\CreditsManager;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class SearchComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';
    public array $results = [];

    public bool $interactive = true;

    public int $credits = 0;
    public ?string $npub = null;

    #[LiveProp]
    public int $vol = 0;

    public function __construct(
        private readonly FinderInterface $finder,
        private readonly CreditsManager $creditsManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger
    )
    {
        $token = $this->tokenStorage->getToken();
        $this->npub = $token?->getUserIdentifier();
    }

    public function mount(): void
    {
        if ($this->npub) {
            try {
                $this->credits = $this->creditsManager->getBalance($this->npub);
                $this->logger->info($this->credits);
            } catch (InvalidArgumentException $e) {
                $this->logger->error($e);
                $this->credits = $this->creditsManager->resetBalance($this->npub);
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    #[LiveAction]
    public function search(): void
    {
        $this->logger->info("Query: {$this->query}, npub: {$this->npub}");

        if (empty($this->query)) {
            $this->results = [];
            return;
        }

        try {
            $this->credits = $this->creditsManager->getBalance($this->npub);
        } catch (InvalidArgumentException $e) {
            $this->credits = $this->creditsManager->resetBalance($this->npub);
        }

        if (!$this->creditsManager->canAfford($this->npub, 1)) {
            $this->results = [];
            return;
        }

        $this->creditsManager->spendCredits($this->npub, 1, 'search');
        $this->credits--;

        $this->results = array_filter(
            $this->finder->find($this->query, 12),
            fn($r) => !str_contains($r->getSlug(), '/')
        );
    }

    #[LiveListener('creditsAdded')]
    public function incrementCreditsCount(array $data): void
    {
        $this->credits += $data['credits'];
    }

}
