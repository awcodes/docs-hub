<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('package_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('git_ref');
            $table->string('status')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_attempted_sync_at')->nullable();
            $table->string('source_commit', 40)->nullable()->index();
            $table->string('documentation_type')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'version']);
            $table->index(['package_id', 'is_default']);
            $table->index(['package_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_versions');
    }
};
