<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolLink extends Model
{
    use BelongsToTenant;

    protected $table = 'school_links';

    protected $guarded = ['id'];
}
