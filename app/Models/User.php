<?php

namespace App\Models;

use App\Concerns\HasShortId;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CausesActivity, HasApiTokens, HasFactory, HasRoles, HasShortId, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly([
                'name',
                'title',
                'email',
                'department_id',
                'branch_id',
                'phone',
                'status',
                'notification_channel',
                'file',
                'signature',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'title',
        'email',
        'password',
        'file',
        'signature',
        'department_id',
        'branch_id',
        'phone',
        'status',
        'notification_channel',
        'last_login_at',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function headedDepartment()
    {
        return $this->hasOne(Department::class, 'head_user_id');
    }

    public function subDepartments()
    {
        return $this->belongsToMany(Department::class, 'sub_department_user', 'user_id', 'sub_department_id');
    }

    public function departmentScopeIds(): array
    {
        if (Schema::hasTable('sub_department_user')) {
            $subDepartmentIds = $this->subDepartments()->pluck('departments.id')->all();
        } else {
            $subDepartmentIds = [];
        }

        if (! empty($subDepartmentIds)) {
            $scoped = [];
            $subDepartments = Department::whereIn('id', $subDepartmentIds)->get();
            foreach ($subDepartments as $subDepartment) {
                $scoped = array_merge($scoped, $subDepartment->selfAndDescendantIds());
            }

            return array_values(array_unique($scoped));
        }

        if ($this->department_id) {
            $department = $this->department;
            if ($department) {
                return $department->selfAndDescendantIds();
            }
        }

        // No explicit department/sub-department assignment means global visibility.
        return Department::pluck('id')->all();
    }

    public function receivesEmailNotifications(): bool
    {
        return $this->status === 'active'
            && filled(trim((string) $this->email))
            && in_array((string) $this->notification_channel, ['email', 'both'], true);
    }

    /**
     * Grace-period work emails: any active staff with an email on file (not limited to profile SMS preference).
     */
    public function canReceiveGracePeriodWorkEmail(): bool
    {
        return $this->status === 'active' && filled(trim((string) $this->email));
    }

    public function requiresLoginOtp(?int $hours = null): bool
    {
        $hours ??= (int) config('auth.login_otp_inactivity_hours', 24);

        return $this->last_login_at === null
            || $this->last_login_at->lt(now()->subHours($hours));
    }

    public function maskedEmail(): string
    {
        $email = trim((string) $this->email);

        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = substr($local, 0, 1);
        $hidden = max(strlen($local) - 1, 2);

        return $visible.str_repeat('*', $hidden).'@'.$domain;
    }

    /**
     * Relationship: User has many Documents
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
