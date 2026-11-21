<?php

namespace Tests\Unit\Services\Voucher;

use App\Services\Voucher\VoucherCodeGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoucherCodeGeneratorTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function charsets(): array
    {
        return [
            'alphanumeric' => ['alphanumeric', '/^[A-HJ-NP-Z2-9]{8}$/'],
            'numeric' => ['numeric', '/^\d{8}$/'],
            'alpha' => ['alpha', '/^[A-HJ-NP-Z]{8}$/'],
        ];
    }

    #[DataProvider('charsets')]
    public function test_generates_code_of_requested_length_from_charset(string $charset, string $pattern): void
    {
        $this->assertMatchesRegularExpression($pattern, (new VoucherCodeGenerator)->generate(8, $charset));
    }

    public function test_alphanumeric_codes_never_contain_ambiguous_characters(): void
    {
        $generator = new VoucherCodeGenerator;

        for ($i = 0; $i < 200; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[01OI]/', $generator->generate(10, 'alphanumeric'));
        }
    }

    public function test_prepends_uppercased_prefix_to_the_random_part(): void
    {
        $code = (new VoucherCodeGenerator)->generate(5, 'numeric', 'wf');

        $this->assertMatchesRegularExpression('/^WF\d{5}$/', $code);
    }

    public function test_rejects_unknown_charset(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new VoucherCodeGenerator)->generate(6, 'emoji');
    }
}
