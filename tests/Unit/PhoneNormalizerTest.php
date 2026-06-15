<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_us_ten_digit_to_e164(): void
    {
        $this->assertSame('+13035551234', PhoneNormalizer::toE164('303-555-1234'));
        $this->assertSame('+13035551234', PhoneNormalizer::toE164('(303) 555 1234'));
        $this->assertSame('+13035551234', PhoneNormalizer::toE164('13035551234'));
    }

    public function test_preserves_e164(): void
    {
        $this->assertSame('+447946000000', PhoneNormalizer::toE164('+44 7946 000000'));
    }

    public function test_rejects_garbage(): void
    {
        $this->assertNull(PhoneNormalizer::toE164(''));
        $this->assertNull(PhoneNormalizer::toE164('not a number'));
        $this->assertNull(PhoneNormalizer::toE164('+0123'));
    }
}
