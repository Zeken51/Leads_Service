<?php

namespace App\Domain\Tenants;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isSet()) {
            $builder->where($model->getTable().'.tenant_id', TenantContext::getId());
        }
    }
}
