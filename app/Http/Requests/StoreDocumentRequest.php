<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $decoded = [];

        foreach (['extracted_content', 'ai_metadata'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === '') {
                    $decoded[$field] = null;

                    continue;
                }

                $json = json_decode($trimmed, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded[$field] = $json;
                }
            }
        }

        if ($decoded !== []) {
            $this->merge($decoded);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_paper' => ['required', 'boolean'],
            'binder_id' => ['nullable', 'required_if:is_paper,1', 'integer', 'exists:binders,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'extracted_content' => ['nullable', 'array'],
            'ai_metadata' => ['nullable', 'array'],
            'scans' => ['nullable', 'array'],
            'scans.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_paper.required' => 'Wybierz typ dokumentu.',
            'is_paper.boolean' => 'Typ dokumentu musi byc poprawny.',
            'binder_id.required' => 'Wybierz segregator.',
            'binder_id.exists' => 'Wybrany segregator nie istnieje.',
            'category_id.required' => 'Wybierz kategorie.',
            'category_id.exists' => 'Wybrana kategoria nie istnieje.',
            'title.required' => 'Podaj tytul dokumentu.',
            'title.max' => 'Tytul moze miec maksymalnie 255 znakow.',
            'reference_number.max' => 'Numer referencyjny moze miec maksymalnie 255 znakow.',
            'issuer.max' => 'Wystawca moze miec maksymalnie 255 znakow.',
            'document_date.date' => 'Data dokumentu musi byc poprawna data.',
            'received_at.date' => 'Data otrzymania musi byc poprawna data.',
            'notes.max' => 'Notatki moga miec maksymalnie 4000 znakow.',
            'tags.max' => 'Tagi moga miec maksymalnie 1000 znakow.',
            'scans.array' => 'Skan musi byc lista plikow.',
            'scans.*.file' => 'Kazdy skan musi byc plikiem.',
            'scans.*.mimes' => 'Dozwolone formaty skanow: pdf, jpg, jpeg, png.',
            'scans.*.max' => 'Kazdy skan moze miec maksymalnie 10MB.',
        ];
    }
}
