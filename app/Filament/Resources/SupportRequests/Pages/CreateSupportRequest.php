<?php

namespace App\Filament\Resources\SupportRequests\Pages;

use App\Filament\Resources\SupportRequests\SupportRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportRequest extends CreateRecord
{
    protected static string $resource = SupportRequestResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = 'Nuevo';
        $data['priority'] = 'Media';

        return $data;
    }
}