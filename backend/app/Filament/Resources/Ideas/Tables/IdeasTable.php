<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Tables;

use App\Enums\IdeaPriority;
use App\Enums\IdeaStatus;
use App\Models\Idea;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * =============================================================================
 * Ideas Table Configuration
 * =============================================================================
 * Configures the table for listing ideas.
 *
 * COLUMNS:
 * - Title: Main identifier, searchable
 * - Status: Badge with color/icon
 * - Priority: Badge with color/icon
 * - Deadline: With overdue indicator
 * - Duration: Human-readable format
 * - Created: For chronological tracking
 *
 * FILTERS:
 * - Status: Dropdown filter
 * - Priority: Dropdown filter
 * - Overdue: Boolean filter
 * - Due soon: Boolean filter
 * - Trashed: Show archived items
 *
 * ACTIONS:
 * - Start: Move to in_progress (visible on todo items)
 * - Complete: Move to done (visible on in_progress items)
 * - View/Edit: Standard CRUD actions
 *
 * @group Ideas
 * =============================================================================
 */
class IdeasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (Idea $record): ?string {
                        // Show full title on hover if truncated
                        return strlen($record->title) > 50 ? $record->title : null;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->sortable(),

                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color(fn (Idea $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->icon(fn (Idea $record): ?string => $record->isOverdue() ? 'heroicon-o-exclamation-circle' : null)
                    ->description(fn (Idea $record): ?string => $record->isOverdue() ? 'Overdue!' : ($record->isDueSoon() ? 'Due soon' : null)),

                TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn (Idea $record): ?string => $record->formatDuration())
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Status filter
                SelectFilter::make('status')
                    ->options(IdeaStatus::class)
                    ->multiple()
                    ->label('Status'),

                // Priority filter
                SelectFilter::make('priority')
                    ->options(IdeaPriority::class)
                    ->multiple()
                    ->label('Priority'),

                // Overdue filter
                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue())
                    ->toggle(),

                // Due soon filter
                Filter::make('due_soon')
                    ->label('Due This Week')
                    ->query(fn (Builder $query): Builder => $query->dueSoon())
                    ->toggle(),

                // Soft delete filter (for archived items)
                TrashedFilter::make(),
            ])
            ->recordActions([
                // Quick status transition: Start working
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Idea $record): bool => $record->status === IdeaStatus::TODO)
                    ->requiresConfirmation()
                    ->modalHeading('Start Working')
                    ->modalDescription('This will mark the idea as "In Progress".')
                    ->action(fn (Idea $record) => $record->markInProgress()),

                // Quick status transition: Mark complete
                Action::make('complete')
                    ->label('Done')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Idea $record): bool => $record->status === IdeaStatus::IN_PROGRESS)
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Done')
                    ->modalDescription('This will mark the idea as completed.')
                    ->action(fn (Idea $record) => $record->markDone()),

                // Standard actions
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            // Default sort: priority (critical first), then deadline (soonest first)
            ->defaultSort('priority', 'desc')
            ->searchable()
            ->poll('30s'); // Auto-refresh every 30 seconds for real-time updates
    }
}
