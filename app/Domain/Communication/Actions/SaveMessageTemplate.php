<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\MessageTemplate;

class SaveMessageTemplate
{
    /** @param  array<string, mixed>  $data  validated by MessageTemplateRequest */
    public function handle(?MessageTemplate $template, array $data): MessageTemplate
    {
        $template ??= new MessageTemplate;
        $template->fill([
            'channel' => $data['channel'],
            'name' => $data['name'],
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'provider_template_id' => $data['provider_template_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $template;
    }
}
