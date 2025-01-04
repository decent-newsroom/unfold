<?php

namespace App\Form\DataTransformer;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class HtmlToMdTransformer implements DataTransformerInterface
{

    private $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter();
    }

    /**
     * Transforms Markdown into HTML (for displaying in the form).
     *  @inheritDoc
     */
    public function transform(mixed $value): mixed
    {
        dump($value);
        if ($value === null) {
            return '';
        }

        // Optional: You can add a markdown-to-html conversion if needed
        return $value; // You could return rendered markdown here.
    }

    /**
     * Transforms a HTML string to Markdown.
     * @inheritDoc
     */
    public function reverseTransform(mixed $value): mixed
    {
        dump($value);
        if (!$value) {
            return '';
        }

        try {
            // Convert HTML to Markdown
            return $this->converter->convert($value);
        } catch (\Exception $e) {
            throw new TransformationFailedException('Failed to convert HTML to Markdown');
        }
    }
}
