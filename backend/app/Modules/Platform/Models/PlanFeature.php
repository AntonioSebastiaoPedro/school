<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'config' => 'array'];
    }
}
