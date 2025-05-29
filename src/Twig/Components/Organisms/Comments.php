<?php

namespace App\Twig\Components\Organisms;

use App\Service\NostrClient;
use App\Service\NostrLinkParser;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Comments
{
    public array $list = [];
    public array $commentLinks = [];
    public array $processedContent = [];

    public function __construct(
        private readonly NostrClient $nostrClient,
        private readonly NostrLinkParser $nostrLinkParser

    ) {
    }

    /**
     * @throws \Exception
     */
    public function mount($current): void
    {
        // Fetch comments
        $this->list = $this->nostrClient->getComments($current);

        // Parse Nostr links in comments but don't fetch previews
        $this->parseNostrLinks();
    }

    /**
     * Parse Nostr links in comments for client-side loading
     */
    private function parseNostrLinks(): void
    {
        foreach ($this->list as $comment) {
            $content = $comment->content ?? '';
            if (empty($content)) {
                continue;
            }

            // Store the original content
            $this->processedContent[$comment->id] = $content;

            // Parse the content for Nostr links
            $links = $this->nostrLinkParser->parseLinks($content);

            if (!empty($links)) {
                // Save the links for the client-side to fetch
                $this->commentLinks[$comment->id] = $links;
            }
        }
    }
}
