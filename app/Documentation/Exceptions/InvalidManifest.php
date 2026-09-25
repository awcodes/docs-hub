<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use App\Documentation\Data\Manifest;
use RuntimeException;

/**
 * A `docs.yml` that cannot become a `Manifest`.
 *
 * Thrown rather than collected because there is no partial answer to give: a
 * manifest that will not parse has no navigation to check references against.
 * The validator catches this and reports it as the first and only error for
 * that file.
 */
final class InvalidManifest extends RuntimeException
{
    public static function unreadableYaml(string $reason): self
    {
        return new self("The manifest is not valid YAML: {$reason}");
    }

    public static function notAMapping(): self
    {
        return new self('The manifest must be a YAML mapping.');
    }

    public static function unsupportedSchemaVersion(mixed $version): self
    {
        $found = is_scalar($version) ? var_export($version, true) : gettype($version);

        return new self(
            "Manifest schema version {$found} is not supported; this hub understands version "
            .Manifest::SCHEMA_VERSION.'.',
        );
    }

    public static function navigationMissing(): self
    {
        return new self('The manifest has no `navigation` list.');
    }

    public static function navigationNotAList(): self
    {
        return new self('`navigation` must be a list of entries.');
    }

    public static function unrecognizedEntry(int $position): self
    {
        return new self(
            "Navigation entry {$position} is neither a page reference nor a group with `label` and `children`.",
        );
    }

    public static function groupWithoutChildren(string $label): self
    {
        return new self("Navigation group `{$label}` has no `children` list.");
    }

    public static function nestedGroup(string $label): self
    {
        return new self(
            "Navigation group `{$label}` contains another group. Groups do not nest.",
        );
    }

    public static function referenceWithExtension(string $reference): self
    {
        return new self(
            "Page reference `{$reference}` carries a file extension. References are paths without `.md`.",
        );
    }

    public static function redirectsNotAMap(): self
    {
        return new self('`redirects` must be a mapping of old page reference to new page reference.');
    }

    public static function redirectNotAString(string $key): self
    {
        return new self("Redirect `{$key}` must point at a page reference.");
    }
}
