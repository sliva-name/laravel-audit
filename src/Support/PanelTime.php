<?php

declare(strict_types=1);

namespace LaravelAudit\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

final class PanelTime
{
    public static function format(mixed $value): string
    {
        $date = self::parse($value);

        return $date === null ? '—' : $date->format('M j, Y · H:i');
    }

    public static function datetime(mixed $value): ?string
    {
        return self::parse($value)?->toIso8601String();
    }

    public static function tooltip(mixed $value): ?string
    {
        $date = self::parse($value);

        if ($date === null) {
            return null;
        }

        return sprintf('%s (%s)', $date->diffForHumans(), $date->toDateTimeString());
    }

    public static function duration(float $seconds): string
    {
        if ($seconds < 1) {
            return number_format($seconds * 1000, 0).' ms';
        }

        if ($seconds < 60) {
            $formatted = rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.');

            return $formatted.' sec';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = (int) round($seconds - ($minutes * 60));

        if ($remaining === 0) {
            return $minutes.' min';
        }

        return sprintf('%d min %d sec', $minutes, $remaining);
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
