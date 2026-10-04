<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistroRequest extends FormRequest
{
    use ValidaApodo;

    /**
     * El email se guarda en minúsculas para que "Ana@x.com" y "ana@x.com" sean la misma cuenta.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower($this->email)]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'apodo' => $this->reglasDeApodo(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('jugadores', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensajesDeApodo(),
            'email.required' => 'Escribí tu email.',
            'email.email' => 'Ese email no parece válido.',
            'email.max' => 'El email es demasiado largo.',
            'email.unique' => 'Ya hay una cuenta con ese email. Ingresá con ella.',
            'password.required' => 'Elegí una contraseña.',
            'password.min' => 'La contraseña tiene que tener 8 caracteres o más.',
        ];
    }
}
