<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function isSchool(): bool
    {
        return in_array($this->institution_type, ['school', 'both'], true);
    }
}
