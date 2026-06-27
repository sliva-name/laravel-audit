<?php

declare(strict_types=1);

namespace LaravelAudit\Tests\Unit;

use Illuminate\Support\Carbon;
use LaravelAudit\Support\PanelTime;
use LaravelAudit\Tests\TestCase;

final class PanelTimeTest extends TestCase
{
    public function test_format_returns_placeholder_for_empty_values(): void
    {
        $this->assertSame('—', PanelTime::format(null));
        $this->assertSame('—', PanelTime::format(''));
    }

    public function test_format_accepts_iso_strings_and_carbon_instances(): void
    {
        $this->assertSame(
            'Jun 27, 2026 · 14:30',
            PanelTime::format('2026-06-27T14:30:00+00:00'),
        );

        $this->assertSame(
            'Jun 27, 2026 · 14:30',
            PanelTime::format(Carbon::parse('2026-06-27T14:30:00+00:00')),
        );
    }

    public function test_tooltip_includes_relative_and_exact_time(): void
    {
        $tooltip = PanelTime::tooltip('2026-06-27T14:30:00+00:00');

        $this->assertNotNull($tooltip);
        $this->assertStringContainsString('2026-06-27 14:30:00', $tooltip);
    }

    public function test_duration_is_human_readable(): void
    {
        $this->assertSame('850 ms', PanelTime::duration(0.85));
        $this->assertSame('1.3 sec', PanelTime::duration(1.25));
        $this->assertSame('2 min 5 sec', PanelTime::duration(125));
        $this->assertSame('3 min', PanelTime::duration(180));
    }
}
