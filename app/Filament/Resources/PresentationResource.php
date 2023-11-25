<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresentationResource\Pages;
use App\Models\Presentation;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class PresentationResource extends Resource
{
    protected static ?string $model = Presentation::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationGroup = 'Main';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(3)
                    ->schema([
                        Forms\Components\MarkdownEditor::make('content')
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        Section::make('Details')
                            ->description('All the metadata related to your presentation.')
                            ->columnSpan(1)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state)))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Autogenerated')
                                    ->unique(
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('user_id', $get('user_id')),
                                    )
                                    ->maxLength(255),
                                Forms\Components\Select::make('user_id')
                                    ->relationship('user', 'name')
                                    ->hidden(fn () => ! auth()->user()->isAdministrator())
                                    ->searchable()
                                    ->default(auth()->id()),
                                Forms\Components\Hidden::make('user_id')
                                    ->default(auth()->id()),
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Published')
                                    ->helperText('You can always view your own presentations, but if they aren\'t published, then no one else can.'),
                                Forms\Components\Textarea::make('description')
                                    ->helperText(
                                        'A short abstract of your presentation. '
                                        .'This will be only be seen when sharing via social media. '
                                        .'Limit is 160 characters.'
                                    )->placeholder('i.e. In this talk by Abraham Lincoln, we explore yada yada...')
                                    ->maxLength(160),
                                SpatieMediaLibraryFileUpload::make('thumbnail')
                                    ->collection('thumbnail')
                                    ->image()
                                    ->imageEditor()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('1.91:1')
                                    ->imageResizeTargetWidth('1200')
                                    ->imageResizeTargetHeight('630')
                                    ->rules([
                                        function () {
                                            return function (string $attribute, $value, Closure $fail) {
                                                if (auth()->user()->can('upload')) {
                                                    return;
                                                }

                                                $fail(config('app-upload.limit_exceeded_message'));
                                            };
                                        },
                                    ])
                                    ->helperText(
                                        'Image Size: 1200 x 630. This will be only be seen when sharing via social media. '
                                        .'If omitted, the default Simple Slides thumbnail will be used.'
                                    ),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('thumbnail')->collection('thumbnail'),
                Tables\Columns\TextColumn::make('title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Published')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->hidden(fn () => ! auth()->user()->isAdministrator())
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('View')
                    ->url(fn (Presentation $record): string => route('presentations.show', [
                        'user' => $record->user->username,
                        'slug' => $record->slug,
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPresentations::route('/'),
            'create' => Pages\CreatePresentation::route('/create'),
            'edit' => Pages\EditPresentation::route('/{record}/edit'),
        ];
    }

    /**
     * Modify the base eloquent table query.
     *
     * @return Builder<Presentation>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (! auth()->user()->isAdministrator()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }
}
