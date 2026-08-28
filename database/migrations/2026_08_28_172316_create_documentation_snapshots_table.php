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
        Schema::create('documentation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_version_id')->constrained()->cascadeOnDelete();
            $table->string('source_commit', 40);
            $table->string('storage_disk');
            $table->string('storage_prefix')->unique();
            $table->string('documentation_type');
            $table->string('state')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['package_version_id', 'source_commit']);
            $table->index(['package_version_id', 'state', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_snapshots');
    }
};
