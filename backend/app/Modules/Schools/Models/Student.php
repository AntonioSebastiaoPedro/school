<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use BelongsToTenant;

    protected $table = 'students';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'document_issue_date' => 'date:Y-m-d', 'medical_info' => 'array'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
