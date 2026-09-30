<?php

namespace App\Filament\Resources\SupportRequests\RelationManagers;

use App\Filament\Actions\AddCommentAction;
use App\Models\SupportRequest;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * HU06: comentarios de avance de una solicitud. Solo se pueden agregar; una vez
 * creados son inmutables, asi que la tabla no ofrece editar ni borrar (BR-10).
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

    /**
     * En una pagina de detalle Filament deja los Relation Managers en solo
     * lectura; aqui se necesita false para poder agregar un comentario.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * La pestaña ni siquiera aparece para quien no puede ver la solicitud.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof SupportRequest
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author.name')
                    ->label('Autor'),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Comentario')
                    ->wrap(),
            ])
            // Se leen como una conversacion: del mas antiguo al mas reciente.
            ->defaultSort('created_at')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('author'))
            // Sin editar ni borrar: el comentario es inmutable (BR-10).
            ->recordActions([])
            ->bulkActions([])
            ->headerActions([
                AddCommentAction::make($this->getOwnerRecord()),
            ]);
    }
}
