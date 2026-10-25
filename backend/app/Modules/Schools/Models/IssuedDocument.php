<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IssuedDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'issued_documents';

    public $timestamps = false;

    protected $guarded = ['id'];
}
