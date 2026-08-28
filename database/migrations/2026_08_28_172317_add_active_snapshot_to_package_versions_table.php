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
        Schema::table('package_versions', function (Blueprint $table): void {
            $table->foreignId('active_snapshot_id')
                ->nullable()
                ->constrained('documentation_snapshots')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_snapshot_id');
        });
    }
};
