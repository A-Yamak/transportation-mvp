<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Pages;

use App\Filament\Resources\Ideas\IdeaResource;
use App\Filament\Resources\Ideas\Schemas\IdeaForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/**
 * =============================================================================
 * Edit Idea Page
 * =============================================================================
 * Page for editing existing ideas.
 *
 * ACTIONS:
 * - View: Switch to read-only view
 * - Delete: Soft delete the idea
 * - Force Delete: Permanently delete (only for trashed)
 * - Restore: Restore soft-deleted idea
 *
 * @group Ideas
 * =============================================================================
 */
class EditIdea extends EditRecord
{
    protected static string $resource = IdeaResource::class;

    /**
     * Header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Mutate form data before saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert duration to minutes
        if (isset($data['duration_value']) && isset($data['duration_unit'])) {
            $data['duration_minutes'] = IdeaForm::convertDurationToMinutes(
                (int) $data['duration_value'],
                $data['duration_unit']
            );
        }

        // Remove virtual fields
        unset($data['duration_value'], $data['duration_unit']);

        return $data;
    }

    /**
     * Redirect to list after saving.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
