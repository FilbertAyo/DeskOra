<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_configs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('schedule_type')->default('weekly');
            $table->unsignedTinyInteger('day_of_week')->default(5); // Friday (Carbon::FRIDAY)
            $table->string('time', 5)->default('15:00');
            $table->string('timezone')->default(config('app.timezone', 'UTC'));
            $table->json('channels')->nullable();
            $table->json('audience_rules')->nullable();
            $table->json('criteria_rules')->nullable();
            $table->string('template_key')->default('weekly_plan_report');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('reminder_configs')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'key' => 'weekly_plan_report_followup',
            'name' => 'Weekly Plan/Report Follow-up',
            'enabled' => true,
            'schedule_type' => 'weekly',
            'day_of_week' => 5,
            'time' => '15:00',
            'timezone' => config('app.timezone', 'UTC'),
            'channels' => json_encode(['email', 'in_app']),
            'audience_rules' => json_encode([
                'permissions' => ['create weekly plan', 'create weekly report'],
                'status' => 'active',
            ]),
            'criteria_rules' => json_encode([
                'mode' => 'separate_per_activity',
            ]),
            'template_key' => 'weekly_plan_report',
            'meta' => json_encode([
                'description' => 'Reminder for staff missing weekly plan/report submissions.',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_configs');
    }
};
