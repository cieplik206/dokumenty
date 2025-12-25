<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentIntakeBulkDestroyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Wybierz analizy do usuniecia.',
            'ids.array' => 'Lista analiz musi byc tablica.',
            'ids.min' => 'Wybierz przynajmniej jedna analize do usuniecia.',
            'ids.*.integer' => 'Id analizy musi byc liczba.',
            'ids.*.distinct' => 'Id analizy nie moze sie powtarzac.',
        ];
    }
}
