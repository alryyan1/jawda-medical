<?php

namespace App\Services\Admissions;

use App\Models\AdmissionSetting;
use Carbon\Carbon;

class StayDaysCalculator
{
    /**
     * Calculate the number of stay days for an admission based on configurable rules.
     *
     * @param  Carbon  $admissionAt  Date and time of admission
     * @param  Carbon|null  $endAt  End date/time (discharge or now if still admitted)
     * @param  AdmissionSetting|null  $config  Optional; uses AdmissionSetting::current() if not provided
     * @return int Number of days (>= 1)
     */
    public static function calculate(Carbon $admissionAt, ?Carbon $endAt = null, ?AdmissionSetting $config = null): int
    {
        $config = $config ?? AdmissionSetting::current();
        $endAt = $endAt ?? Carbon::now();

        if ($admissionAt->gte($endAt)) {
            return 1;
        }

        $rule = self::determineRule($admissionAt, $config);

        return match ($rule) {
            '24h' => self::calculate24h($admissionAt, $endAt),
            'full_day' => self::calculateFullDay($admissionAt, $endAt, $config),
            'default' => self::calculateDefault($admissionAt, $endAt),
        };
    }

    /**
     * Determine which rule applies based on admission time of day.
     */
    protected static function determineRule(Carbon $admissionAt, AdmissionSetting $config): string
    {
        $minutes = self::timeToMinutes(self::timeString($admissionAt));
        $morningStart = self::timeToMinutes(self::normalizeTime($config->morning_start));
        $morningEnd = self::timeToMinutes(self::normalizeTime($config->morning_end));
        $eveningStart = self::timeToMinutes(self::normalizeTime($config->evening_start));
        $eveningEnd = self::timeToMinutes(self::normalizeTime($config->evening_end));
        $defaultStart = self::timeToMinutes(self::normalizeTime($config->default_period_start));
        $defaultEnd = self::timeToMinutes(self::normalizeTime($config->default_period_end));

        // Default period: 06:00–07:00 (e.g. 360–420 minutes)
        if ($minutes >= $defaultStart && $minutes < $defaultEnd) {
            return 'default';
        }

        // 24h system: morning window (e.g. 07:00–12:00)
        if ($minutes >= $morningStart && $minutes <= $morningEnd) {
            return '24h';
        }

        // Full-day system: evening 13:00–23:59 or 00:00–06:00 (next day)
        if ($minutes >= $eveningStart || $minutes < $eveningEnd) {
            return 'full_day';
        }

        // Between morning_end and evening_start (e.g. 12:00–13:00): treat as 24h
        return '24h';
    }

    protected static function timeString(Carbon $dt): string
    {
        return $dt->format('H:i:s');
    }

    protected static function normalizeTime($value): string
    {
        $s = (string) $value;
        if (strlen($s) === 5) {
            return $s . ':00';
        }
        return substr($s, 0, 8);
    }

    protected static function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return $h * 60 + $m;
    }

    protected static function calculate24h(Carbon $admissionAt, Carbon $endAt): int
    {
        $days = 1;
        $currentEnd = $admissionAt->copy()->addHours(24);
        while ($currentEnd->lt($endAt)) {
            $days++;
            $currentEnd->addHours(24);
        }
        return $days;
    }

    protected static function calculateFullDay(Carbon $admissionAt, Carbon $endAt, AdmissionSetting $config): int
    {
        $boundary = self::normalizeTime($config->full_day_boundary);
        $day1End = $admissionAt->copy()->startOfDay()->addDay()->setTimeFromTimeString($boundary);

        if ($endAt->lte($day1End)) {
            return 1;
        }

        $days = 1;
        $current = $day1End->copy();
        while ($current->lt($endAt)) {
            $days++;
            $current->addDay()->setTimeFromTimeString($boundary);
        }
        return $days;
    }

    protected static function calculateDefault(Carbon $admissionAt, Carbon $endAt): int
    {
        $days = $admissionAt->diffInDays($endAt) + 1;
        return max(1, $days);
    }
}
