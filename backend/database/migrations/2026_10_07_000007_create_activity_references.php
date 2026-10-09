<?php
declare(strict_types=1);
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up():void
    {
        DB::schema()->create('activity_references',function(Blueprint $table){
            $table->id();$table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::connection()->unprepared("CREATE TRIGGER activity_reference_no_update BEFORE UPDATE ON activity_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Original activity reference is immutable'");
        DB::connection()->unprepared("CREATE TRIGGER activity_reference_no_delete BEFORE DELETE ON activity_references FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retain original activity reference'");
    }
    public function down():void {throw new RuntimeException('Preserve original references; use reviewed forward repair.');}
};
