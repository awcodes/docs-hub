<?php

declare(strict_types=1);

namespace App\Documentation\Validation;

use App\Documentation\Data\DocumentationManifest;
use App\Documentation\Data\DocumentationNavigationItem;
use App\Documentation\Data\DocumentationPageMetadata;
use App\Documentation\Data\ValidatedDocumentationCheckout;
use App\Documentation\Exceptions\DocumentationValidationException;
use App\Documentation\Sources\LocalFilesystemDocumentationSource;
use App\Documentation\Support\DocumentationPathResolver;
use App\Enums\DocumentationType;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class ValidateDocumentationCheckout
{
    public function __construct(
        private LocalFilesystemDocumentationSource $source,
        private DocumentationManifestParser $manifestParser,
        private MarkdownFrontMatterParser $frontMatterParser,
        private DocumentationPathResolver $pathResolver,
    ) {}

    public function handle(string $checkoutPath, string $docsPath = 'docs'): ValidatedDocumentationCheckout
    {
        $checkout = $this->source->resolve($checkoutPath, $docsPath);

        if ($checkout->type === DocumentationType::None) {
            return new ValidatedDocumentationCheckout($checkout, null, []);
        }

        if ($checkout->type === DocumentationType::Readme) {
            $metadata = $this->frontMatterParser->parseFile($checkout->readmePath ?? '');

            return new ValidatedDocumentationCheckout($checkout, null, ['' => $metadata]);
        }

        $documentationRoot = $checkout->documentationRoot;
        $manifestPath = $checkout->manifestPath;

        if ($documentationRoot === null || $manifestPath === null) {
            throw new DocumentationValidationException(['Structured documentation is missing its resolved root or manifest path.']);
        }

        $manifest = $this->manifestParser->parseFile($manifestPath);
        $pages = $this->discoverPages($documentationRoot);
        $this->validateNavigation($manifest, $pages);
        $this->validateRedirects($manifest, $pages);

        return new ValidatedDocumentationCheckout($checkout, $manifest, $pages);
    }

    /** @return array<string, DocumentationPageMetadata> */
    private function discoverPages(string $documentationRoot): array
    {
        $pages = [];
        $sourceCollisionKeys = [];
        $routeCollisionKeys = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($documentationRoot));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                throw new DocumentationValidationException(["Symbolic links are not allowed in documentation [{$file->getPathname()}]."]);
            }

            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = str_replace($documentationRoot.'/', '', $file->getPathname());
            $sourceReference = $this->pathResolver->pageReference($relativePath);
            $metadata = $this->frontMatterParser->parseFile($file->getPathname());
            $routePath = $this->pathResolver->routePath($sourceReference, $metadata->slug);
            $sourceCollisionKey = $this->pathResolver->collisionKey($sourceReference);
            $routeCollisionKey = $this->pathResolver->collisionKey($routePath);

            if (isset($sourceCollisionKeys[$sourceCollisionKey])) {
                throw new DocumentationValidationException(["Documentation source path collision between [{$sourceCollisionKeys[$sourceCollisionKey]}] and [{$sourceReference}]."]);
            }

            if (isset($routeCollisionKeys[$routeCollisionKey])) {
                throw new DocumentationValidationException(["Documentation route collision between [{$routeCollisionKeys[$routeCollisionKey]}] and [{$routePath}]."]);
            }

            $sourceCollisionKeys[$sourceCollisionKey] = $sourceReference;
            $routeCollisionKeys[$routeCollisionKey] = $routePath;
            $pages[$sourceReference] = $metadata;
        }

        return $pages;
    }

    /** @param array<string, DocumentationPageMetadata> $pages */
    private function validateNavigation(DocumentationManifest $manifest, array $pages): void
    {
        $seen = [];

        foreach ($manifest->navigation as $item) {
            foreach ($this->pageReferences($item) as $pageReference) {
                if (! array_key_exists($pageReference, $pages)) {
                    throw new DocumentationValidationException(["Navigation references missing page [{$pageReference}]."]);
                }

                if (isset($seen[$pageReference])) {
                    throw new DocumentationValidationException(["Navigation references page [{$pageReference}] more than once."]);
                }

                $seen[$pageReference] = true;
            }
        }
    }

    /** @param array<string, DocumentationPageMetadata> $pages */
    private function validateRedirects(DocumentationManifest $manifest, array $pages): void
    {
        $routePaths = [];

        foreach ($pages as $sourceReference => $metadata) {
            $routePaths[$this->pathResolver->routePath($sourceReference, $metadata->slug)] = true;
        }

        foreach ($manifest->redirects as $source => $destination) {
            if ($source === $destination) {
                throw new DocumentationValidationException(["Redirect [{$source}] may not target itself."]);
            }

            $visited = [$source => true];
            $target = $destination;

            while (isset($manifest->redirects[$target])) {
                if (isset($visited[$target])) {
                    throw new DocumentationValidationException(["Redirect cycle detected from [{$source}]."]);
                }

                $visited[$target] = true;
                $target = $manifest->redirects[$target];
            }

            if (! isset($routePaths[$target])) {
                throw new DocumentationValidationException(["Redirect [{$source}] targets missing page [{$target}]."]);
            }
        }
    }

    /** @return list<string> */
    private function pageReferences(DocumentationNavigationItem $item): array
    {
        if ($item->pageReference !== null) {
            return [$item->pageReference];
        }

        $references = [];

        foreach ($item->children as $child) {
            if ($child->pageReference !== null) {
                $references[] = $child->pageReference;
            }
        }

        return $references;
    }
}
