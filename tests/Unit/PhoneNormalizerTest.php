<?php

use App\Domain\Shared\Services\PhoneNormalizer;

beforeEach(function () {
    $this->normalizer = app(PhoneNormalizer::class);
});

it('normalizes Vietnamese phone numbers to 84xxxxxxxxx', function (string $input, string $expected) {
    expect($this->normalizer->normalize($input))->toBe($expected);
})->with([
    'national mobile' => ['0912345678', '84912345678'],
    'with spaces' => ['0912 345 678', '84912345678'],
    'with dots and dashes' => ['091.234-5678', '84912345678'],
    'international with plus' => ['+84 912 345 678', '84912345678'],
    'international without plus' => ['84912345678', '84912345678'],
    'international dialing prefix' => ['0084912345678', '84912345678'],
    'new 03x prefix' => ['0386 123 456', '84386123456'],
    'Hanoi landline' => ['024 3869 4242', '842438694242'],
]);

it('returns null for empty or invalid numbers', function (?string $input) {
    expect($this->normalizer->normalize($input))->toBeNull()
        ->and($this->normalizer->isValid($input))->toBeFalse();
})->with([
    'null' => [null],
    'empty' => [''],
    'spaces' => ['   '],
    'letters' => ['abc'],
    'too short' => ['09123'],
    'too long' => ['091234567890123'],
]);

it('keeps foreign numbers in E.164 digits', function () {
    expect($this->normalizer->normalize('+1 650-253-0000'))->toBe('16502530000');
});

it('formats numbers for national display', function () {
    expect($this->normalizer->formatNational('84912345678'))->toBe('0912 345 678')
        ->and($this->normalizer->formatNational('not a phone'))->toBe('not a phone');
});
