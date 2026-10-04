<?php

namespace Database\Seeders;

use App\Models\Jugador;
use Illuminate\Database\Seeder;

/**
 * Cuentas para probar el sitio a mano sin tener que registrarse cada vez.
 *
 * La contraseña es conocida y este repositorio es público, así que estas
 * cuentas no se crean nunca en producción.
 */
class CuentasDePruebaSeeder extends Seeder
{
    public const PASSWORD = 'valecuatro';

    /** Apodo => email. El dominio .test está reservado: no existe en internet. */
    public const CUENTAS = [
        'Román' => 'roman@valecuatro.test',
        'La Tana' => 'tana@valecuatro.test',
        'El Zurdo' => 'zurdo@valecuatro.test',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Las cuentas de prueba no se crean en producción.');

            return;
        }

        foreach (self::CUENTAS as $apodo => $email) {
            // Se busca por email para que sembrar dos veces no duplique ni falle.
            Jugador::updateOrCreate(['email' => $email], ['apodo' => $apodo, 'password' => self::PASSWORD]);
        }
    }
}
