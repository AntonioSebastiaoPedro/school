<?php

namespace App\Modules\Platform\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * Isola o modelo por institution_id. Falha fechada: sem tenant activo, a consulta
 * lança excepção em vez de devolver dados de todas as instituições.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->getModel()->getTable().'.institution_id', app(CurrentTenant::class)->id());
        });

        // O valor enviado pelo cliente nunca decide a que instituição o registo pertence.
        static::creating(function ($model) {
            $model->institution_id = app(CurrentTenant::class)->id();
        });
    }
}
