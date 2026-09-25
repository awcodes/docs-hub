<?php

declare(strict_types=1);

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('kind')->default(ProjectKind::Package->value);
            $table->string('repository');
            $table->string('docs_path')->default('docs');
            $table->string('default_branch');
            $table->text('description')->nullable();
            $table->string('group');
            $table->string('versioning_mode')->default(VersioningMode::Versioned->value);

            // Hidden from the homepage and the selector, still reachable by
            // direct URL — which is what a project being set up needs.
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
