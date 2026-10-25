<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StudentTransfer extends Model
{
    use BelongsToTenant;

    protected $table = 'student_transfers';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date:Y-m-d'];
    }
}
