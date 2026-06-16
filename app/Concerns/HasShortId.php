<?php

namespace App\Concerns;

use App\Services\ShortIdGenerator;

trait HasShortId
{
    protected function initializeHasShortId(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasShortId(): void
    {
        static::creating(function ($model): void {
            $key = $model->getKeyName();
            $current = $model->getAttribute($key);

            if (! is_string($current) || $current === '') {
                $model->setAttribute($key, app(ShortIdGenerator::class)->generateUnique());
            }
        });
    }
}
