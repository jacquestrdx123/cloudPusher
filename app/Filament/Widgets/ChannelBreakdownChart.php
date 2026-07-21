<?php

namespace App\Filament\Widgets;

use App\Models\NotificationDelivery;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ChannelBreakdownChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Channels';

    protected ?string $description = 'Delivery attempts by channel for this company.';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
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

        $counts = NotificationDelivery::query()
            ->whereHas(
                'pushNotification',
                fn (Builder $query) => $query->where('company_id', $tenantId),
            )
            ->selectRaw('channel, COUNT(*) as aggregate')
            ->groupBy('channel')
            ->pluck('aggregate', 'channel');

        $order = ['fcm', 'apns', 'mail', 'sms'];
        $labels = [];
        $data = [];
        $colors = [
            'fcm' => 'rgb(14, 165, 233)',
            'apns' => 'rgb(232, 144, 12)',
            'mail' => 'rgb(99, 102, 241)',
            'sms' => 'rgb(20, 184, 166)',
        ];
        $background = [];

        foreach ($order as $channel) {
            $count = (int) ($counts[$channel] ?? 0);

            if ($count === 0 && ! $counts->has($channel)) {
                continue;
            }

            $labels[] = strtoupper($channel);
            $data[] = $count;
            $background[] = $colors[$channel];
        }

        foreach ($counts as $channel => $aggregate) {
            if (in_array($channel, $order, true)) {
                continue;
            }

            $labels[] = strtoupper((string) $channel);
            $data[] = (int) $aggregate;
            $background[] = 'rgb(107, 114, 128)';
        }

        if ($data === []) {
            $labels = ['None'];
            $data = [0];
            $background = ['rgb(209, 213, 219)'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Deliveries',
                    'data' => $data,
                    'backgroundColor' => $background,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
