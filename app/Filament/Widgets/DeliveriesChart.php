<?php

namespace App\Filament\Widgets;

use App\Models\NotificationDelivery;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeliveriesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Deliveries (14 days)';

    protected ?string $description = 'Sent vs failed delivery attempts for this company.';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId === null) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $from = now()->subDays(13)->startOfDay();
        $labels = [];
        $sent = [];
        $failed = [];

        $sentByDay = $this->dailyStatusCounts($tenantId, NotificationDelivery::STATUS_SENT, $from);
        $failedByDay = $this->dailyStatusCounts($tenantId, NotificationDelivery::STATUS_FAILED, $from);

        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo);
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $sent[] = (int) ($sentByDay[$key] ?? 0);
            $failed[] = (int) ($failedByDay[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sent',
                    'data' => $sent,
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Failed',
                    'data' => $failed,
                    'borderColor' => 'rgb(220, 38, 38)',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, int|string>
     */
    protected function dailyStatusCounts(int|string $tenantId, string $status, CarbonInterface $from): array
    {
        $dateExpression = $this->dateExpression('created_at');

        return NotificationDelivery::query()
            ->whereHas(
                'pushNotification',
                fn (Builder $query) => $query->where('company_id', $tenantId),
            )
            ->where('status', $status)
            ->where('created_at', '>=', $from)
            ->selectRaw("{$dateExpression} as day, COUNT(*) as aggregate")
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->all();
    }

    protected function dateExpression(string $column): string
    {
        $grammar = DB::connection()->getQueryGrammar();
        $wrapped = $grammar->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$wrapped})",
            'pgsql' => "to_char({$wrapped}, 'YYYY-MM-DD')",
            default => "DATE({$wrapped})",
        };
    }
}
