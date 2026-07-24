<?php

namespace App\Filament\Widgets;

use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Models\PushNotification;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DeliveryStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getHeading(): ?string
    {
        return 'Delivery overview';
    }

    protected function getDescription(): ?string
    {
        return 'Tenant totals for the last 7 days vs the prior week.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId === null) {
            return [];
        }

        $thisWeekStart = now()->subDays(6)->startOfDay();
        $priorWeekStart = now()->subDays(13)->startOfDay();
        $priorWeekEnd = now()->subDays(7)->endOfDay();

        $messagesTotal = $this->notificationsQuery($tenantId)->count();
        $messagesThisWeek = $this->notificationsQuery($tenantId)
            ->where('created_at', '>=', $thisWeekStart)
            ->count();
        $messagesPriorWeek = $this->notificationsQuery($tenantId)
            ->whereBetween('created_at', [$priorWeekStart, $priorWeekEnd])
            ->count();

        $deliveriesSent = $this->deliveriesQuery($tenantId)
            ->whereIn('status', NotificationDelivery::SUCCESS_STATUSES)
            ->count();
        $deliveriesSentThisWeek = $this->deliveriesQuery($tenantId)
            ->whereIn('status', NotificationDelivery::SUCCESS_STATUSES)
            ->where('created_at', '>=', $thisWeekStart)
            ->count();
        $deliveriesSentPriorWeek = $this->deliveriesQuery($tenantId)
            ->whereIn('status', NotificationDelivery::SUCCESS_STATUSES)
            ->whereBetween('created_at', [$priorWeekStart, $priorWeekEnd])
            ->count();

        $deliveriesOpened = $this->deliveriesQuery($tenantId)
            ->where('status', NotificationDelivery::STATUS_DELIVERED)
            ->count();
        $deliveriesOpenedThisWeek = $this->deliveriesQuery($tenantId)
            ->where('status', NotificationDelivery::STATUS_DELIVERED)
            ->where('delivered_at', '>=', $thisWeekStart)
            ->count();
        $deliveriesOpenedPriorWeek = $this->deliveriesQuery($tenantId)
            ->where('status', NotificationDelivery::STATUS_DELIVERED)
            ->whereBetween('delivered_at', [$priorWeekStart, $priorWeekEnd])
            ->count();

        $deliveriesFailed = $this->deliveriesQuery($tenantId)
            ->where('status', NotificationDelivery::STATUS_FAILED)
            ->count();
        $deliveriesFailedThisWeek = $this->deliveriesQuery($tenantId)
            ->where('status', NotificationDelivery::STATUS_FAILED)
            ->where('created_at', '>=', $thisWeekStart)
            ->count();

        $deviceTokens = DeviceToken::query()
            ->whereHas(
                'user.companies',
                fn (Builder $query) => $query->whereKey($tenantId),
            )
            ->count();

        return [
            Stat::make('Messages', number_format($messagesTotal))
                ->description($this->trendDescription($messagesThisWeek, $messagesPriorWeek, 'this week'))
                ->descriptionIcon($this->trendIcon($messagesThisWeek, $messagesPriorWeek))
                ->descriptionColor($this->trendColor($messagesThisWeek, $messagesPriorWeek))
                ->chart($this->dailyCounts(
                    $this->notificationsQuery($tenantId),
                    'created_at',
                    $thisWeekStart,
                ))
                ->color('primary')
                ->icon(Heroicon::OutlinedBellAlert),
            Stat::make('Deliveries sent', number_format($deliveriesSent))
                ->description($this->trendDescription($deliveriesSentThisWeek, $deliveriesSentPriorWeek, 'sent this week'))
                ->descriptionIcon($this->trendIcon($deliveriesSentThisWeek, $deliveriesSentPriorWeek))
                ->descriptionColor($this->trendColor($deliveriesSentThisWeek, $deliveriesSentPriorWeek))
                ->chart($this->dailyCounts(
                    $this->deliveriesQuery($tenantId)->whereIn('status', NotificationDelivery::SUCCESS_STATUSES),
                    'created_at',
                    $thisWeekStart,
                ))
                ->color('success')
                ->icon(Heroicon::OutlinedCheckCircle),
            Stat::make('Opened in app', number_format($deliveriesOpened))
                ->description($this->trendDescription($deliveriesOpenedThisWeek, $deliveriesOpenedPriorWeek, 'opened this week'))
                ->descriptionIcon($this->trendIcon($deliveriesOpenedThisWeek, $deliveriesOpenedPriorWeek))
                ->descriptionColor($this->trendColor($deliveriesOpenedThisWeek, $deliveriesOpenedPriorWeek))
                ->chart($this->dailyCounts(
                    $this->deliveriesQuery($tenantId)->where('status', NotificationDelivery::STATUS_DELIVERED),
                    'delivered_at',
                    $thisWeekStart,
                ))
                ->color('info')
                ->icon(Heroicon::OutlinedEye),
            Stat::make('Deliveries failed', number_format($deliveriesFailed))
                ->description(number_format($deliveriesFailedThisWeek).' failed this week')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->descriptionColor($deliveriesFailedThisWeek > 0 ? 'danger' : 'gray')
                ->chart($this->dailyCounts(
                    $this->deliveriesQuery($tenantId)->where('status', NotificationDelivery::STATUS_FAILED),
                    'created_at',
                    $thisWeekStart,
                ))
                ->color($deliveriesFailed > 0 ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedXCircle),
            Stat::make('Device tokens', number_format($deviceTokens))
                ->description('Registered push endpoints')
                ->descriptionIcon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('info')
                ->icon(Heroicon::OutlinedDevicePhoneMobile),
        ];
    }

    /**
     * @return Builder<PushNotification>
     */
    protected function notificationsQuery(int|string $tenantId): Builder
    {
        return PushNotification::query()->where('company_id', $tenantId);
    }

    /**
     * @return Builder<NotificationDelivery>
     */
    protected function deliveriesQuery(int|string $tenantId): Builder
    {
        return NotificationDelivery::query()->whereHas(
            'pushNotification',
            fn (Builder $query) => $query->where('company_id', $tenantId),
        );
    }

    /**
     * @param  Builder<*>  $query
     * @return array<int, float>
     */
    protected function dailyCounts(Builder $query, string $column, CarbonInterface $from): array
    {
        $dateExpression = $this->dateExpression($column);

        /** @var array<string, int|string> $grouped */
        $grouped = (clone $query)
            ->where($column, '>=', $from)
            ->selectRaw("{$dateExpression} as day, COUNT(*) as aggregate")
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->all();

        $points = [];

        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo)->toDateString();
            $points[] = (float) ($grouped[$day] ?? 0);
        }

        return $points;
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

    protected function trendDescription(int $current, int $previous, string $suffix): string
    {
        if ($previous === 0) {
            return number_format($current).' '.$suffix;
        }

        $delta = round((($current - $previous) / $previous) * 100, 1);
        $direction = $delta >= 0 ? 'up' : 'down';

        return abs($delta).'% '.$direction.' · '.number_format($current).' '.$suffix;
    }

    protected function trendIcon(int $current, int $previous): Heroicon
    {
        if ($current === $previous) {
            return Heroicon::Minus;
        }

        return $current > $previous
            ? Heroicon::OutlinedArrowTrendingUp
            : Heroicon::OutlinedArrowTrendingDown;
    }

    protected function trendColor(int $current, int $previous): string
    {
        if ($current === $previous) {
            return 'gray';
        }

        return $current > $previous ? 'success' : 'warning';
    }
}
