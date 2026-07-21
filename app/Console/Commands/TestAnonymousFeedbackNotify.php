<?php

namespace App\Console\Commands;

use App\Models\AnonymousFeedback;
use App\Notifications\AnonymousFeedbackSubmittedNotification;
use App\Services\AnonymousFeedbackNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class TestAnonymousFeedbackNotify extends Command
{
    protected $signature = 'anonymous-feedback:test-notify
                            {email : Email address to send a test notification to}
                            {--full : Also run the full recipient resolver against a sample submission}';

    protected $description = 'Send a test anonymous-feedback email notification';

    public function handle(AnonymousFeedbackNotificationService $service): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $feedback = new AnonymousFeedback([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Test anonymous feedback notification — please ignore.',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);
        $feedback->id = 0;

        $this->info('Sending test notification to '.$email.'...');

        try {
            Notification::route('mail', $email)
                ->notify(new AnonymousFeedbackSubmittedNotification($feedback));
        } catch (\Throwable $e) {
            $this->error('Failed to send: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Test notification sent.');

        if ($this->option('full')) {
            $this->info('Running full notifySubmitted() against a temporary DB row...');
            $row = AnonymousFeedback::query()->create([
                'category' => AnonymousFeedback::CATEGORY_SUGGESTION,
                'body' => 'Full-path test anonymous feedback — please ignore / delete.',
                'status' => AnonymousFeedback::STATUS_NEW,
            ]);
            $service->notifySubmitted($row);
            $this->info('Full-path notify completed for feedback #'.$row->id);
        }

        return self::SUCCESS;
    }
}
