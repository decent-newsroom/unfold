<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class CommaSeparatedToJsonTransformer implements DataTransformerInterface
{

    /**
     *  Transforms an array to a comma-separated string.
     *  @inheritDoc
     */
    public function transform(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        $array = json_decode($value, true);

        return implode(',', $array);
    }

    /**
     * Transforms a comma-separated string to an array.
     * @inheritDoc
     */
    public function reverseTransform(mixed $value): mixed
    {
        if (!$value) {
            return json_encode([]);
        }

        $array = array_map('trim', explode(',', $value));

        return json_encode($array);
    }
}