<?php
declare(strict_types=1);
namespace Tests\Unit\Academic;
use App\Domain\Academic\AcademicNameKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AcademicNameKeyTest extends TestCase
{
    public function testOuterUnicodeWhitespaceAndNfcCaseEquivalence(): void
    {
        foreach ([" a\u{0301}LGEBRA ", "\u{00A0}ÁLGEBRA\u{2003}", "\tálgebra\r\n", "\u{0085}Álgebra\u{2028}"] as $name) {
            $this->assertSame('álgebra', AcademicNameKey::generate($name));
        }
    }
    public function testFullCaseFoldAndAccentDistinction(): void
    {
        $this->assertSame(AcademicNameKey::generate('STRASSE'), AcademicNameKey::generate('Straße'));
        $this->assertSame(AcademicNameKey::generate('Σ'), AcademicNameKey::generate('ς'));
        $this->assertNotSame(AcademicNameKey::generate('Álgebra'), AcademicNameKey::generate('Algebra'));
        $this->assertNotSame(AcademicNameKey::generate('Grade  1'), AcademicNameKey::generate('Grade 1'));
    }
    public function testComparisonDoesNotRewriteVisibleLabel(): void
    {
        $label = " a\u{0301}LGEBRA ";
        $before = $label;
        AcademicNameKey::generate($label);
        $this->assertSame($before, $label);
    }
    public function testInvalidUtf8IsRejectedRatherThanSilentlyCompared(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AcademicNameKey::generate("\xFF");
    }
}
