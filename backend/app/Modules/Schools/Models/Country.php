<?php

namespace App\Modules\Schools\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

    public $timestamps = false;

    protected $guarded = ['id'];
}
