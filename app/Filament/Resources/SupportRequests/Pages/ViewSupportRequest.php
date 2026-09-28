<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use App\Filament\Actions\PrioritizeAction;
use App\Filament\Resources\SupportRequests\SupportRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportRequest extends ViewRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrioritizeAction::make(),
        ];
    }
}
