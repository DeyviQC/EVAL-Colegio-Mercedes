<?php
declare(strict_types=1);
namespace App\Application\Academic;
use App\Infrastructure\Persistence\Academic\LockedAcademicContext;

/** Pure locked-row prerequisite validation; creates no academic records and grants no authority. */
final class CatalogReferenceValidation
{
    public static function assertActive(LockedAcademicContext $context,int|string $grade,int|string $section,int|string|null $entry=null):void
    {
        $g=$context->row('grades',$grade);$s=$context->row('sections',$section);
        if((string)$s['grade_id']!==(string)$g['id']){throw new AcademicCommandFailure('scope_mismatch');}
        $rows=[$g,$s];if($entry!==null){$rows[]=$context->row('instructional_entries',$entry);}
        foreach($rows as $row){if(!(bool)$row['is_active']){throw new AcademicCommandFailure('inactive_catalog_reference');}}
    }
}
