<?php

namespace App\Mail;

use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TeacherNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $teacher;

    public function __construct(Teacher $teacher)
    {
        $this->teacher = $teacher;
        Log::debug('TeacherNotificationMail: creating mail instance', ['teacher_id' => $teacher->id]);
    }

    public function build()
    {
        Log::info('TeacherNotificationMail: building email', [
            'teacher_id' => $this->teacher->id,
            'subject' => 'Notification de modification'
        ]);

        return $this
                    ->subject('Notification de modification')
                    ->view('mail_notification');
    }
}