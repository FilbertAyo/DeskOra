<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReminderConfig extends Model
{
    use HasFactory;
    use HasUlids;

    public const KEY_WEEKLY_PLAN_REPORT = 'weekly_plan_report_followup';

    public const KEY_WEEKLY_GRACE_PERIOD = 'weekly_grace_period';

    protected $fillable = [
        'key',
        'name',
        'enabled',
        'schedule_type',
        'day_of_week',
        'time',
        'timezone',
        'channels',
        'audience_rules',
        'criteria_rules',
        'template_key',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
            'audience_rules' => 'array',
            'criteria_rules' => 'array',
            'meta' => 'array',
        ];
    }
}
