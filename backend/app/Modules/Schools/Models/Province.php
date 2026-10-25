<?php

namespace App\Modules\Schools\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $table = 'provinces';

    public $timestamps = false;

    protected $guarded = ['id'];
}
