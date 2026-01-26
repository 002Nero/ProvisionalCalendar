<?php

namespace Tests\Unit\Services;

use App\Mail\TeacherNotificationMail;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\WithoutDatabaseTestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

class TeacherNotificationServiceTest extends WithoutDatabaseTestCase
{
    public function test_notification_is_sent_once_per_delay()
    {
        $this->useArrayCache();
        Mail::fake();

        $service = new TeacherNotificationService();

        $teacher = new Teacher();
        $teacher->id = 42;
        $teacher->setRelation('user', new User(['email' => 'teacher@example.test']));

        $service->handleModification($teacher);
        $service->handleModification($teacher);

        Mail::assertSent(TeacherNotificationMail::class, 1);
        $this->assertTrue(Cache::has('teacher_notification_42'));
        $this->assertTrue(Cache::has('notification_scheduled_42'));
    }

    public function test_no_mail_sent_when_teacher_has_no_email()
    {
        $this->useArrayCache();
        Mail::fake();

        $service = new TeacherNotificationService();

        $teacher = new Teacher();
        $teacher->id = 7;
        // No user/email set on purpose

        $service->handleModification($teacher);

        Mail::assertNothingSent();
        $this->assertTrue(Cache::has('teacher_notification_7'));
        $this->assertTrue(Cache::has('notification_scheduled_7'));
    }

    private function useArrayCache(): void
    {
        Cache::swap(new Repository(new ArrayStore()));
    }
}
