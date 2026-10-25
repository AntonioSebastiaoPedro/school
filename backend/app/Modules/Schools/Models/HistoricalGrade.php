<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class HistoricalGrade extends Model
{
    use BelongsToTenant;

    protected $table = 'historical_grades';

    public $timestamps = false;

    protected $guarded = ['id'];
}
