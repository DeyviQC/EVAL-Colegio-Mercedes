<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use Normalizer;

/**
 * Generates canonical academic name keys conforming to the approved product equality rules:
 * - Case-insensitive: converts to lowercase.
 * - Trims outer whitespace.
 * - Unicode Normalization Form C (NFC): normalizes decomposed characters to precomposed.
 * - Distinguishes accents: 'álgebra' !== 'algebra'.
 * - Preserves visible label separately from comparison key.
 */
final readonly class AcademicNameKey
{
    public static function generate(string $name): string
    {
        $trimmed = trim($name);
        $normalized = Normalizer::normalize($trimmed, Normalizer::FORM_C);
        $result = $normalized !== false ? $normalized : $trimmed;

        return mb_strtolower($result, 'UTF-8');
    }
}
