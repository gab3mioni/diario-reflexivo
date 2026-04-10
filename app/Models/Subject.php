<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Representa uma disciplina vinculada a um curso e ministrada por um professor.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $course_id
 * @property int $teacher_id
 * @property bool $is_active
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read Course $course
 * @property-read User $teacher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $students
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Lesson> $lessons
 */
class Subject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'course_id',
        'teacher_id',
        'is_active',
    ];

    /**
     * Curso ao qual a disciplina pertence.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Professor responsável pela disciplina.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Alunos matriculados na disciplina.
     *
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_student', 'subject_id', 'student_id');
    }

    /**
     * Aulas pertencentes a esta disciplina.
     *
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}