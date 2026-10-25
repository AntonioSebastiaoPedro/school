<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use BelongsToTenant;

    protected $table = 'grades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['value' => 'float'];
    }
}
