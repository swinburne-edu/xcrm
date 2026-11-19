<?php

namespace App\Domain\Communication\Data;

/** What a channel driver needs to send one message. */
final class OutboundMessage
{
    /**
     * @param  array<string, string>  $variables  template variables (ZNS sends them as template_data)
     */
    public function __construct(
        public readonly string $recipient,
        public readonly string $body,
        public readonly ?string $subject = null,
        public readonly ?string $providerTemplateId = null,
        public readonly array $variables = [],
        public readonly ?string $trackingId = null,
    ) {}
}
