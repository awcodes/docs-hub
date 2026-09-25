<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_version_id')->constrained()->cascadeOnDelete();

            // Where the file sits in the repository, and where the reader finds
            // it. A page may set its own slug in front matter, so the two are
            // not derivable from each other.
            $table->string('source_path');
            $table->string('slug');

            $table->string('title');
            $table->text('description')->nullable();

            // `[{"level": 2, "title": "…", "anchor": "…"}]` — the table of
            // contents, so rendering one does not mean reparsing Markdown on
            // every request.
            $table->json('headings')->nullable();

            $table->string('content_hash');
            $table->timestamp('source_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['project_version_id', 'slug']);
            $table->unique(['project_version_id', 'source_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_pages');
    }
};
