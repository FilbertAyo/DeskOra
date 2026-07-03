<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anonymous nomination content.
 *
 * IMPORTANT (privacy): this model intentionally does NOT use LogsActivity and has
 * NO nominator column/relation. A nomination stores only the nominee and an
 * optional reason, so it can never be traced back to whoever submitted it.
 */
class Nomination extends Model
{
    use HasShortId;

    protected $fillable = [
        'nomination_period_id',
        'nominee_id',
        'reason',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(NominationPeriod::class, 'nomination_period_id');
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_id');
    }
}
