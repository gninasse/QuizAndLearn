<?php

namespace Tests\Unit;

use Modules\Core\Services\Sm2Service;
use PHPUnit\Framework\TestCase;

/**
 * Vecteurs de test SM-2 — répliqués côté client dans
 * resources/js/learner/domain/sm2.test.ts pour garantir la parité.
 */
class Sm2ServiceTest extends TestCase
{
    private Sm2Service $sm2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sm2 = new Sm2Service;
    }

    public function test_first_successful_review_gives_one_day_interval(): void
    {
        $result = $this->sm2->review(2.5, 0, 0, 5);

        $this->assertSame(1, $result['interval_days']);
        $this->assertSame(1, $result['repetitions']);
        $this->assertSame(2.6, $result['easiness_factor']);
    }

    public function test_second_successful_review_gives_six_day_interval(): void
    {
        $result = $this->sm2->review(2.6, 1, 1, 5);

        $this->assertSame(6, $result['interval_days']);
        $this->assertSame(2, $result['repetitions']);
    }

    public function test_third_review_multiplies_interval_by_easiness(): void
    {
        $result = $this->sm2->review(2.6, 2, 6, 4);

        // round(6 * 2.6) = 16
        $this->assertSame(16, $result['interval_days']);
        $this->assertSame(3, $result['repetitions']);
        $this->assertSame(2.6, $result['easiness_factor']); // q=4 : EF inchangé
    }

    public function test_failed_review_resets_repetitions_and_interval(): void
    {
        $result = $this->sm2->review(2.6, 4, 30, 1);

        $this->assertSame(1, $result['interval_days']);
        $this->assertSame(0, $result['repetitions']);
        // q=1 : EF - 0.54
        $this->assertSame(2.06, $result['easiness_factor']);
    }

    public function test_easiness_never_drops_below_floor(): void
    {
        $result = $this->sm2->review(1.3, 0, 0, 0);

        $this->assertSame(1.3, $result['easiness_factor']);
    }

    public function test_quality_three_decreases_easiness(): void
    {
        // q=3 : EF + (0.1 - 2*(0.08+2*0.02)) = EF - 0.14
        $result = $this->sm2->review(2.5, 0, 0, 3);

        $this->assertSame(2.36, $result['easiness_factor']);
        $this->assertSame(1, $result['repetitions']);
    }

    public function test_interval_clamped_to_deck_max(): void
    {
        $result = $this->sm2->review(2.5, 5, 200, 5, intervalMin: 1, intervalMax: 365);
        $this->assertSame(365, $result['interval_days']);

        $clamped = $this->sm2->review(2.5, 5, 200, 5, intervalMin: 1, intervalMax: 90);
        $this->assertSame(90, $clamped['interval_days']);
    }

    public function test_interval_respects_deck_min(): void
    {
        $result = $this->sm2->review(2.5, 0, 0, 5, intervalMin: 3);

        $this->assertSame(3, $result['interval_days']);
    }

    public function test_status_mapping(): void
    {
        $this->assertSame('relearning', $this->sm2->statusFor(0, 1));
        $this->assertSame('learning', $this->sm2->statusFor(1, 5));
        $this->assertSame('learning', $this->sm2->statusFor(2, 4));
        $this->assertSame('review', $this->sm2->statusFor(3, 4));
        $this->assertSame('mastered', $this->sm2->statusFor(5, 5));
    }
}
