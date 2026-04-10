<?php

use App\Contracts\Chat\BranchClassifierContract;
use App\Events\LessonResponseSubmitted;
use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Models\Subject;
use App\Models\User;
use App\Services\Chat\ChatTurnProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeBranchClassifier;

uses(RefreshDatabase::class);

it('dispatches LessonResponseSubmitted when chat finalizes', function () {
    Event::fake([LessonResponseSubmitted::class]);
    Notification::fake();
    Queue::fake();

    $fake = new FakeBranchClassifier();
    $this->app->instance(BranchClassifierContract::class, $fake);

    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $subject = Subject::factory()->create(['teacher_id' => $teacher->id]);
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id]);
    $script = QuestionScript::factory()->active()->create();
    $response = LessonResponse::factory()->create([
        'lesson_id' => $lesson->id,
        'student_id' => $student->id,
        'submitted_at' => null,
        'student_message_count' => ChatTurnProcessor::GLOBAL_MESSAGE_CAP,
    ]);

    $msg = ChatMessage::factory()->student()->create([
        'lesson_response_id' => $response->id,
        'node_id' => 'q1',
    ]);

    $processor = app(ChatTurnProcessor::class);
    $processor->processStudentTurn($script, $response->fresh(), $msg);

    Event::assertDispatched(
        LessonResponseSubmitted::class,
        fn (LessonResponseSubmitted $e) => $e->response->id === $response->id,
    );
});
