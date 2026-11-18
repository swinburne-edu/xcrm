<?php

use App\Domain\Catalog\Services\CombinationValidator;

function combo(array $pairs): array
{
    return array_map(fn ($code, $weight) => ['code' => $code, 'weight' => $weight], array_keys($pairs), $pairs);
}

beforeEach(function () {
    $this->validator = new CombinationValidator;
});

it('accepts valid combinations (R2)', function (array $subjects) {
    expect($this->validator->errors($subjects))->toBe([]);
})->with([
    'A00 equal weights' => [combo(['TOAN' => 1, 'LY' => 1, 'HOA' => 1])],
    'D01 with both core subjects' => [combo(['VAN' => 1, 'TOAN' => 1, 'ANH' => 1])],
    'math double weight' => [combo(['TOAN' => 2, 'LY' => 1, 'HOA' => 1])],
    'exactly one third' => [combo(['VAN' => 1, 'SU' => 1, 'DIA' => 1])],
    'lower-case codes' => [combo(['toan' => 1, 'ly' => 1, 'hoa' => 1])],
    'one core heavy enough, other light' => [combo(['TOAN' => 2, 'VAN' => 0.5, 'ANH' => 1.5])],
]);

it('rejects combinations breaking R2', function (array $subjects, string $message) {
    expect($this->validator->errors($subjects))->toBe([$message]);
})->with([
    'two subjects' => [combo(['TOAN' => 1, 'LY' => 1]), 'Tổ hợp phải có đúng 3 môn.'],
    'four subjects' => [combo(['TOAN' => 1, 'LY' => 1, 'HOA' => 1, 'ANH' => 1]), 'Tổ hợp phải có đúng 3 môn.'],
    'duplicate subject' => [[['code' => 'TOAN', 'weight' => 1], ['code' => 'toan', 'weight' => 1], ['code' => 'LY', 'weight' => 1]], 'Ba môn trong tổ hợp phải khác nhau.'],
    'no math or literature' => [combo(['LY' => 1, 'HOA' => 1, 'SINH' => 1]), 'Tổ hợp bắt buộc có môn Toán hoặc Ngữ văn.'],
    'math weighs less than a third' => [combo(['TOAN' => 1, 'LY' => 2, 'HOA' => 1]), 'Trọng số môn Toán/Ngữ văn phải chiếm ít nhất 1/3 tổng trọng số.'],
    'zero weight' => [combo(['TOAN' => 1, 'LY' => 0, 'HOA' => 1]), 'Trọng số mỗi môn phải lớn hơn 0.'],
]);
