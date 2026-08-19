<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryLocation extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'status',
        'parent_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_locations')
            ->logOnly(['name', 'description', 'status', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function descendantIds(): array
    {
        $ids = [];
        $queue = [$this->id];

        while (! empty($queue)) {
            $current = array_shift($queue);
            $childIds = self::where('parent_id', $current)->pluck('id')->all();

            foreach ($childIds as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }
}
