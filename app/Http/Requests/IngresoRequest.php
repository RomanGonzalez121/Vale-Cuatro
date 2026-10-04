<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class IngresoRequest extends FormRequest
{
    /** Intentos fallidos que se permiten antes de hacer esperar. */
    private const INTENTOS = 5;

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribí tu email.',
            'email.email' => 'Ese email no parece válido.',
            'password.required' => 'Escribí tu contraseña.',
        ];
    }

    /**
     * Intenta abrir la sesión. Cada fallo cuenta para el límite; al acertar, se borra la cuenta.
     *
     * @throws ValidationException
     */
    public function autenticar(): void
    {
        $clave = $this->claveDelLimite();

        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Probá de nuevo en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('recordarme'))) {
            RateLimiter::hit($clave);

            // El mismo mensaje para email desconocido y contraseña errada: no se revela qué cuentas existen.
            throw ValidationException::withMessages([
                'email' => 'El email o la contraseña no coinciden.',
            ]);
        }

        RateLimiter::clear($clave);

        // Quien venía jugando como invitado tiene guardada la cookie de
        // "recordarme" de ese invitado. Si no pidió que lo recuerden, se borra:
        // si no, al vencer la sesión volvería a entrar como aquel invitado.
        if (! $this->boolean('recordarme')) {
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
        }
    }

    /**
     * El límite se cuenta por email y por dirección, para que nadie le trabe la cuenta a otro.
     */
    private function claveDelLimite(): string
    {
        return 'ingreso:'.$this->string('email').'|'.$this->ip();
    }
}
