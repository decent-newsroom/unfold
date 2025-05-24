<?php

namespace App\Twig\Components;

use App\Credits\Service\CreditsManager;
use FOS\ElasticaBundle\Finder\FinderInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MultiMatch;

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

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp]
    public int $resultsPerPage = 12;

    public function __construct(
        private readonly FinderInterface $finder,
        private readonly CreditsManager $creditsManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache
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

        try {
            $this->results = [];
            $this->creditsManager->spendCredits($this->npub, 1, 'search');
            $this->credits--;

            // Create an optimized query using collapse correctly
            $mainQuery = new Query();

            // Build multi-match query for searching across fields
            $multiMatch = new MultiMatch();
            $multiMatch->setQuery($this->query);
            $multiMatch->setFields([
                'title^3',
                'summary^2',
                'content^1.5',
                'topics'
            ]);
            $multiMatch->setType(MultiMatch::TYPE_MOST_FIELDS);
            $multiMatch->setFuzziness('AUTO');

            $boolQuery = new BoolQuery();
            $boolQuery->addMust($multiMatch);
            $boolQuery->addMustNot(new Query\Wildcard('slug', '*/*'));

            // For text fields, we need to use a different approach
            // Create a regexp query that matches content with at least 250 chars
            // This is a simplification - actually matches content with enough words
            $lengthFilter = new Query\QueryString();
            $lengthFilter->setQuery('content:/.{250,}/');
            // $boolQuery->addFilter($lengthFilter);

            $mainQuery->setQuery($boolQuery);

            // Use the collapse field directly in the array format
            // This fixes the [collapse] failed to parse field [inner_hits] error
            $mainQuery->setParam('collapse', [
                'field' => 'slug',
                'inner_hits' => [
                    'name' => 'latest_articles',
                    'size' => 1 // Show more related articles
                ]
            ]);

            // Reduce the minimum score threshold to include more results
            $mainQuery->setMinScore(0.1); // Lower minimum score

            // Sort by score and createdAt
            $mainQuery->setSort([
                '_score' => ['order' => 'desc'],
                'createdAt' => ['order' => 'desc']
            ]);

            // Add pagination
            $offset = ($this->page - 1) * $this->resultsPerPage;
            $mainQuery->setFrom($offset);
            $mainQuery->setSize($this->resultsPerPage);

            // Execute the search
            $results = $this->finder->find($mainQuery);
            $this->logger->info('Search results count: ' . count($results));
            $this->logger->info('Search results: ',  ['results' => $results]);

            $this->results = $results;
        } catch (\Exception $e) {
            $this->logger->error('Search error: ' . $e->getMessage());
            $this->results = [];
        }
    }

    #[LiveListener('creditsAdded')]
    public function incrementCreditsCount(): void
    {
        $this->credits += 5;
    }

}
