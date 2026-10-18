<?php

namespace Tests\Unit;

use Modules\Core\Support\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_normalizes_local_international_and_00_prefixes(): void
    {
        foreach (['07 00 00 00 00', '+225 07-00-00-00-00', '002250700000000', '2250700000000'] as $raw) {
            $this->assertSame('+2250700000000', PhoneNumber::normalize($raw, '225'), $raw);
        }
    }

    public function test_rejects_garbage(): void
    {
        foreach (['abc', '12', '', null] as $raw) {
            $this->assertNull(PhoneNumber::normalize($raw, '225'));
        }
    }

    public function test_keeps_foreign_numbers(): void
    {
        $this->assertSame('+33612345678', PhoneNumber::normalize('+33 6 12 34 56 78', '225'));
    }
}
