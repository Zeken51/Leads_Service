<?php

namespace App\Domain\Concerns;

use App\Domain\Tenants\TenantContext;
use App\Domain\Tenants\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        // `Model` como tipo para evitar ambigüedad de `self` en closures de traits
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && TenantContext::isSet()) {
                $model->tenant_id = TenantContext::getId();
            }
        });
    }
}
