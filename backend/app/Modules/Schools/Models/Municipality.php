<?php

namespace App\Modules\Schools\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $table = 'municipalities';

    public $timestamps = false;

    protected $guarded = ['id'];
}
