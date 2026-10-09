<?php
declare(strict_types=1);
namespace App\Application\Academic;
final class AcademicCommandFailure extends \RuntimeException
{
    public function __construct(public readonly string $category){parent::__construct($category);}
}
