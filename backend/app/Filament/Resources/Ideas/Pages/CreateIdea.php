<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Pages;

use App\Filament\Resources\Ideas\IdeaResource;
use App\Filament\Resources\Ideas\Schemas\IdeaForm;
use Filament\Resources\Pages\CreateRecord;

/**
 * =============================================================================
 * Create Idea Page
 * =============================================================================
 * Page for creating new ideas.
 *
 * MUTATION:
 * - Converts duration value/unit to duration_minutes before saving
 *
 * @group Ideas
 * =============================================================================
 */
class CreateIdea extends CreateRecord
{
    protected static string $resource = IdeaResource::class;

    /**
     * Mutate form data before creation.
     *
     * WHY MUTATE?
     * - Form has duration_value and duration_unit fields
     * - Database stores duration_minutes
     * - This converts user input to storage format
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert duration to minutes
        if (isset($data['duration_value']) && isset($data['duration_unit'])) {
            $data['duration_minutes'] = IdeaForm::convertDurationToMinutes(
                (int) $data['duration_value'],
                $data['duration_unit']
            );
        }

        // Remove virtual fields (not in database)
        unset($data['duration_value'], $data['duration_unit']);

        return $data;
    }

    /**
     * Redirect to list after creation.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
