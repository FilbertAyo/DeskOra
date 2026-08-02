<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_mail_is_queueable(): void
    {
        Mail::fake();

        Mail::to('staff@example.com')->send(new NotificationMail('Staff', 'staff@example.com'));

        Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) {
            return $mail->name === 'Staff' && $mail->email === 'staff@example.com';
        });
    }
}
