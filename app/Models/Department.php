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

    public function pathLabel(): string
    {
        if ($this->parent) {
            return $this->parent->name.' › '.$this->name;
        }

        return $this->name;
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

    /**
     * Parent units above this department (not including self).
     *
     * @return list<string>
     */
    public function ancestorIds(): array
    {
        $ids = [];
        $current = $this->parent_id
            ? ($this->relationLoaded('parent') ? $this->parent : $this->parent()->first())
            : null;
        $guard = 0;

        while ($current && $guard < 20) {
            $ids[] = (string) $current->id;
            $current = $current->parent_id
                ? ($current->relationLoaded('parent') ? $current->parent : $current->parent()->first())
                : null;
            $guard++;
        }

        return $ids;
    }

    /**
     * This department plus parent units, so a sub-department member
     * also matches items assigned to the whole parent department.
     *
     * @return list<string>
     */
    public function selfAndAncestorIds(): array
    {
        return array_merge([(string) $this->id], $this->ancestorIds());
    }
}
