<?php

declare(strict_types=1);

namespace App\Domain\Academic;

use Normalizer;
use InvalidArgumentException;

/**
 * Generates canonical academic name keys conforming to the approved product equality rules:
 * - Locale-independent full Unicode case folding.
 * - Trims Unicode White_Space only at the outer boundaries.
 * - Unicode Normalization Form C (NFC): normalizes decomposed characters to precomposed.
 * - Distinguishes accents: 'álgebra' !== 'algebra'.
 * - Preserves visible label separately from comparison key.
 */
final readonly class AcademicNameKey
{
    public static function generate(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidArgumentException('Academic name must be valid UTF-8.');
        }
        $trimmed = preg_replace('/^[\p{Z}\x{0009}-\x{000D}\x{0085}]+|[\p{Z}\x{0009}-\x{000D}\x{0085}]+$/u', '', $name);
        $normalized = Normalizer::normalize($trimmed, Normalizer::FORM_C);
        if ($normalized === false) { throw new InvalidArgumentException('Academic name normalization failed.'); }
        $key = Normalizer::normalize(mb_convert_case($normalized, MB_CASE_FOLD, 'UTF-8'), Normalizer::FORM_C);
        if ($key === false) { throw new InvalidArgumentException('Academic name normalization failed.'); }
        return $key;
    }
}
