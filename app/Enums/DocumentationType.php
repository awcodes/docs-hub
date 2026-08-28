<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentationType: string
{
    case Structured = 'structured';
    case Readme = 'readme';
    case None = 'none';
}
