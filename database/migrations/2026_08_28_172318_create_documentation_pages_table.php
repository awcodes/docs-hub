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
        Schema::create('documentation_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('source_path');
            $table->string('slug')->nullable();
            $table->string('route_path');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('headings');
            $table->string('content_hash', 64);
            $table->boolean('is_listed')->default(false);
            $table->unsignedInteger('navigation_order')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['documentation_snapshot_id', 'source_path']);
            $table->unique(['documentation_snapshot_id', 'route_path']);
            $table->index(['documentation_snapshot_id', 'is_listed', 'navigation_order'], 'documentation_pages_navigation_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_pages');
    }
};
