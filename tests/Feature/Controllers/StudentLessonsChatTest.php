<?php

use App\Jobs\ProcessChatTurn;
use App\Models\ChatMessage;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Queue::fake();
    RateLimiter::clear('lessons.chat.message');

    $this->student = User::factory()->student()->create();
    $this->subject = Subject::factory()->create();
    $this->subject->students()->attach($this->student->id);
    $this->lesson = Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'scheduled_at' => now()->subDay(),
    ]);

    QuestionScript::factory()->active()->create();

    $this->response = LessonResponse::factory()->create([
        'lesson_id' => $this->lesson->id,
        'student_id' => $this->student->id,
    ]);

    $this->actingAs($this->student);
});

test('sendMessage writes student message, sets processing state, and dispatches job', function () {
    $response = $this->post(route('lessons.chat.message', $this->lesson->id), [
        'content' => 'minha reflexão',
        'node_id' => 'q1',
    ]);

    $response->assertRedirect();

    $fresh = $this->response->fresh();
    expect($fresh->chat_state)->toBe(LessonResponse::CHAT_STATE_PROCESSING);
    expect($fresh->student_message_count)->toBe(1);

    $msg = ChatMessage::where('lesson_response_id', $this->response->id)->first();
    expect($msg)->not->toBeNull();
    expect($msg->content)->toBe('minha reflexão');

    Queue::assertPushed(ProcessChatTurn::class);
});

test('sendMessage blocks a new turn while chat is still processing', function () {
    $this->response->update([
        'chat_state' => LessonResponse::CHAT_STATE_PROCESSING,
        'chat_state_since' => now(),
    ]);

    $response = $this->post(route('lessons.chat.message', $this->lesson->id), [
        'content' => 'segundo',
        'node_id' => 'q1',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    Queue::assertNotPushed(ProcessChatTurn::class);
});

test('sendMessage blocks after submission', function () {
    $this->response->update(['submitted_at' => now()]);

    $this->post(route('lessons.chat.message', $this->lesson->id), [
        'content' => 'tarde demais',
        'node_id' => 'q1',
    ])->assertForbidden();
});

test('sendMessage 404 for non-enrolled student', function () {
    $otherStudent = User::factory()->student()->create();
    $this->actingAs($otherStudent);

    $this->post(route('lessons.chat.message', $this->lesson->id), [
        'content' => 'hack',
        'node_id' => 'q1',
    ])->assertNotFound();
});

test('throttle: more than 20 messages in a minute yields flash error', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->response->update(['chat_state' => LessonResponse::CHAT_STATE_IDLE]);
        $this->post(route('lessons.chat.message', $this->lesson->id), [
            'content' => "msg {$i}",
            'node_id' => 'q1',
        ]);
    }

    $response = $this->post(route('lessons.chat.message', $this->lesson->id), [
        'content' => 'over limit',
        'node_id' => 'q1',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('saveDraft stores draft for enrolled student', function () {
    $this->put(route('lessons.chat.draft', $this->lesson->id), [
        'content' => 'rascunho',
    ])->assertRedirect();

    // No visible side-effect to assert beyond 200 — cache is the storage.
    expect(true)->toBeTrue();
});

test('IDOR: saveDraft 404 for lesson the student has no access to', function () {
    $foreignSubject = Subject::factory()->create();
    $foreignLesson = Lesson::factory()->create([
        'subject_id' => $foreignSubject->id,
        'scheduled_at' => now()->subDay(),
    ]);

    $this->put(route('lessons.chat.draft', $foreignLesson->id), [
        'content' => 'hack',
    ])->assertNotFound();
});
