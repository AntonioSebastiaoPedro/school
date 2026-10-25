<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date:Y-m-d', 'issued_at' => 'datetime'];
    }
}
