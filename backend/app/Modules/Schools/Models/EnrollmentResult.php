<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EnrollmentResult extends Model
{
    use BelongsToTenant;

    protected $table = 'enrollment_results';

    public $timestamps = false;

    protected $guarded = ['id'];
}
