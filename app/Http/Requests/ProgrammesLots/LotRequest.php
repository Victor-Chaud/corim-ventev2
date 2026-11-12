<?php

namespace App\Http\Requests\ProgrammesLots;

use Illuminate\Foundation\Http\FormRequest;

class LotRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:20'],
            'cat' => ['nullable', 'string', 'max:20'],
            'prix' => ['nullable', 'string', 'max:100'],
            'prix_m2' => ['nullable', 'string', 'max:100'],
            'etage' => ['nullable', 'string', 'max:50'],
            'rdc' => ['nullable', 'string', 'max:50'],
            'jardin' => ['nullable', 'string', 'max:50'],
            'terrasse' => ['nullable', 'string', 'max:50'],
            'superficie' => ['nullable', 'string', 'max:50'],
            'stationnement' => ['nullable', 'string', 'max:100'],
            'exposition' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:65535'],
            'numero' => ['nullable', 'string', 'max:150'],
            'cellier' => ['nullable', 'string', 'max:150'],
            'accession' => ['nullable', 'string', 'max:250'],
            'option' => ['nullable', 'array'],
            'option.*' => ['string'],
        ];
    }
}
