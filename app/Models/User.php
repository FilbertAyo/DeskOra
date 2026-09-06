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

    /**
     * Units this user belongs to, plus parent departments.
     * Does not include sibling or child sub-departments they are not in.
     * Unlike departmentScopeIds(), users without a unit get an empty list (not every department).
     *
     * @return list<string>
     */
    public function inventoryCustodyScopeIds(): array
    {
        $ids = [];

        if ($this->department_id) {
            $department = $this->relationLoaded('department') ? $this->department : $this->department()->first();
            if ($department) {
                $ids = array_merge($ids, $department->selfAndAncestorIds());
            } else {
                $ids[] = (string) $this->department_id;
            }
        }

        if (Schema::hasTable('sub_department_user')) {
            $subDepartments = $this->relationLoaded('subDepartments')
                ? $this->subDepartments
                : $this->subDepartments()->get();

            foreach ($subDepartments as $subDepartment) {
                $ids = array_merge($ids, $subDepartment->selfAndAncestorIds());
            }
        }

        return array_values(array_unique(array_map('strval', $ids)));
    }

    public function hasInventoryCustodyOf(InventoryAsset $asset): bool
    {
        return InventoryAsset::query()
            ->whereKey($asset->id)
            ->inUserCustody($this)
            ->exists();
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

    public function isDemoViewer(): bool
    {
        $role = (string) config('demo.role', 'demo_viewer');

        if ($role === '') {
            return false;
        }

        return $this->getRoleNames()->contains($role);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeWithoutDemoViewers($query)
    {
        $role = (string) config('demo.role', 'demo_viewer');

        return $query->whereDoesntHave('roles', function ($roles) use ($role) {
            $roles->where('roles.name', $role);
        });
    }

    public function requiresLoginOtp(?int $hours = null): bool
    {
        if ($this->isDemoViewer()) {
            return false;
        }

        $hours ??= (int) config('auth.login_otp_inactivity_hours', 120);

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
