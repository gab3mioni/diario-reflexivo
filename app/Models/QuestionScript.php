<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionScript extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'nodes',
        'edges',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'nodes' => 'array',
            'edges' => 'array',
        ];
    }

    /**
     * Get the currently active question script.
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Lookup a single node by id.
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
     * Return all outgoing edges for a given node.
     *
     * @return array<int, array>
     */
    public function getOutgoingEdges(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges ?? [],
            fn ($edge) => ($edge['source'] ?? null) === $nodeId,
        ));
    }

    /**
     * Return the default outgoing edge (flagged is_default). Falls back to the
     * first outgoing edge if none is explicitly marked.
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
     * Find the start node.
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
     * Return the first node reachable from the start by following default edges
     * that is of the given type. Used to find the first question after start.
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
     * Linear walk from start following default edges. Kept for the admin
     * preview/ordering helpers; the chat runtime uses the explicit resolver.
     *
     * @return array<int, array>
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
