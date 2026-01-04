<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Idea;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * =============================================================================
 * Idea Stats Overview Widget
 * =============================================================================
 * Dashboard widget showing idea pipeline statistics.
 *
 * STATS DISPLAYED:
 * - Total Ideas: Count of all non-archived ideas
 * - In Progress: Count of active work items
 * - Overdue: Count of items past deadline (red if > 0)
 * - Completed This Week: Recent completions
 *
 * WHY THESE STATS?
 * - Total gives overall scope
 * - In Progress shows current workload
 * - Overdue needs immediate attention
 * - Completed This Week shows productivity
 *
 * @group Ideas
 * =============================================================================
 */
class IdeaStatsOverview extends BaseWidget
{
    /**
     * Widget polling interval.
     *
     * WHY POLL?
     * - Dashboard should show real-time counts
     * - 30 seconds is reasonable refresh rate
     */
    protected ?string $pollingInterval = '30s';

    /**
     * Get the stats to display.
     */
    protected function getStats(): array
    {
        $totalIdeas = Idea::withoutTrashed()->count();
        $inProgressCount = Idea::inProgress()->count();
        $overdueCount = Idea::overdue()->count();
        $completedThisWeek = Idea::done()
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make('Total Ideas', $totalIdeas)
                ->description('All active ideas')
                ->descriptionIcon('heroicon-o-light-bulb')
                ->color('gray'),

            Stat::make('In Progress', $inProgressCount)
                ->description('Currently being worked on')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color($inProgressCount > 3 ? 'warning' : 'primary'),

            Stat::make('Overdue', $overdueCount)
                ->description($overdueCount > 0 ? 'Need attention!' : 'All on track')
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Completed This Week', $completedThisWeek)
                ->description('Since ' . now()->startOfWeek()->format('M j'))
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),
        ];
    }
}
