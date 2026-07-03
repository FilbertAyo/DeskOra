<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that a user has responded to a nomination period.
 *
 * IMPORTANT (privacy): this model intentionally does NOT use LogsActivity and does
 * NOT store which colleague was nominated. It only marks that a given user has
 * responded (nominated or skipped) so they are not prompted again and so
 * participation rates can be reported without breaking anonymity.
 */
class NominationParticipation extends Model
{
    use HasShortId;

    public const STATUS_NOMINATED = 'nominated';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'nomination_period_id',
        'user_id',
        'status',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(NominationPeriod::class, 'nomination_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
