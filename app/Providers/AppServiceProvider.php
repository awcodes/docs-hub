<?php

declare(strict_types=1);

namespace App\Providers;

use App\Documentation\Search\DatabaseDocumentationIndexer;
use App\Documentation\Search\DocumentationIndexer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(DocumentationIndexer::class, DatabaseDocumentationIndexer::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureDates();
        $this->configureUrls();
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureModels(): void
    {
        Model::unguard();
        Model::shouldBeStrict();
    }

    private function configureUrls(): void
    {
        URL::forceScheme('https');
    }
}
