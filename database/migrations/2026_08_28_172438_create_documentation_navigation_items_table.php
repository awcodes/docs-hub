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
        Schema::create('documentation_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('documentation_navigation_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('route_path')->nullable();
            $table->unsignedSmallInteger('depth');
            $table->unsignedInteger('sort_order');
            $table->string('position_path');
            $table->timestamps();

            $table->unique(['documentation_snapshot_id', 'position_path']);
            $table->index(['documentation_snapshot_id', 'parent_id', 'sort_order'], 'documentation_navigation_parent_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_navigation_items');
    }
};
