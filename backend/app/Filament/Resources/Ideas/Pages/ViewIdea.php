<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Pages;

use App\Enums\IdeaStatus;
use App\Filament\Resources\Ideas\IdeaResource;
use App\Models\Idea;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * =============================================================================
 * View Idea Page
 * =============================================================================
 * Read-only detailed view of an idea.
 *
 * WHY A SEPARATE VIEW PAGE?
 * - Quick overview without edit mode
 * - Shows computed values (time spent, formatted duration)
 * - Clean presentation of all data
 *
 * ACTIONS:
 * - Start/Complete: Quick status transitions
 * - Edit: Switch to edit mode
 * - Delete/Restore: Manage archiving
 *
 * @group Ideas
 * =============================================================================
 */
class ViewIdea extends ViewRecord
{
    protected static string $resource = IdeaResource::class;

    /**
     * Header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            // Quick status transitions
            Action::make('start')
                ->label('Start Working')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === IdeaStatus::TODO)
                ->requiresConfirmation()
                ->action(fn () => $this->record->markInProgress()),

            Action::make('complete')
                ->label('Mark Done')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === IdeaStatus::IN_PROGRESS)
                ->requiresConfirmation()
                ->action(fn () => $this->record->markDone()),

            Action::make('reopen')
                ->label('Reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === IdeaStatus::DONE)
                ->requiresConfirmation()
                ->modalHeading('Reopen Idea')
                ->modalDescription('This will move the idea back to "Todo" status.')
                ->action(fn () => $this->record->markTodo()),

            EditAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
