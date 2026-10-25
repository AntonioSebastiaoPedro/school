<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    use BelongsToTenant;

    protected $table = 'school_years';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'settings' => 'array'];
    }
}
