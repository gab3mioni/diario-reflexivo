<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Representa um usuário da aplicação (aluno, professor ou administrador).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?\Illuminate\Support\Carbon $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property ?string $two_factor_secret
 * @property ?string $two_factor_recovery_codes
 * @property ?\Illuminate\Support\Carbon $two_factor_confirmed_at
 * @property ?string $remember_token
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $teachers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $students
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Subject> $subjectsAsTeacher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Subject> $subjectsAsStudent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LessonResponse> $lessonResponses
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Papéis atribuídos ao usuário.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Verifica se o usuário possui um papel específico.
     *
     * @param  string  $roleSlug  Slug do papel (ex.: 'student', 'teacher', 'admin').
     * @return bool
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Verifica se o usuário é aluno.
     *
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    /**
     * Verifica se o usuário é professor.
     *
     * @return bool
     */
    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    /**
     * Verifica se o usuário é administrador.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Verifica se o usuário possui ambos os papéis de aluno e professor.
     *
     * @return bool
     */
    public function hasBothRoles(): bool
    {
        return $this->isStudent() && $this->isTeacher();
    }

    /**
     * Professores associados a este aluno.
     *
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_teacher', 'student_id', 'teacher_id');
    }

    /**
     * Alunos associados a este professor.
     *
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_teacher', 'teacher_id', 'student_id');
    }

    /**
     * Disciplinas onde este usuário é o professor.
     *
     * @return HasMany<Subject, $this>
     */
    public function subjectsAsTeacher(): HasMany
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    /**
     * Disciplinas onde este usuário está matriculado como aluno.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function subjectsAsStudent(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_student', 'student_id', 'subject_id');
    }

    /**
     * Respostas de aula submetidas por este aluno.
     *
     * @return HasMany<LessonResponse, $this>
     */
    public function lessonResponses(): HasMany
    {
        return $this->hasMany(LessonResponse::class, 'student_id');
    }
}
