<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Department extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('departments')
            ->logOnly(['name', 'status', 'parent_id', 'head_user_id', 'plan_section_number'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'plan_section_number' => 'integer',
        ];
    }

    protected $fillable = [
        'name',
        'status',
        'parent_id',
        'head_user_id',
        'plan_section_number',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function petties()
    {
        return $this->hasMany(Petty::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function yearPlanActivities()
    {
        return $this->hasMany(YearPlanActivity::class);
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

    public function selfAndDescendantIds(): array
    {
        return array_merge([$this->id], $this->descendantIds());
    }
}
