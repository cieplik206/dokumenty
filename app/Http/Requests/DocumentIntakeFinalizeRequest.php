<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentIntakeFinalizeRequest extends FormRequest
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
            'storage_type' => ['required', 'string', Rule::in(['paper', 'electronic'])],
            'binder_id' => ['nullable', 'required_if:storage_type,paper', 'integer', 'exists:binders,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'storage_type.required' => 'Wybierz typ dokumentu.',
            'storage_type.in' => 'Typ dokumentu musi byc papierowy lub elektroniczny.',
            'binder_id.required_if' => 'Wybierz segregator dla dokumentu papierowego.',
            'binder_id.exists' => 'Wybrany segregator nie istnieje.',
        ];
    }
}
