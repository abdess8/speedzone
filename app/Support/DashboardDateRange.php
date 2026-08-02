<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class DashboardDateRange
{
    public function __construct(
        public readonly string $period,
        public readonly CarbonInterface $start,
        public readonly CarbonInterface $end,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $period = (string) $request->input('period', 'last_30_days');

        return match ($period) {
            'today' => new self($period, Carbon::today()->startOfDay(), Carbon::today()->endOfDay()),
            'yesterday' => new self($period, Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()),
            'last_7_days' => new self($period, Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()),
            'last_30_days' => new self($period, Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()),
            'this_month' => new self($period, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()),
            'last_month' => new self(
                $period,
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ),
            'custom' => self::fromCustom($request),
            default => throw new InvalidArgumentException("Unknown dashboard period: {$period}"),
        };
    }

    private static function fromCustom(Request $request): self
    {
        $from = $request->input('from');
        $to = $request->input('to');

        if (! $from || ! $to) {
            throw new InvalidArgumentException('Custom period requires from and to dates.');
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('The from date must be before the to date.');
        }

        return new self('custom', $start, $end);
    }

    /**
     * @return array{period: string, from: string, to: string}
     */
    public function toMeta(): array
    {
        return [
            'period' => $this->period,
            'from' => $this->start->toDateString(),
            'to' => $this->end->toDateString(),
        ];
    }

    public function cacheKeySuffix(): string
    {
        return "{$this->period}:{$this->start->toDateString()}:{$this->end->toDateString()}";
    }
}
