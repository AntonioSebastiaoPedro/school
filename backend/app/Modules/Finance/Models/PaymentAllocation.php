<?php

namespace App\Modules\Finance\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
