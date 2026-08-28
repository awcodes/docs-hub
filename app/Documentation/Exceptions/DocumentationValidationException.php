<?php

declare(strict_types=1);

namespace App\Documentation\Exceptions;

use RuntimeException;

final class DocumentationValidationException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(PHP_EOL, $errors));
    }
}
