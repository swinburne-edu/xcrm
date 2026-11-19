<?php

namespace App\Domain\Workflow\Actions;

use App\Domain\Workflow\Models\Workflow;

class SaveWorkflow
{
    /** @param  array<string, mixed>  $data  validated by WorkflowRequest */
    public function handle(?Workflow $workflow, array $data): Workflow
    {
        $workflow ??= new Workflow;
        $trigger = ['type' => $data['trigger']['type']];

        if ($trigger['type'] === 'updated') {
            $trigger['field'] = $data['trigger']['field'];
        }

        $workflow->fill([
            'name' => $data['name'],
            'object' => $data['object'],
            'trigger' => $trigger,
            'conditions' => array_values($data['conditions'] ?? []),
            'actions' => array_values($data['actions']),
            'is_active' => $data['is_active'] ?? $workflow->is_active,
        ])->save();

        return $workflow;
    }
}
