<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Models\Institution;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Schools\Models\SchoolYear;
use App\Modules\Schools\Models\Section;
use App\Modules\Schools\Services\SchoolProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Dados de demonstração para desenvolvimento local. Nunca em produção. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_if(app()->isProduction(), 1, 'DemoSeeder não corre em produção.');

        $this->call(GeographySeeder::class);

        User::firstOrCreate(['email' => 'admin@demo.ao'], ['name' => 'Administrador SaaS', 'password' => 'password', 'is_platform_admin' => true]);

        Plan::firstOrCreate(['code' => 'demo'], ['name' => 'Demonstração', 'price' => 0, 'billing_interval' => 'monthly']);

        $school = Institution::firstOrCreate(['slug' => 'escola-demo'], [
            'name' => 'Escola Demonstração', 'institution_type' => 'school',
            'approval_status' => 'approved', 'operational_status' => 'active',
        ]);

        $director = User::firstOrCreate(['email' => 'director@demo.ao'], ['name' => 'Director Demo', 'password' => 'password']);
        DB::table('institution_user')->updateOrInsert(
            ['institution_id' => $school->id, 'user_id' => $director->id],
            ['role' => 'director', 'status' => 'active'],
        );

        app(CurrentTenant::class)->runAs($school->id, function () use ($school) {
            if (DB::table('school_profiles')->where('institution_id', $school->id)->doesntExist()) {
                app(SchoolProvisioner::class)->provision($school, ['director_name' => 'Director Demo', 'director_gender' => 'M', 'regime' => 'public']);
            }

            $year = SchoolYear::firstOrCreate(['name' => '2026/2027'], ['starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'status' => 'active']);

            foreach ([0, 4, 6] as $grade) {
                Section::firstOrCreate(
                    ['school_year_id' => $year->id, 'grade_level_id' => $grade, 'name' => 'A'],
                    ['shift' => 'morning', 'capacity' => 40],
                );
            }
        });
    }
}
