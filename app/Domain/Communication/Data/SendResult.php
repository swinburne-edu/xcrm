<?php

namespace App\Domain\Communication\Data;

/** Outcome of one send: the provider id when accepted, else an error message. */
final class SendResult
{
    private function __construct(public readonly bool $ok, public readonly ?string $providerMessageId = null, public readonly ?string $error = null) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
