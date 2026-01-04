<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ideas\Schemas;

use App\Enums\IdeaPriority;
use App\Enums\IdeaStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * =============================================================================
 * Idea Form Configuration
 * =============================================================================
 * Configures the form for creating/editing ideas.
 *
 * SECTIONS:
 * 1. Basic Info - Title, description, category
 * 2. Pipeline - Status, priority
 * 3. Time Management - Deadline, duration estimate
 * 4. Additional - Tags, notes
 *
 * WHY RICH EDITOR FOR DESCRIPTION?
 * - Ideas often need formatting (lists, bold, etc.)
 * - Better for longer explanations
 *
 * WHY TEXTAREA FOR NOTES?
 * - Notes are internal, don't need formatting
 * - Simpler, faster to use
 *
 * @group Ideas
 * =============================================================================
 */
class IdeaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Section 1: Basic Information
                Section::make('Basic Information')
                    ->description('Core details about the idea')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('What\'s the idea?')
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Description')
                            ->placeholder('Describe the idea in detail...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),

                        TextInput::make('category')
                            ->label('Category')
                            ->placeholder('e.g., Feature, Bug, Enhancement')
                            ->maxLength(100)
                            ->datalist([
                                'Feature',
                                'Bug Fix',
                                'Enhancement',
                                'Research',
                                'Documentation',
                                'Refactoring',
                                'Infrastructure',
                            ]),
                    ])
                    ->columns(2),

                // Section 2: Pipeline Status
                Section::make('Pipeline')
                    ->description('Current status and priority')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(IdeaStatus::class)
                            ->default(IdeaStatus::TODO)
                            ->required()
                            ->native(false),

                        Select::make('priority')
                            ->label('Priority')
                            ->options(IdeaPriority::class)
                            ->default(IdeaPriority::MEDIUM)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                // Section 3: Time Management
                Section::make('Time Management')
                    ->description('Deadline and duration estimates')
                    ->schema([
                        DateTimePicker::make('deadline')
                            ->label('Deadline')
                            ->placeholder('When should this be done?')
                            ->native(false)
                            ->displayFormat('M j, Y g:i A')
                            ->minDate(now()),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('duration_value')
                                    ->label('Duration Estimate')
                                    ->numeric()
                                    ->placeholder('e.g., 2')
                                    ->minValue(1)
                                    ->maxValue(9999)
                                    ->afterStateHydrated(function (TextInput $component, $state, $record) {
                                        if ($record && $record->duration_minutes) {
                                            // Convert minutes to most appropriate unit
                                            $minutes = $record->duration_minutes;
                                            if ($minutes >= 1440) { // 24 * 60
                                                $component->state(intval($minutes / 1440));
                                            } elseif ($minutes >= 60) {
                                                $component->state(intval($minutes / 60));
                                            } else {
                                                $component->state($minutes);
                                            }
                                        }
                                    }),

                                Select::make('duration_unit')
                                    ->label('Unit')
                                    ->options([
                                        'minutes' => 'Minutes',
                                        'hours' => 'Hours',
                                        'days' => 'Days',
                                    ])
                                    ->default('hours')
                                    ->native(false)
                                    ->afterStateHydrated(function (Select $component, $state, $record) {
                                        if ($record && $record->duration_minutes) {
                                            $minutes = $record->duration_minutes;
                                            if ($minutes >= 1440) {
                                                $component->state('days');
                                            } elseif ($minutes >= 60) {
                                                $component->state('hours');
                                            } else {
                                                $component->state('minutes');
                                            }
                                        }
                                    }),
                            ]),
                    ])
                    ->columns(2),

                // Section 4: Additional Information
                Section::make('Additional')
                    ->description('Tags and notes for organization')
                    ->schema([
                        TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags...')
                            ->separator(',')
                            ->suggestions([
                                'frontend',
                                'backend',
                                'api',
                                'database',
                                'ui',
                                'ux',
                                'security',
                                'performance',
                                'testing',
                            ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Internal notes, links, references...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    /**
     * Convert duration value and unit to minutes for storage.
     *
     * WHY STORE AS MINUTES?
     * - Single column, simple storage
     * - Easy to convert to any unit in display layer
     * - No floating point issues
     */
    public static function convertDurationToMinutes(?int $value, ?string $unit): ?int
    {
        if (! $value || ! $unit) {
            return null;
        }

        return match ($unit) {
            'minutes' => $value,
            'hours' => $value * 60,
            'days' => $value * 60 * 24,
            default => $value,
        };
    }
}
