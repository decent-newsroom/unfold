<?php

namespace App\Enum;

enum IndexStatusEnum: int
{
    case NOT_INDEXED = 0;
    case TO_BE_INDEXED = 1;
    case INDEXED = 2;
    case DO_NOT_INDEX = 3;
}
