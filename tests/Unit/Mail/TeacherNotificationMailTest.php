<?php

namespace Tests\Unit\Mail;

use App\Mail\TeacherNotificationMail;
use App\Models\Teacher;
use Illuminate\Mail\Mailable;
use Tests\WithoutDatabaseTestCase;

class TeacherNotificationMailTest extends WithoutDatabaseTestCase
{
    public function test_build_sets_subject_and_view()
    {
        $teacher = new Teacher();
        $mail = (new TeacherNotificationMail($teacher))->build();

        $this->assertInstanceOf(Mailable::class, $mail);
        $this->assertSame('Notification de modification', $mail->subject);
        $this->assertEquals('mail_notification', $mail->view);
    }
}
