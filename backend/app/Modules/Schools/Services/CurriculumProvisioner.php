<?php

namespace App\Modules\Schools\Services;

use App\Modules\Schools\Models\GradeLevelSubject;
use App\Modules\Schools\Models\Subject;

/** Copia o currículo oficial (config/curriculum.php) para a instituição activa. Idempotente. */
class CurriculumProvisioner
{
    public function provision(): void
    {
        $order = 0;

        foreach (config('curriculum.subjects') as $code => [$name, $category, $credits, $hours, $grades, $nuclear]) {
            $subject = Subject::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'credits' => $credits, 'is_nuclear' => $nuclear],
            );

            $this->link($subject, $grades, $hours, ++$order);
        }

        foreach (config('curriculum.preschool_areas') as $code => [$name, $hours]) {
            $subject = Subject::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => 'core', 'credits' => 1, 'is_nuclear' => false],
            );

            $this->link($subject, [0], $hours, ++$order);
        }
    }

    /** @param list<int> $grades */
    private function link(Subject $subject, array $grades, int $hours, int $order): void
    {
        foreach ($grades as $grade) {
            GradeLevelSubject::firstOrCreate(
                ['grade_level_id' => $grade, 'subject_id' => $subject->id],
                ['weekly_hours' => $hours, 'display_order' => $order],
            );
        }
    }
}
