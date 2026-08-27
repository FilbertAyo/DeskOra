<?php

namespace App\Support;

use App\Models\Department;
use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryHierarchy
{
    /**
     * @return array{locations: \Illuminate\Support\Collection, departments: \Illuminate\Support\Collection, users: \Illuminate\Support\Collection}
     */
    public static function pickerPayload(): array
    {
        $users = User::query()
            ->active()
            ->with(['subDepartments:id'])
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return [
            'locations' => InventoryLocation::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
            'departments' => Department::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']),
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'department_id' => $user->department_id,
                'sub_department_ids' => $user->subDepartments->pluck('id')->values()->all(),
            ])->values(),
        ];
    }

    public static function resolveLocationId(Request $request, string $field = 'location_id'): ?string
    {
        $room = $request->input('room_location_id');
        if (filled($room)) {
            return (string) $room;
        }

        $branch = $request->input('branch_location_id');
        if (filled($branch)) {
            return (string) $branch;
        }

        $value = $request->input($field);

        return filled($value) ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    public static function locationFilterIds(?string $locationId): array
    {
        if (! filled($locationId)) {
            return [];
        }

        $location = InventoryLocation::query()->find($locationId);

        return $location ? $location->selfAndDescendantIds() : [(string) $locationId];
    }

    public static function applyLocationFilter(Builder $query, ?string $locationId, string $column = 'location_id'): Builder
    {
        $ids = self::locationFilterIds($locationId);

        if ($ids === []) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * @return array{department_id: ?string, user_id: ?string}
     */
    public static function resolveOwnership(Request $request, string $userField, string $departmentField): array
    {
        $parentId = filled($request->input('parent_department_id'))
            ? (string) $request->input('parent_department_id')
            : null;
        $subId = filled($request->input('sub_department_id'))
            ? (string) $request->input('sub_department_id')
            : null;
        $userId = filled($request->input($userField))
            ? (string) $request->input($userField)
            : null;
        $wholeDepartment = $request->boolean('assign_whole_department');
        $wholeSubDepartment = $request->boolean('assign_whole_sub_department');

        if ($parentId !== null || $subId !== null || $wholeDepartment || $wholeSubDepartment) {
            if ($wholeDepartment && $parentId) {
                return ['department_id' => $parentId, 'user_id' => null];
            }

            if ($wholeSubDepartment && $subId) {
                return ['department_id' => $subId, 'user_id' => null];
            }

            if ($userId) {
                return ['department_id' => $subId ?: $parentId, 'user_id' => $userId];
            }

            if ($subId) {
                return ['department_id' => $subId, 'user_id' => null];
            }

            if ($parentId) {
                return ['department_id' => $parentId, 'user_id' => null];
            }
        }

        return [
            'department_id' => filled($request->input($departmentField)) ? (string) $request->input($departmentField) : null,
            'user_id' => $userId,
        ];
    }
}
