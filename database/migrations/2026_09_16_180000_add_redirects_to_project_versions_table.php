<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_versions', function (Blueprint $table): void {
            // The `redirects` map from `docs.yml`, stored beside the page index
            // rather than in a table of its own: it is owned by the repository,
            // rewritten wholesale on every sync, and only ever read for one
            // version at a time.
            $table->json('redirects')->nullable()->after('documentation_type');
        });
    }

    public function down(): void
    {
        Schema::table('project_versions', function (Blueprint $table): void {
            $table->dropColumn('redirects');
        });
    }
};
