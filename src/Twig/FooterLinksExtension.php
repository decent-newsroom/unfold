<?php

namespace App\Twig;

use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class FooterLinksExtension extends AbstractExtension implements GlobalsInterface
{
    private array $footerLinks;

    public function __construct(string $footerLinksPath)
    {
        $config = Yaml::parseFile($footerLinksPath);
        $this->footerLinks = $config['parameters']['external_links'] ?? [];
    }

    public function getGlobals(): array
    {
        return [
            'footer_links' => $this->footerLinks,
        ];
    }
}
