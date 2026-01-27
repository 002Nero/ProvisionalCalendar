<?php

namespace App\Services;

use App\Models\Teacher;
use Illuminate\Support\Facades\Mail;
use App\Mail\TeacherNotificationMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TeacherNotificationService
{
    const NOTIFICATION_DELAY = 300; // 5 minutes in seconds

    public function handleModification(Teacher $teacher)
    {
        $teacherId = $teacher->id;
        $cacheKey = "teacher_notification_{$teacherId}";

        Log::debug('TeacherNotificationService: handling modification', [
            'teacher_id' => $teacherId,
            'cache_key' => $cacheKey
        ]);

        // If we already have a pending notification, update its timestamp
        Cache::put($cacheKey, $teacher, self::NOTIFICATION_DELAY);

        // Schedule the notification
        if (!Cache::has("notification_scheduled_{$teacherId}")) {
            Cache::put("notification_scheduled_{$teacherId}", true, self::NOTIFICATION_DELAY);
            Log::info('TeacherNotificationService: scheduling notification', ['teacher_id' => $teacherId]);
            $this->sendNotification($teacher);
        } else {
            Log::debug('TeacherNotificationService: notification already scheduled', ['teacher_id' => $teacherId]);
        }
    }

    protected function sendNotification(Teacher $teacher)
    {
        if ($teacher->user && $teacher->user->email) {
            Log::info('TeacherNotificationService: sending notification email', [
                'teacher_id' => $teacher->id,
                'email' => $teacher->user->email
            ]);

            try {
                Mail::to($teacher->user->email)->send(new TeacherNotificationMail($teacher));
                Log::info('TeacherNotificationService: notification email sent successfully', [
                    'teacher_id' => $teacher->id
                ]);
            } catch (\Exception $e) {
                Log::error('TeacherNotificationService: failed to send notification email', [
                    'teacher_id' => $teacher->id,
                    'email' => $teacher->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            Log::warning('TeacherNotificationService: cannot send notification, no email available', [
                'teacher_id' => $teacher->id,
                'has_user' => (bool) $teacher->user
            ]);
        }
    }
}
