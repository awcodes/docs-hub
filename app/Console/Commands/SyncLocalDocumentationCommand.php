<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Documentation\Actions\SyncLocalDocumentation;
use App\Models\Package;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('docs:sync-local {package : Package slug} {version : Registered package version} {checkout : Local repository checkout} {--commit= : Exact 40-character source commit SHA}')]
#[Description('Synchronize a registered package version from a local checkout')]
class SyncLocalDocumentationCommand extends Command
{
    public function handle(SyncLocalDocumentation $synchronizer): int
    {
        $packageSlug = (string) $this->argument('package');
        $versionName = (string) $this->argument('version');
        $checkoutPath = (string) $this->argument('checkout');
        $sourceCommit = (string) $this->option('commit');
        $package = Package::query()->where('slug', $packageSlug)->first();

        if ($package === null) {
            $this->error("Package [{$packageSlug}] is not registered.");

            return self::FAILURE;
        }

        $version = $package->versions()->where('version', $versionName)->first();

        if ($version === null) {
            $this->error("Version [{$versionName}] is not registered for package [{$packageSlug}].");

            return self::FAILURE;
        }

        try {
            $version = $synchronizer->handle($version, $checkoutPath, $sourceCommit);
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info("Published [{$packageSlug} {$versionName}] at commit [{$version->source_commit}].");

        return self::SUCCESS;
    }
}
