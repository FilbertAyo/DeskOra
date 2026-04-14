<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CausesActivity, HasApiTokens, HasFactory, HasRoles, HasUlids, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'department_id', 'phone', 'status', 'notification_channel', 'file', 'signature'])
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
        'email',
        'password',
        'file',
        'signature',
        'department_id',
        'branch_id',
        'phone',
        'status',
        'notification_channel',
    ];

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
        $subDepartmentIds = $this->subDepartments()->pluck('departments.id')->all();
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
            'password' => 'hashed',
        ];
    }
}
