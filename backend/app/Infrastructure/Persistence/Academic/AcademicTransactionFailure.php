<?php
declare(strict_types=1);
namespace App\Infrastructure\Persistence\Academic;
use RuntimeException;
use Throwable;

final class AcademicTransactionFailure extends RuntimeException
{
    public function __construct(public readonly string $category,
        public readonly ?string $correlationId=null, ?Throwable $previous=null)
    {
        parent::__construct($category,0,$previous);
    }
}
