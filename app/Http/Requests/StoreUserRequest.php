<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'is_admin' => ['required', 'boolean'],
            'appearance' => ['required', 'string', Rule::in(['light', 'dark', 'system'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Podaj imie i nazwisko.',
            'name.max' => 'Imie i nazwisko moze miec maksymalnie 255 znakow.',
            'email.required' => 'Podaj adres email.',
            'email.email' => 'Podaj poprawny adres email.',
            'email.unique' => 'Ten adres email jest juz zajety.',
            'password.required' => 'Podaj haslo.',
            'password.confirmed' => 'Hasla musza byc takie same.',
            'is_admin.required' => 'Wybierz, czy uzytkownik ma byc administratorem.',
            'is_admin.boolean' => 'Nieprawidlowa wartosc dla roli administratora.',
            'appearance.required' => 'Wybierz wyglad aplikacji.',
            'appearance.in' => 'Wybrany tryb wygladu jest nieprawidlowy.',
        ];
    }
}
