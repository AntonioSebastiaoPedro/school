<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:2'];
    }
}
