<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeSheetLock extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_sheet_locks';

    public $timestamps = false;

    protected $guarded = ['id'];
}
