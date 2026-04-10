<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Representa uma aula agendada dentro de uma disciplina.
 *
 * @property int $id
 * @property int $subject_id
 * @property string $title
 * @property ?string $description
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property bool $is_active
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property-read Subject $subject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LessonResponse> $responses
 */
class Lesson extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'subject_id',
        'title',
        'description',
        'scheduled_at',
        'is_active',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Disciplina à qual a aula pertence.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Respostas dos alunos para esta aula.
     *
     * @return HasMany<LessonResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(LessonResponse::class);
    }

    /**
     * Obtém a resposta de um aluno específico para esta aula.
     *
     * @param  int  $studentId  ID do aluno.
     * @return HasOne<LessonResponse, $this>
     */
    public function responseForStudent(int $studentId): HasOne
    {
        return $this->hasOne(LessonResponse::class)->where('student_id', $studentId);
    }

    /**
     * Verifica se a aula está disponível para resposta (ativa e no passado).
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->scheduled_at->isPast();
    }

    /**
     * Verifica se a aula está agendada para o futuro.
     *
     * @return bool
     */
    public function isFuture(): bool
    {
        return $this->scheduled_at->isFuture();
    }

    /**
     * Scope: apenas aulas disponíveis (ativas e com scheduled_at <= agora).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('scheduled_at', '<=', now());
    }

    /**
     * Scope: apenas aulas agendadas para o futuro.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeFuture($query)
    {
        return $query->where('scheduled_at', '>', now());
    }
}
