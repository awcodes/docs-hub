<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageVersionStatus: string
{
    case Current = 'current';
    case Supported = 'supported';
    case Legacy = 'legacy';
    case Beta = 'beta';
}
