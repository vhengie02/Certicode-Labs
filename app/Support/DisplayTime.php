<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Dates as the signed-in viewer asked to see them. Stored times stay in UTC. A viewer with no
 * saved time zone keeps today's behavior: x-local-time follows their device, and anything else
 * shows UTC.
 */
class DisplayTime
{
    public const DATE_STYLES = [
        'mdy' => ['date' => 'M j, Y', 'datetime' => 'M j, Y, g:i A'],
        'dmy' => ['date' => 'j M Y', 'datetime' => 'j M Y, H:i'],
        'ymd' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i'],
    ];

    public static function zone(): ?string
    {
        return auth()->user()?->timezone ?: null;
    }

    public static function style(): ?string
    {
        $style = auth()->user()?->date_style;

        return isset(self::DATE_STYLES[$style]) ? $style : null;
    }

    /**
     * A copy of the moment in the viewer's saved time zone, for views with their own format.
     */
    public static function in(DateTimeInterface|string|null $at): ?CarbonInterface
    {
        if ($at === null || $at === '') {
            return null;
        }

        $moment = Carbon::parse($at);

        return self::zone() ? $moment->setTimezone(self::zone()) : $moment;
    }

    public static function format(DateTimeInterface|string $at, bool $dateOnly = false): string
    {
        $formats = self::DATE_STYLES[self::style() ?? 'mdy'];

        return self::in($at)->format($formats[$dateOnly ? 'date' : 'datetime']);
    }

    /**
     * A datetime-local value read in the viewer's saved time zone, ready to store.
     */
    public static function fromInput(?string $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, self::zone())->utc();
    }
}
