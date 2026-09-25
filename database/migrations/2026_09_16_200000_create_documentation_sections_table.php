<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentation_sections', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('documentation_page_id')->constrained()->cascadeOnDelete();

            // Denormalized from the page so that filtering to one project or
            // one version, and boosting by version status, are a join away
            // rather than two.
            $table->foreignId('project_version_id')->constrained()->cascadeOnDelete();

            // Null for the text before the first heading — a page's opening
            // paragraphs are a section like any other, they just have no anchor
            // to deep-link to.
            $table->string('heading')->nullable();
            $table->string('anchor')->nullable();
            $table->unsignedTinyInteger('level')->default(0);

            // Reading order within the page, so a result can say where in the
            // page it sits without re-parsing anything.
            $table->unsignedSmallInteger('position');

            $table->text('content');

            $table->timestamps();

            $table->index(['project_version_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentation_sections');
    }
};
