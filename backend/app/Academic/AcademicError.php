<?php

namespace App\Academic;

use RuntimeException;

class AcademicError extends RuntimeException
{
    public function __construct(public readonly string $category, string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
