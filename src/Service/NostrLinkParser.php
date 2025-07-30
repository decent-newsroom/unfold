<?php

namespace App\Service;

use nostriphant\NIP19\Bech32;
use Psr\Log\LoggerInterface;

readonly class NostrLinkParser
{
    private const NOSTR_LINK_PATTERN = '/(?:nostr:)?(nevent1[a-z0-9]+|naddr1[a-z0-9]+|nprofile1[a-z0-9]+|note1[a-z0-9]+|npub1[a-z0-9]+)/';


    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Parse content for Nostr links and return structured data
     *
     * @param string $content The content to parse
     * @return array Array of detected Nostr links with their type and decoded data
     */
    public function parseLinks(string $content): array
    {
        $links = [];

        // Improved regular expression to match all nostr: links
        // This will find all occurrences including multiple links in the same text
        if (preg_match_all(self::NOSTR_LINK_PATTERN, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $fullMatch = $match[0][0];
                $identifier = $match[1][0];
                $position = $match[0][1]; // Position in the text

                try {
                    $decoded = new Bech32($identifier);
                    $links[] = [
                        'type' => $decoded->type,
                        'identifier' => $identifier,
                        'full_match' => $fullMatch,
                        'position' => $position,
                        'data' => $decoded->data
                    ];
                } catch (\Exception $e) {
                    // If decoding fails, skip this identifier
                    $this->logger->info('Failed to decode Nostr identifier', [
                        'identifier' => $identifier,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            // Sort by position to maintain the original order in the text
            usort($links, fn($a, $b) => $a['position'] <=> $b['position']);
        }

        return $links;
    }

}
