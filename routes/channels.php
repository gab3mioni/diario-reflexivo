<?php

use App\Models\LessonResponse;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('lesson-response.{responseId}', function ($user, int $responseId) {
    $response = LessonResponse::with('lesson.subject')->find($responseId);
    if (! $response) {
        return false;
    }
    return $user->id === $response->student_id
        || $user->id === $response->lesson?->subject?->teacher_id
        || (method_exists($user, 'isAdmin') && $user->isAdmin());
});

Broadcast::channel('teacher.{teacherId}', function ($user, int $teacherId) {
    return (int) $user->id === $teacherId;
});
