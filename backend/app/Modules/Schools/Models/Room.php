<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use BelongsToTenant;

    protected $table = 'rooms';

    protected $guarded = ['id'];
}
