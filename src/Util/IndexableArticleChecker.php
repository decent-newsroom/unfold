<?php

namespace App\Util;

use App\Entity\Article;
use App\Enum\IndexStatusEnum;

class IndexableArticleChecker
{
    public static function isIndexable(Article $article): bool
    {
        return $article->getIndexStatus() !== IndexStatusEnum::DO_NOT_INDEX;
    }
}
