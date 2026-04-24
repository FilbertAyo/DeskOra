<?php

namespace App\Http\Controllers;

use App\Services\ReminderConfigService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function index(NotificationService $notificationService, ReminderConfigService $reminderConfigService)
    {
        $smsInfo = $notificationService->getSmsAccountInfo();
        $reminderConfig = $reminderConfigService->getWeeklyPlanReportConfig();

        return view('notifications.index', compact('smsInfo', 'reminderConfig'));
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
}

