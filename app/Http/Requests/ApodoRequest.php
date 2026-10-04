<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApodoRequest extends FormRequest
{
    use ValidaApodo;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'apodo' => $this->reglasDeApodo(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesDeApodo();
    }
}
