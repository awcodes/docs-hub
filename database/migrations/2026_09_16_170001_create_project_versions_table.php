<?php

declare(strict_types=1);

use App\Enums\DocumentationType;
use App\Enums\VersionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // What the reader sees in the URL (`1.x`), and the ref it is read
            // from (`1.x`). Usually identical, but a version pinned to a tag
            // makes them differ, so they are stored apart.
            $table->string('version');
            $table->string('git_ref');

            $table->string('status')->default(VersionStatus::Current->value);
            $table->boolean('is_default')->default(false);

            // Written by synchronization, not by the registry.
            $table->timestamp('last_synced_at')->nullable();
            $table->string('source_commit')->nullable();
            $table->string('active_snapshot')->nullable();
            $table->string('documentation_type')->default(DocumentationType::None->value);

            $table->timestamps();

            $table->unique(['project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_versions');
    }
};
