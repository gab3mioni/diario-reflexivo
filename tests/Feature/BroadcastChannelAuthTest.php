<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

/**
 * Invoke the channel auth callback registered for a given private channel name.
 * Mirrors what Laravel's broadcast auth endpoint does internally, without
 * having to spin up the HTTP layer or a real broadcaster driver.
 */
function authorizeChannel(User $user, string $channel): bool
{
    $broadcaster = Broadcast::driver();
    $reflection = new ReflectionClass(Broadcaster::class);

    // Resolve the registered channel definitions ($channels property).
    $channelsProp = $reflection->getProperty('channels');
    $channelsProp->setAccessible(true);
    $channels = $channelsProp->getValue($broadcaster);

    foreach ($channels as $pattern => $callback) {
        $regex = '/^'.preg_replace('/\{(.*?)\}/', '(?<$1>[^.]+)', $pattern).'$/u';
        if (preg_match($regex, $channel, $matches)) {
            $params = array_filter($matches, fn ($k) => ! is_int($k), ARRAY_FILTER_USE_KEY);

            return (bool) $callback($user, ...array_values($params));
        }
    }

    return false;
}

it('authorizes the owning student on a lesson-response channel', function () {
    $student = User::factory()->create();
    $teacher = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
    ]);

    expect(authorizeChannel($student, "lesson-response.{$response->id}"))->toBeTrue();
});

it('authorizes the subject teacher on a lesson-response channel', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
    ]);

    expect(authorizeChannel($teacher, "lesson-response.{$response->id}"))->toBeTrue();
});

it('rejects an unrelated user on a lesson-response channel', function () {
    $stranger = User::factory()->create();
    $owner = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $otherTeacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $owner->id,
    ]);

    expect(authorizeChannel($stranger, "lesson-response.{$response->id}"))->toBeFalse();
});

it('only authorizes a teacher on their own teacher channel', function () {
    $teacherA = User::factory()->create();
    $teacherB = User::factory()->create();

    expect(authorizeChannel($teacherA, "teacher.{$teacherB->id}"))->toBeFalse();
    expect(authorizeChannel($teacherA, "teacher.{$teacherA->id}"))->toBeTrue();
});
