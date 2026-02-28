<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Get the course that owns the subject.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the teacher that owns the subject.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the students enrolled in the subject.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_student', 'subject_id', 'student_id');
    }

    /**
     * Get all lessons for this subject.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}