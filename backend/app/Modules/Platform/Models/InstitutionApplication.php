<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionApplication extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'submitted_documents' => 'array',
            'school_profile' => 'array',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }
}
