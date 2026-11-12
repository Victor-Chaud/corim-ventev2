<?php

namespace App\Http\Requests\ProgrammesLots;

use Illuminate\Foundation\Http\FormRequest;

class ProgrammeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'titre' => ['nullable', 'string', 'max:100'],
            'livraison' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'dept' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:65535'],
            'action' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'filer' => ['nullable', 'array'],
        ];
    }
}
