<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentIntakeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('messages')) {
            return;
        }

        if ($this->has('message')) {
            $this->merge([
                'messages' => [$this->input('message')],
            ]);
        }
    }

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
            'messages' => ['required', 'array'],
            'messages.*.parts' => ['nullable', 'array'],
            'messages.*.parts.*.type' => ['required', 'string'],
            'messages.*.parts.*.url' => ['nullable', 'string'],
            'messages.*.parts.*.mediaType' => ['nullable', 'string'],
        ];
    }
}
