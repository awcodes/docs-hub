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
        Schema::create('documentation_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('documentation_page_id')->constrained()->cascadeOnDelete();
            $table->json('heading_path');
            $table->string('heading')->nullable();
            $table->string('anchor')->nullable();
            $table->longText('body');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['documentation_page_id', 'sort_order']);
            $table->unique(['documentation_page_id', 'anchor']);
            $table->index(['documentation_snapshot_id', 'documentation_page_id']);
            $table->index(['documentation_snapshot_id', 'heading']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_sections');
    }
};
