<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolProfile extends Model
{
    use BelongsToTenant;

    protected $table = 'school_profiles';

    protected $primaryKey = 'institution_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_complex' => 'boolean'];
    }
}
