<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Representa um roteiro de perguntas reflexivas com estrutura de grafo (nós e arestas).
 *
 * @property int $id
 * @property string $name
 * @property ?string $description
 * @property bool $is_active
 * @property array<int, array<string, mixed>> $nodes
 * @property array<int, array<string, mixed>> $edges
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class QuestionScript extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'nodes',
        'edges',
    ];

    /**
     * Atributos que devem ser convertidos para tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'nodes' => 'array',
            'edges' => 'array',
        ];
    }

    /**
     * Retorna o roteiro de perguntas atualmente ativo.
     *
     * @return ?static
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Busca um nó pelo seu identificador.
     *
     * @param  string  $nodeId  Identificador único do nó.
     * @return ?array<string, mixed>
     */
    public function getNode(string $nodeId): ?array
    {
        foreach ($this->nodes ?? [] as $node) {
            if (($node['id'] ?? null) === $nodeId) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Retorna todas as arestas de saída de um nó.
     *
     * @param  string  $nodeId  Identificador único do nó de origem.
     * @return array<int, array<string, mixed>>
     */
    public function getOutgoingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges ?? [],
            fn ($edge) => ($edge['source'] ?? null) === $nodeId,
        ));
    }

    /**
     * Retorna a aresta de saída padrão (marcada como is_default).
     *
     * Caso nenhuma aresta esteja explicitamente marcada, retorna a primeira aresta de saída.
     *
     * @param  string  $nodeId  Identificador único do nó de origem.
     * @return ?array<string, mixed>
     */
    public function getDefaultOutgoingEdge(string $nodeId): ?array
    {
        $outgoing = $this->getOutgoingEdges($nodeId);

        foreach ($outgoing as $edge) {
            if (! empty($edge['is_default'])) {
                return $edge;
            }
        }

        return $outgoing[0] ?? null;
    }

    /**
     * Encontra o nó inicial do roteiro.
     *
     * @return ?array<string, mixed>
     */
    public function getStartNode(): ?array
    {
        foreach ($this->nodes ?? [] as $node) {
            if (($node['type'] ?? null) === 'start') {
                return $node;
            }
        }

        return null;
    }

    /**
     * Retorna o primeiro nó de um tipo específico alcançável a partir do início seguindo arestas padrão.
     *
     * Utilizado para encontrar a primeira pergunta após o nó de início.
     *
     * @param  string  $type  Tipo do nó (ex.: 'question', 'free_talk').
     * @return ?array<string, mixed>
     */
    public function getFirstNodeOfType(string $type): ?array
    {
        $start = $this->getStartNode();
        if (! $start) {
            return null;
        }

        $currentId = $start['id'];
        $visited = [];

        while ($currentId && ! in_array($currentId, $visited, true)) {
            $visited[] = $currentId;
            $node = $this->getNode($currentId);
            if (! $node) {
                return null;
            }
            if (($node['type'] ?? null) === $type) {
                return $node;
            }
            $edge = $this->getDefaultOutgoingEdge($currentId);
            $currentId = $edge['target'] ?? null;
        }

        return null;
    }

    /**
     * Percurso linear do início seguindo arestas padrão.
     *
     * Mantido para os helpers de pré-visualização/ordenação do admin;
     * o runtime do chat utiliza o resolver explícito.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrderedNodes(): array
    {
        $start = $this->getStartNode();
        if (! $start) {
            return [];
        }

        $ordered = [];
        $currentId = $start['id'];
        $visited = [];

        while ($currentId && ! in_array($currentId, $visited, true)) {
            $visited[] = $currentId;
            $node = $this->getNode($currentId);
            if ($node) {
                $ordered[] = $node;
            }

            $edge = $this->getDefaultOutgoingEdge($currentId);
            $currentId = $edge['target'] ?? null;
        }

        return $ordered;
    }
}
