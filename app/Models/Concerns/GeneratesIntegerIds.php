<?php

namespace App\Models\Concerns;

trait GeneratesIntegerIds
{
    protected static function bootGeneratesIntegerIds(): void
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();

            if ($model->{$keyName}) {
                return;
            }

            $model->{$keyName} = ((int) static::max($keyName)) + 1;
        });
    }
}
