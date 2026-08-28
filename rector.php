<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/bootstrap/app.php',
            __DIR__.'/config',
            __DIR__.'/database',
            __DIR__.'/public',
            __DIR__.'/tests',
        ])
        ->withSets([
            PestSetList::CODING_STYLE,
        ])
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            typeDeclarations: true,
            privatization: true,
            earlyReturn: true,
        )
        ->withPhpSets();
} catch (InvalidConfigurationException $e) {
    echo 'Rector configuration error: '.$e->getMessage();
    exit(1);
}
