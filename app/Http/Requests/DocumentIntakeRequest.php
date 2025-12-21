<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentIntakeRequest extends FormRequest
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
            'scans' => ['required', 'array', 'min:1'],
            'scans.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scans.required' => 'Dodaj przynajmniej jeden skan do analizy.',
            'scans.array' => 'Skan musi byc lista plikow.',
            'scans.min' => 'Dodaj przynajmniej jeden skan do analizy.',
            'scans.*.file' => 'Kazdy skan musi byc plikiem.',
            'scans.*.mimes' => 'Dozwolone formaty skanow: pdf, jpg, jpeg, png.',
            'scans.*.max' => 'Kazdy skan moze miec maksymalnie 10MB.',
        ];
    }
}
