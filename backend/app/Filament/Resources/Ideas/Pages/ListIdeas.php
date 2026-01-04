<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Pages;

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Filament\Widgets\IdeaStatsOverview;
use App\Models\Idea;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * =============================================================================
 * List Ideas Page
 * =============================================================================
 * Main listing page for ideas with tabs for quick filtering.
 *
 * TABS:
 * - All: Show all ideas
 * - Todo: Ideas not yet started
 * - In Progress: Ideas currently being worked on
 * - Done: Completed ideas
 * - Overdue: Ideas past their deadline
 *
 * WHY TABS INSTEAD OF JUST FILTERS?
 * - Faster access to common views
 * - Visual indicator of counts per status
 * - Simpler UX for daily workflow
 *
 * @group Ideas
 * =============================================================================
 */
class ListIdeas extends ListRecords
{
    protected static string $resource = IdeaResource::class;

    /**
     * Header actions (Create button).
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Idea'),
        ];
    }

    /**
     * Define tabs for quick filtering.
     *
     * WHY BADGE COUNTS?
     * - Quick overview without clicking
     * - Shows workload at a glance
     * - Overdue badge is red to draw attention
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Idea::withoutTrashed()->count())
                ->badgeColor('gray'),

            'todo' => Tab::make('Todo')
                ->modifyQueryUsing(fn ($query) => $query->todo())
                ->badge(Idea::todo()->count())
                ->badgeColor('gray'),

            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn ($query) => $query->inProgress())
                ->badge(Idea::inProgress()->count())
                ->badgeColor('warning'),

            'done' => Tab::make('Done')
                ->modifyQueryUsing(fn ($query) => $query->done())
                ->badge(Idea::done()->count())
                ->badgeColor('success'),

            'overdue' => Tab::make('Overdue')
                ->modifyQueryUsing(fn ($query) => $query->overdue())
                ->badge(Idea::overdue()->count())
                ->badgeColor('danger'),
        ];
    }

    /**
     * Get header widgets for this page.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            IdeaStatsOverview::class,
        ];
    }
}
