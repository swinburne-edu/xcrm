<?php

namespace App\Domain\Workflow\Enums;

enum WorkflowActionType: string
{
    case Assign = 'assign';
    case CreateTask = 'create_task';
    case SendMessage = 'send_message';
    case UpdateField = 'update_field';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::Assign => 'Giao người phụ trách',
            self::CreateTask => 'Tạo việc cần làm',
            self::SendMessage => 'Gửi tin nhắn cho thí sinh',
            self::UpdateField => 'Cập nhật trường',
            self::Webhook => 'Gọi webhook',
        };
    }

    /** Whether the action makes sense for the object. */
    public function supports(WorkflowObject $object): bool
    {
        return match ($this) {
            self::Assign, self::UpdateField => $object === WorkflowObject::AdmissionCase,
            default => true,
        };
    }
}
