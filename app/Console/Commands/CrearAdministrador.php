<?php

namespace App\Console\Commands;

use App\Administracion\Credenciales;
use App\Models\Jugador;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Deja lista la cuenta que administra el sitio, con el email y la contraseña del entorno
 * (ADMIN_EMAIL y ADMIN_PASSWORD, ver App\Administracion\Credenciales).
 *
 * Lo que diga el entorno es lo que vale, y se puede correr todas las veces que haga falta (el contenedor
 * lo corre en cada arranque):
 *
 * - la cuenta de ese email administra el sitio, y ninguna otra: si antes administraba otra, pierde el rol;
 * - si la cuenta no existe, la crea; si existe y ya está como dice el entorno, no la toca;
 * - si existe con otra contraseña, se la cambia y le cierra las sesiones abiertas;
 * - si existe y no administraba (alguien se registró antes con ese email, que el sitio no verifica), pasa
 *   a ser la de administración con la contraseña del entorno y sus sesiones se cierran: quien la había
 *   abierto no hereda el panel. Eso solo se puede garantizar con las sesiones guardadas en la base, como
 *   en el sitio publicado; si están en otro lado, el comando se niega y pide otro email;
 * - sin email o sin contraseña en el entorno, nadie administra el sitio.
 *
 * La contraseña no se pasa por la línea de comandos, donde quedaría a la vista en la lista de procesos.
 */
#[Signature('administrador:crear')]
#[Description('Crea o pone al día la cuenta de administración con ADMIN_EMAIL y ADMIN_PASSWORD')]
class CrearAdministrador extends Command
{
    /** Lo mínimo que tiene que medir la contraseña: esta cuenta puede cerrar partidas y tocar apodos. */
    public const LARGO_MINIMO = 10;

    public function handle(Credenciales $credenciales): int
    {
        $email = mb_strtolower(trim((string) $credenciales->email));
        $password = (string) $credenciales->password;

        if ($email === '' || $password === '') {
            $this->quitarElRolATodosMenos(null);
            $this->warn('Sin ADMIN_EMAIL y ADMIN_PASSWORD nadie administra el sitio.');

            return self::SUCCESS;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('ADMIN_EMAIL no tiene forma de email.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < self::LARGO_MINIMO) {
            $this->error('ADMIN_PASSWORD tiene que tener '.self::LARGO_MINIMO.' caracteres o más.');

            return self::FAILURE;
        }

        $cuenta = Jugador::query()->where('email', $email)->first();

        if ($cuenta !== null && ! $cuenta->esAdministrador() && ! $this->lasSesionesEstanEnLaBase()) {
            $this->error('Ya hay una cuenta con ese email, y con las sesiones fuera de la base no se puede dejar afuera a quien la tenga abierta. Usá otro email.');

            return self::FAILURE;
        }

        $mensaje = match (true) {
            $cuenta === null => $this->crear($email, $password, $credenciales->apodo),
            $cuenta->esAdministrador() && Hash::check($password, (string) $cuenta->password) => 'La cuenta de administración ya estaba al día.',
            default => $this->ponerAlDia($cuenta, $password),
        };

        $this->quitarElRolATodosMenos($email);
        $this->info($mensaje);

        return self::SUCCESS;
    }

    /**
     * Crea la cuenta con ese apodo. Si el apodo ya es de otro jugador, le suma un número.
     */
    private function crear(string $email, string $password, string $apodo): string
    {
        $apodo = Str::limit($apodo, 16, '');

        for ($intento = 1; $intento <= 50; $intento++) {
            try {
                $cuenta = new Jugador(['apodo' => $intento === 1 ? $apodo : "{$apodo} {$intento}", 'email' => $email, 'password' => $password]);
                $cuenta->es_administrador = true;
                $cuenta->save();

                break;
            } catch (UniqueConstraintViolationException $repetido) {
                // Si lo repetido es el email, otro arranque la creó recién: no hay nada más que hacer.
                if (Jugador::query()->where('email', $email)->exists()) {
                    break;
                }

                if ($intento === 50) {
                    throw $repetido;
                }
            }
        }

        return 'Cuenta de administración creada.';
    }

    private function ponerAlDia(Jugador $cuenta, string $password): string
    {
        $cuenta->password = $password;
        $cuenta->es_administrador = true;
        // Con otro valor acá, el "recordarme" de un navegador que había entrado antes deja de servir.
        $cuenta->setRememberToken(Str::random(60));
        $cuenta->save();

        return $this->cerrarSesionesDe([$cuenta->getKey()])
            ? 'Cuenta de administración puesta al día. Se cerraron sus sesiones abiertas.'
            : 'Cuenta de administración puesta al día. Sus sesiones abiertas no se pudieron cerrar: no están en la base.';
    }

    /**
     * La cuenta de administración es una sola: cualquier otra que tuviera el rol lo pierde, y se le
     * cierran las sesiones. Así, cambiar el email en el entorno le saca el panel a la cuenta anterior.
     */
    private function quitarElRolATodosMenos(?string $email): void
    {
        $otras = Jugador::query()
            ->where('es_administrador', true)
            ->when($email !== null, fn ($cuentas) => $cuentas->where(fn ($cuenta) => $cuenta->whereNull('email')->orWhere('email', '!=', $email)))
            ->pluck('id')
            ->all();

        if ($otras === []) {
            return;
        }

        Jugador::query()->whereKey($otras)->update(['es_administrador' => false, 'remember_token' => Str::random(60)]);
        $this->cerrarSesionesDe($otras);
        $this->warn(count($otras) === 1 ? 'Otra cuenta administraba el sitio: ya no.' : count($otras).' cuentas más administraban el sitio: ya no.');
    }

    /**
     * Borra las sesiones abiertas de esas cuentas. Devuelve false si no se puede: solo se llega a ellas
     * cuando están guardadas en la base.
     *
     * @param  list<int>  $cuentas
     */
    private function cerrarSesionesDe(array $cuentas): bool
    {
        if (! $this->lasSesionesEstanEnLaBase()) {
            return false;
        }

        DB::table(config('session.table', 'sessions'))->whereIn('user_id', $cuentas)->delete();

        return true;
    }

    private function lasSesionesEstanEnLaBase(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'));
    }
}
