<?php

namespace Database\Seeders;

use App\Academic\AcademicService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcademicDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('academic_periods')->exists()) {
            $this->command?->warn('Academic data exists; the seed does not overwrite retained history.');

            return;
        }
        $people = [
            ['Carmen Flores', 'director@eval.test', 'director'],
            ['Elena Ramos', 'subdirector@eval.test', 'subdirector'],
            ['María Huamán', 'docente@eval.test', 'docente'],
            ['José Medina', 'comunicacion@eval.test', 'docente'],
            ['Rosa Flores', 'ciencia@eval.test', 'docente'],
            ['Ana Torres', 'sociales@eval.test', 'docente'],
            ['Elena Castro', 'ingles@eval.test', 'docente'],
            ['Patricia Ramos', 'arte@eval.test', 'docente'],
        ];
        $ids = [];
        foreach ($people as [$name, $email, $role]) {
            $ids[$email] = DB::table('users')->insertGetId(['name' => $name, 'email' => $email, 'role' => $role, 'password' => Hash::make('Mercedes2026!'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $actor = $ids['director@eval.test'];
        $service = app(AcademicService::class);
        $period = $service->execute($actor, 'create_period', ['name' => 'Año escolar '.now()->year, 'start_on' => now()->startOfYear()->toDateString(), 'end_on' => now()->endOfYear()->toDateString()]);
        $service->execute($actor, 'activate_period', ['id' => $period]);
        $entries = [];
        foreach (['Matemática' => 'docente', 'Comunicación' => 'comunicacion', 'Ciencia y Tecnología' => 'ciencia', 'Ciencias Sociales' => 'sociales', 'Inglés' => 'ingles', 'Arte y Cultura' => 'arte'] as $name => $email) {
            $entries[] = ['id' => $service->execute($actor, 'create_catalog', ['type' => 'entry', 'name' => $name, 'classification' => 'area']), 'teacher_id' => $ids[$email.'@eval.test']];
        }
        for ($gradeNumber = 1; $gradeNumber <= 5; $gradeNumber++) {
            $grade = $service->execute($actor, 'create_catalog', ['type' => 'grade', 'name' => $gradeNumber.'° de secundaria']);
            foreach (['A', 'B', 'C', 'D', 'E'] as $sectionName) {
                $section = $service->execute($actor, 'create_catalog', ['type' => 'section', 'name' => $sectionName, 'grade_id' => $grade]);
                $scope = ['academic_period_id' => $period, 'grade_id' => $grade, 'section_id' => $section];
                for ($number = 1; $number <= 10; $number++) {
                    $email = ($gradeNumber === 3 && $sectionName === 'A' && $number === 1) ? 'estudiante@eval.test' : 'alumna.'.$gradeNumber.strtolower($sectionName).'.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'@eval.test';
                    $student = DB::table('users')->insertGetId(['name' => 'Estudiante '.$gradeNumber.'° '.$sectionName.' · '.$number, 'email' => $email, 'role' => 'estudiante', 'password' => Hash::make('Mercedes2026!'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $service->execute($actor, 'enroll', $scope + ['student_id' => $student]);
                }
                foreach ($entries as $entry) {
                    $assignment = $service->execute($actor, 'create_assignment', $scope + ['instructional_entry_id' => $entry['id'], 'teacher_id' => $entry['teacher_id']]);
                    $service->execute($actor, 'activate_assignment', ['id' => $assignment]);
                }
            }
        }
        $this->command?->info('Created 5 grades, 25 sections, 250 enrollments and 150 independent assignments. Legacy records were not altered.');
    }
}
