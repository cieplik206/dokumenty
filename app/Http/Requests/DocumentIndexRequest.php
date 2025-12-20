<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'binder' => ['nullable', 'integer', 'exists:binders,id'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'Fraza wyszukiwania moze miec maksymalnie 255 znakow.',
            'binder.exists' => 'Wybrany segregator nie istnieje.',
            'category.exists' => 'Wybrana kategoria nie istnieje.',
            'from.date' => 'Data od musi byc poprawna data.',
            'to.date' => 'Data do musi byc poprawna data.',
            'to.after_or_equal' => 'Data do nie moze byc wczesniejsza niz od.',
        ];
    }
}
