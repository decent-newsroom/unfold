<?php

namespace App\Util\CommonMark;

use App\Util\Bech32\Bech32Decoder;
use App\Util\CommonMark\ImagesExtension\RawImageLinkExtension;
use App\Util\CommonMark\NostrSchemeExtension\NostrSchemeExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;

class Converter
{
    public function __construct(private readonly Bech32Decoder $bech32Decoder)
    {
    }

    /**
     * @throws CommonMarkException
     */
    public function convertToHTML(string $markdown): string
    {
        // Check if the article has more than three headings
        // Match all headings (from level 1 to 6)
        preg_match_all('/^#+\s.*$/m', $markdown, $matches);
        $headingsCount = count($matches[0]);


        // Configure the Environment with all the CommonMark parsers/renderers
        $config = [
            'table_of_contents' => [
                'min_heading_level' => 1,
                'max_heading_level' => 2,
            ],
            'heading_permalink' => [
                'symbol' => '§',
            ],
            'autolink' => [
                'allowed_protocols' => ['https'], // defaults to ['https', 'http', 'ftp']
                'default_protocol' => 'https', // defaults to 'http'
            ],
        ];
        $environment = new Environment($config);
        // Add the extensions
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new FootnoteExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        // create a custom extension, that handles nostr mentions
        $environment->addExtension(new NostrSchemeExtension($this->bech32Decoder));
        $environment->addExtension(new SmartPunctExtension());
        $environment->addExtension(new RawImageLinkExtension());
        $environment->addExtension(new AutolinkExtension());
        if ($headingsCount > 3) {
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new TableOfContentsExtension());
        }

        // Instantiate the converter engine and start converting some Markdown!
        $converter = new MarkdownConverter($environment);
        $content = html_entity_decode($markdown);

        return $converter->convert($content);
    }

}
