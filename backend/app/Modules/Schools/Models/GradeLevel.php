<?php

namespace App\Modules\Schools\Models;

use App\Modules\Schools\Assessment\Domain\GradeScale;
use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    protected $table = 'grade_levels';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['has_national_exam' => 'boolean'];
    }

    public function scale(): GradeScale
    {
        return new GradeScale($this->max_score, $this->pass_mark);
    }

    public function hasGrades(): bool
    {
        return $this->stage !== 'preschool';
    }
}
