<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBinderRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj nazwe segregatora.',
            'name.max' => 'Nazwa segregatora moze miec maksymalnie 255 znakow.',
            'location.max' => 'Lokalizacja moze miec maksymalnie 255 znakow.',
            'description.max' => 'Opis moze miec maksymalnie 2000 znakow.',
            'sort_order.integer' => 'Kolejnosc musi byc liczba.',
            'sort_order.min' => 'Kolejnosc nie moze byc mniejsza od 0.',
        ];
    }
}
