<?php

namespace App\Http\Controllers;

use App\Models\ReminderDispatchLog;
use App\Services\NotificationService;
use App\Services\ReminderConfigService;
use App\Services\WeeklySubmissionReminderDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(NotificationService $notificationService, ReminderConfigService $reminderConfigService)
    {
        $smsInfo = $notificationService->getSmsAccountInfo();
        $reminderConfig = $reminderConfigService->getWeeklyPlanReportConfig();
        $recentReminderLogs = ReminderDispatchLog::query()
            ->with(['user:id,name,email'])
            ->where('reminder_config_id', $reminderConfig->id)
            ->latest()
            ->limit(20)
            ->get();
        $lastReminderSentAt = ReminderDispatchLog::query()
            ->where('reminder_config_id', $reminderConfig->id)
            ->latest('created_at')
            ->value('created_at');

        return view('notifications.index', compact('smsInfo', 'reminderConfig', 'recentReminderLogs', 'lastReminderSentAt'));
    }

    public function updateWeeklyReminder(Request $request, ReminderConfigService $reminderConfigService)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'time' => ['required', 'date_format:H:i'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,in_app'],
        ]);

        $config = $reminderConfigService->updateWeeklyPlanReportConfig([
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'day_of_week' => (int) $validated['day_of_week'],
            'time' => $validated['time'],
            'timezone' => config('app.timezone', 'UTC'),
            'channels' => array_values(array_unique($validated['channels'])),
            'criteria_rules' => ['mode' => 'separate_per_activity'],
        ]);

        return redirect()
            ->route('notification.index')
            ->with('success', "Reminder settings updated for {$config->name}.");
    }

    public function markRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function runWeeklyReminderNow(WeeklySubmissionReminderDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->dispatchNow();
        $sent = (int) ($result['sent'] ?? 0);
        $targets = (int) ($result['targets'] ?? 0);

        return redirect()
            ->route('notification.index')
            ->with('success', "Manual reminder run completed. Sent to {$sent} recipient activity rows out of {$targets} targets.");
    }
}
