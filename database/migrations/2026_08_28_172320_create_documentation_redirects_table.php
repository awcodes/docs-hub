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
        Schema::create('documentation_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documentation_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('source_route');
            $table->string('destination_route');
            $table->timestamps();

            $table->unique(['documentation_snapshot_id', 'source_route']);
            $table->index(['documentation_snapshot_id', 'destination_route']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentation_redirects');
    }
};
