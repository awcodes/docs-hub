<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentationSnapshotState: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Active = 'active';
    case Superseded = 'superseded';
}
