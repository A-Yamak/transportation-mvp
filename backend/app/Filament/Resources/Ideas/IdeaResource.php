<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas;

use App\Filament\Resources\Ideas\Pages\CreateIdea;
use App\Filament\Resources\Ideas\Pages\EditIdea;
use App\Filament\Resources\Ideas\Pages\ListIdeas;
use App\Filament\Resources\Ideas\Pages\ViewIdea;
use App\Filament\Resources\Ideas\Schemas\IdeaForm;
use App\Filament\Resources\Ideas\Tables\IdeasTable;
use App\Models\Idea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * =============================================================================
 * Idea Resource
 * =============================================================================
 * Filament resource for managing ideas in the pipeline.
 *
 * STRUCTURE:
 * - IdeaResource.php (this file) - Main resource definition
 * - Tables/IdeasTable.php - Table columns, filters, actions
 * - Schemas/IdeaForm.php - Form fields, sections, validation
 * - Pages/ - List, Create, Edit, View pages
 *
 * WHY SEPARATE FILES?
 * - Single Responsibility: Each file handles one concern
 * - Maintainability: Easier to modify table vs form independently
 * - Reusability: Table config could be reused in widgets
 * - Readability: Smaller, focused files are easier to understand
 *
 * @group Ideas
 * =============================================================================
 */
class IdeaResource extends Resource
{
    protected static ?string $model = Idea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    /**
     * Navigation label in sidebar.
     */
    protected static ?string $navigationLabel = 'Ideas Pipeline';

    /**
     * Navigation group for organizing menu items.
     */
    protected static string|\UnitEnum|null $navigationGroup = 'Planning';

    /**
     * Badge showing count in navigation.
     *
     * WHY SHOW IN_PROGRESS COUNT?
     * - Quick visibility of active work
     * - Encourages finishing current tasks before starting new ones
     */
    public static function getNavigationBadge(): ?string
    {
        $inProgressCount = Idea::inProgress()->count();

        return $inProgressCount > 0 ? (string) $inProgressCount : null;
    }

    /**
     * Badge color based on count.
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        $inProgressCount = Idea::inProgress()->count();

        // Warning color if too many items in progress
        return $inProgressCount > 3 ? 'warning' : 'primary';
    }

    /**
     * Configure the form schema.
     */
    public static function form(Schema $schema): Schema
    {
        return IdeaForm::configure($schema);
    }

    /**
     * Configure the table.
     */
    public static function table(Table $table): Table
    {
        return IdeasTable::configure($table);
    }

    /**
     * Get the relations to display.
     */
    public static function getRelations(): array
    {
        return [
            // Future: History relation for tracking changes
        ];
    }

    /**
     * Get the pages for this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListIdeas::route('/'),
            'create' => CreateIdea::route('/create'),
            'view' => ViewIdea::route('/{record}'),
            'edit' => EditIdea::route('/{record}/edit'),
        ];
    }

    /**
     * Get the Eloquent query for this resource.
     *
     * WHY INCLUDE SOFT DELETED?
     * - Show trashed filter option in table
     * - Allow viewing/restoring archived ideas
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes();
    }
}
