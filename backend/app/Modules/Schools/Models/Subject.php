<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use BelongsToTenant;

    protected $table = 'subjects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_nuclear' => 'boolean'];
    }
}
