<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Override;

abstract class TestCase extends BaseTestCase
{
    /**
     * CI does not build assets, and the panel's `->viteTheme()` resolves its
     * stylesheet through the Vite manifest — so without this, every test that
     * renders a panel page throws `ViteManifestNotFoundException` on CI while
     * passing on any machine that has run `npm run build`.
     *
     * Under `--parallel`, each process also gets its own storage directory.
     * Synchronization stages into `storage/app`, and tests clean it up
     * wholesale, so a shared directory would let one process delete another's
     * work mid-sync.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if (ParallelTesting::token() !== false) {
            $this->app->useStoragePath(scratchRoot().'/storage');
        }
    }
}
