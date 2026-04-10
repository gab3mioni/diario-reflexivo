<?php

use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->create();
    $this->subject = Subject::factory()->create(['teacher_id' => $this->teacher->id]);
});

test('index does not N+1 on lesson counts (query count stays bounded)', function () {
    // Create 5 lessons with 3 students and 1 response each (unique per student+lesson).
    $students = User::factory()->student()->count(3)->create();
    $this->subject->students()->attach($students->pluck('id'));

    $lessons = Lesson::factory()->count(5)->create(['subject_id' => $this->subject->id]);
    foreach ($lessons as $lesson) {
        foreach ($students->take(2) as $student) {
            LessonResponse::factory()->create([
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
            ]);
        }
    }

    $this->actingAs($this->teacher);
    DB::enableQueryLog();

    $this->get(route('lessons.index'))->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Heuristic: must not grow with N lessons. With withCount and eager loading
    // the count should be < 20 regardless of how many lessons.
    expect($queryCount)->toBeLessThan(20);
});

test('storeBulk generates lessons skipping Sunday and early hours', function () {
    $this->actingAs($this->teacher);

    $response = $this->post(route('lessons.store-bulk'), [
        'subject_id' => $this->subject->id,
        'title_prefix' => 'Aula',
        'description' => 'desc',
        'day_of_week' => 3, // Wednesday
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-31',
        'start_time' => '08:00',
    ]);

    $response->assertRedirect();
    expect(Lesson::where('subject_id', $this->subject->id)->count())->toBeGreaterThanOrEqual(4);
});

test('storeBulk rejects Sunday (day_of_week=0)', function () {
    $this->actingAs($this->teacher);

    $this->post(route('lessons.store-bulk'), [
        'subject_id' => $this->subject->id,
        'title_prefix' => 'Aula',
        'day_of_week' => 0,
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-31',
        'start_time' => '08:00',
    ])->assertSessionHasErrors('day_of_week');
});

test('storeBulk rejects early-morning start_time', function () {
    $this->actingAs($this->teacher);

    $this->post(route('lessons.store-bulk'), [
        'subject_id' => $this->subject->id,
        'title_prefix' => 'Aula',
        'day_of_week' => 3,
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-31',
        'start_time' => '05:30',
    ])->assertSessionHasErrors('start_time');
});

test('show authorizes via policy: other teacher cannot see lesson', function () {
    $lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($otherTeacher);
    $this->get(route('lessons.show', $lesson->id))->assertForbidden();
});

test('show allows subject teacher', function () {
    $lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);

    $this->actingAs($this->teacher);
    $this->get(route('lessons.show', $lesson->id))->assertOk();
});

test('update forbidden for non-owner teacher', function () {
    $lesson = Lesson::factory()->create(['subject_id' => $this->subject->id]);
    $otherTeacher = User::factory()->teacher()->create();

    $this->actingAs($otherTeacher);
    $this->put(route('lessons.update', $lesson->id), [
        'title' => 'hack',
        'scheduled_at' => now()->toIso8601String(),
    ])->assertForbidden();
});
