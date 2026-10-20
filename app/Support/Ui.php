<?php

namespace App\Support;

use App\Enums\StatusCategory;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Presentation rules shared by the panel, courier app and tracking page. */
final class Ui
{
    /** The world's line inks, assigned to teams/couriers as route identity. */
    public const INKS = ['#e21d2d', '#2f6bff', '#ffc20e', '#22b14c', '#16b3a5', '#9b6bff'];

    public static function ink(?int $id): string
    {
        return $id === null ? '#8e99b8' : self::INKS[$id % count(self::INKS)];
    }

    /** A courier's line colour everywhere: their (first) team's ink, else one derived from the id. */
    public static function courierInk(?User $courier): string
    {
        if ($courier === null) {
            return '#8e99b8';
        }

        $team = $courier->relationLoaded('teams') ? $courier->teams->first() : $courier->teams()->first(['teams.id', 'teams.color']);

        return $team?->color ?? self::ink($courier->id);
    }

    /**
     * Line form per category, so status reads without colour: dashed = waiting,
     * solid = moving, double = failed, struck = cancelled.
     *
     * @return array{form: string, ink: string}
     */
    public static function statusStyle(StatusCategory $category): array
    {
        return match ($category) {
            StatusCategory::Created => ['form' => 'dashed', 'ink' => '#8e99b8'],
            StatusCategory::Pool => ['form' => 'dashed', 'ink' => '#9b6bff'],
            StatusCategory::Assigned => ['form' => 'solid', 'ink' => '#2f6bff'],
            StatusCategory::InProgress => ['form' => 'solid', 'ink' => '#ffc20e'],
            StatusCategory::OnDelivery => ['form' => 'solid', 'ink' => '#16b3a5'],
            StatusCategory::Completed => ['form' => 'solid', 'ink' => '#22b14c'],
            StatusCategory::Cancelled => ['form' => 'struck', 'ink' => '#8e99b8'],
            StatusCategory::Failed => ['form' => 'double', 'ink' => '#e21d2d'],
        };
    }

    /** Signed minutes until the deadline; negative means late. */
    public static function minutesLeft(?Carbon $endsAt): ?int
    {
        return $endsAt === null ? null : (int) floor(now()->diffInMinutes($endsAt, false));
    }

    /** Compact human form of minutes: 47, 2s 10d, −14. */
    public static function minutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $sign = $minutes < 0 ? '−' : '';
        $abs = abs($minutes);

        return $abs < 100 ? $sign.$abs : $sign.intdiv($abs, 60).__('ui.h').' '.($abs % 60).__('ui.m');
    }

    /** urgency bucket for the minutes numeral: late / risk / ok */
    public static function urgency(?int $minutes): string
    {
        return match (true) {
            $minutes === null => 'ok',
            $minutes < 0 => 'late',
            $minutes <= 20 => 'risk',
            default => 'ok',
        };
    }

    public static function localTime(?Carbon $time, ?string $format = 'H:i'): string
    {
        $tz = app(CurrentCompany::class)->get()?->timezone ?? config('app.timezone');

        return $time ? $time->copy()->setTimezone($tz)->translatedFormat($format) : '—';
    }
}
