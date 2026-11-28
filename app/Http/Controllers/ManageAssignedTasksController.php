<?php

namespace App\Http\Controllers;

use App\Models\ManagementAssignedTask;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\ManagementTaskAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManageAssignedTasksController extends Controller
{
    public function __construct(
        private readonly ManagementTaskAssignmentService $service
    ) {}

    public function index(Request $request): Response
    {
        $periodId = $request->string('period_id')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $assigneeUserId = $request->string('assignee_user_id')->toString() ?: null;

        if ($status !== null && $status !== '' && ! in_array($status, [
            'pending', 'in_progress', 'completed', 'overdue',
        ], true)) {
            $status = null;
        }

        $periods = $this->service->periodOptions();
        $currentPeriod = WorkPeriod::getCurrent();

        return Inertia::render('Management/AssignedTasks/Index', [
            'rows' => $this->service->dashboardRows($periodId, $status, $assigneeUserId),
            'filters' => [
                'period_id' => $periodId,
                'status' => $status,
                'assignee_user_id' => $assigneeUserId,
            ],
            'periods' => $periods->map(fn (WorkPeriod $period) => [
                'id' => (string) $period->id,
                'label' => $period->display_name,
                'date_range' => $period->date_range,
            ])->values(),
            'staffUsers' => $this->staffOptions(),
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'overdue', 'label' => 'Overdue'],
            ],
            'currentPeriodId' => $currentPeriod ? (string) $currentPeriod->id : null,
            'createUrl' => route('management.assigned-tasks.create'),
            'indexUrl' => route('management.assigned-tasks.index'),
        ]);
    }

    public function create(): Response
    {
        $periods = $this->service->periodOptions();
        $currentPeriod = WorkPeriod::getCurrent();

        return Inertia::render('Management/AssignedTasks/Create', [
            'periods' => $periods->map(fn (WorkPeriod $period) => [
                'id' => (string) $period->id,
                'label' => $period->display_name,
                'date_range' => $period->date_range,
            ])->values(),
            'staffUsers' => $this->staffOptions(),
            'defaultPeriodId' => $currentPeriod ? (string) $currentPeriod->id : ($periods->first()?->id ? (string) $periods->first()->id : ''),
            'submitUrl' => route('management.assigned-tasks.store'),
            'indexUrl' => route('management.assigned-tasks.index'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period_id' => ['required', 'string', Rule::exists('work_periods', 'id')],
            'deadline' => ['nullable', 'date'],
            'assignee_user_ids' => ['required', 'array', 'min:1'],
            'assignee_user_ids.*' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(static fn ($query) => $query->where('status', 'active')),
            ],
        ]);

        $task = $this->service->assign(
            $user,
            (string) $validated['period_id'],
            (string) $validated['title'],
            $validated['description'] ?? null,
            $validated['deadline'] ?? null,
            $validated['assignee_user_ids']
        );

        return redirect()
            ->route('management.assigned-tasks.show', $task)
            ->with('success', 'Task assigned successfully.');
    }

    public function show(ManagementAssignedTask $task): Response
    {
        return Inertia::render('Management/AssignedTasks/Show', [
            'task' => $this->service->taskShowPayload($task),
        ]);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function staffOptions(): array
    {
        return User::query()
            ->active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }
}
