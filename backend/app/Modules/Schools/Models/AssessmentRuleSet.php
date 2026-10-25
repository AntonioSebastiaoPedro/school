<?php

namespace App\Modules\Schools\Models;

use App\Modules\Platform\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AssessmentRuleSet extends Model
{
    use BelongsToTenant;

    protected $table = 'assessment_rules';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean'];
    }
}
