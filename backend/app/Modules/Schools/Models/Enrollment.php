<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    use BelongsToTenant;

    protected $table = 'enrollments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_repeater' => 'boolean', 'enrolled_at' => 'date:Y-m-d', 'approved_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'grade_level_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(EnrollmentResult::class);
    }
}
