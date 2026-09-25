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
            $table->after('redirects', function (Blueprint $table): void {
                // The manifest's navigation, stored the way it was written:
                // order, groups and labels exist nowhere else. The page index
                // knows every page but not the sequence a reader moves through
                // them in, which is what a sidebar and previous/next both need.
                $table->json('navigation')->nullable();

                // `docs.yml`'s own title, kept only as a fallback. The registry
                // wins for anything a reader sees, because hub chrome must stay
                // consistent across versions whose manifests disagree (10.2).
                $table->string('manifest_title')->nullable();
            });
        });
    }

    public function down(): void
    {
        Schema::table('project_versions', function (Blueprint $table): void {
            $table->dropColumn(['navigation', 'manifest_title']);
        });
    }
};
