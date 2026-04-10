<?php

use App\Events\LessonResponseSubmitted;
use App\Http\Controllers\StudentLessonsController;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches LessonResponseSubmitted when consolidateResponse runs', function () {
    Event::fake([LessonResponseSubmitted::class]);

    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
        'submitted_at' => null,
    ]);

    $controller = app(StudentLessonsController::class);
    $method = (new ReflectionClass($controller))->getMethod('consolidateResponse');
    $method->setAccessible(true);
    $method->invoke($controller, $response);

    Event::assertDispatched(
        LessonResponseSubmitted::class,
        fn (LessonResponseSubmitted $e) => $e->response->id === $response->id,
    );
});
