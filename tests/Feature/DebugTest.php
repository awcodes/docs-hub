<?php

declare(strict_types=1);

arch('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each
    ->not
    ->toBeUsed();
