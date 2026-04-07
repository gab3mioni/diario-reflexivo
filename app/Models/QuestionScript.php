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
     * Get the ordered list of nodes following the edges from start to end.
     * Returns only the bot message nodes in conversation order.
     */
    public function getOrderedNodes(): array
    {
        $nodes = collect($this->nodes)->keyBy('id');
        $edges = collect($this->edges);

        // Find start node
        $startNode = $nodes->firstWhere('type', 'start');
        if (!$startNode) {
            return [];
        }

        $ordered = [];
        $currentId = $startNode['id'];
        $visited = [];

        while ($currentId && !in_array($currentId, $visited)) {
            $visited[] = $currentId;
            $node = $nodes->get($currentId);

            if ($node) {
                $ordered[] = $node;
            }

            // Find the next node via edges
            $edge = $edges->firstWhere('source', $currentId);
            $currentId = $edge['target'] ?? null;
        }

        return $ordered;
    }
}
