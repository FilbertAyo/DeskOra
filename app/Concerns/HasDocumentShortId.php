<?php

namespace App\Concerns;

use App\Services\Documents\DocumentShortIdGenerator;

trait HasDocumentShortId
{
    protected function initializeHasDocumentShortId(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasDocumentShortId(): void
    {
        static::creating(function ($model): void {
            $key = $model->getKeyName();
            $current = $model->getAttribute($key);

            if (! is_string($current) || $current === '') {
                $model->setAttribute($key, app(DocumentShortIdGenerator::class)->generateUnique());
            }
        });
    }
}
