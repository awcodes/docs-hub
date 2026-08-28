<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Data\DocumentationCheckout;
use App\Documentation\Exceptions\DocumentationValidationException;
use App\Enums\DocumentationType;

final class LocalFilesystemDocumentationSource
{
    public function resolve(string $checkoutPath, string $docsPath = 'docs'): DocumentationCheckout
    {
        $repositoryRoot = realpath($checkoutPath);

        if ($repositoryRoot === false || ! is_dir($repositoryRoot)) {
            throw new DocumentationValidationException(["Documentation checkout [{$checkoutPath}] does not exist."]);
        }

        $docsPath = mb_trim($docsPath, '/');

        if ($docsPath === '' || str_contains($docsPath, '\\') || str_contains($docsPath, '..')) {
            throw new DocumentationValidationException(["Invalid documentation root [{$docsPath}]."]);
        }

        $documentationRoot = $repositoryRoot.'/'.$docsPath;

        if (is_link($documentationRoot)) {
            throw new DocumentationValidationException(['The documentation root may not be a symbolic link.']);
        }

        if (is_dir($documentationRoot)) {
            return new DocumentationCheckout(
                type: DocumentationType::Structured,
                repositoryRoot: $repositoryRoot,
                documentationRoot: $documentationRoot,
                manifestPath: $documentationRoot.'/docs.yml',
                readmePath: null,
            );
        }

        $readmePath = $repositoryRoot.'/README.md';

        if (is_file($readmePath) && ! is_link($readmePath)) {
            return new DocumentationCheckout(
                type: DocumentationType::Readme,
                repositoryRoot: $repositoryRoot,
                documentationRoot: null,
                manifestPath: null,
                readmePath: $readmePath,
            );
        }

        return new DocumentationCheckout(
            type: DocumentationType::None,
            repositoryRoot: $repositoryRoot,
            documentationRoot: null,
            manifestPath: null,
            readmePath: null,
        );
    }
}
