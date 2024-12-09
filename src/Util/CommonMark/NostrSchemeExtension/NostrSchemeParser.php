<?php

namespace App\Util\CommonMark\NostrSchemeExtension;

use App\Util\Bech32\Bech32Decoder;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use function BitWasp\Bech32\convertBits;
use function BitWasp\Bech32\decode;


class NostrSchemeParser  implements InlineParserInterface
{

    public function __construct(private Bech32Decoder $bech32Decoder)
    {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('nostr:[0-9a-zA-Z]+');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();
        // Get the match and extract relevant parts
        $fullMatch = $inlineContext->getFullMatch();
        // The match is a Bech32 encoded string
        // decode it to get the parts
        $bechEncoded = substr($fullMatch, 6);  // Extract the part after "nostr:", i.e., "XXXX"
        dump($bechEncoded);

        try {
            list($hrp, $tlv) = $this->bech32Decoder->decodeAndParseNostrBech32($bechEncoded);
            dump($hrp);
            dump($tlv);
            switch ($hrp) {
                case 'npub':
                    $str = '';
                    list($hrp, $data) = decode($bechEncoded);
                    $bytes = convertBits($data, count($data), 5, 8, false);
                    foreach ($bytes as $item) {
                        $str .= str_pad(dechex($item), 2, '0', STR_PAD_LEFT);
                    }
                    $npubPart = $str;
                    $inlineContext->getContainer()->appendChild(new NostrMentionLink(null, $npubPart));
                    break;
                case 'nprofile':
                    $type = 0; // npub
                    foreach ($tlv as $item) {
                        if ($item['type'] === $type) {
                            // from array of integers to string
                            $str = '';
                            foreach ($item['value'] as $byte) {
                                $str .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
                            }
                            $npubPart = $str;
                            break;
                        }
                    }
                    if (isset($npubPart)) {
                        $inlineContext->getContainer()->appendChild(new NostrMentionLink(null, $npubPart));
                    }
                    break;
                case 'nevent':
                    foreach ($tlv as $item) {
                        // event id
                        if ($item['type'] === 0) {
                            $eventId = implode('', array_map(fn($byte) => sprintf('%02x', $byte), $item['value']));
                            break;
                        }
                        // relays
                        if ($item['type'] === 1) {
                            $relays[] = implode('', array_map('chr', $item['value']));
                        }
                    }
                    dump($relays ?? null);
                    // TODO also potentially contains relays, author, and kind
                    $inlineContext->getContainer()->appendChild(new NostrSchemeData('nevent', $eventId, $relays ?? null, null, null));
                    break;
                case 'naddr':
                    $inlineContext->getContainer()->appendChild(new NostrSchemeData('naddr', $bechEncoded, null, null, null));
                    break;
                case 'nrelay':
                    // deprecated
                default:
                    return false;
            }

        } catch (\Exception $e) {
            dump($e->getMessage());
            return false;
        }

        // Advance the cursor to consume the matched part (important!)
        $cursor->advanceBy(strlen($fullMatch));

        return true;
    }
}
