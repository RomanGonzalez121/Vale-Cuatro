<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Las reglas del apodo, que son las mismas al registrarse y al cambiarlo.
 */
trait ValidaApodo
{
    /**
     * @return list<mixed>
     */
    protected function reglasDeApodo(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:20',
            // Palabras de letras, números, guion o guion bajo, separadas por un solo espacio.
            'regex:/^[\pL\pN_-]+(?: [\pL\pN_-]+)*$/u',
            // "Invitado 12345" es el apodo de quien juega sin cuenta.
            'not_regex:/^invitado/i',
            Rule::unique('jugadores', 'apodo')->ignore($this->user()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDeApodo(): array
    {
        return [
            'apodo.required' => 'Escribí un apodo.',
            'apodo.min' => 'El apodo tiene que tener 3 caracteres o más.',
            'apodo.max' => 'El apodo puede tener hasta 20 caracteres.',
            'apodo.regex' => 'Usá solo letras, números, guiones y un espacio entre palabras.',
            'apodo.not_regex' => 'Los apodos que empiezan con "Invitado" están reservados.',
            'apodo.unique' => 'Ese apodo ya está en uso. Probá con otro.',
        ];
    }
}
