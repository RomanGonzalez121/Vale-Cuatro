<?php

use App\Http\Controllers\AdministracionController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\InvitacionController;
use App\Http\Controllers\JugarController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\PaginaController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\RevanchaController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

/*
 | Los límites de pedidos por minuto llevan un nombre al final (throttle:10,1,entrar). Sin él, Laravel
 | cuenta todos los pedidos de una misma persona en una sola cuenta, sea cual sea la ruta: los que hace
 | la mesa mientras se juega gastaban los diez de "Jugar" e "Invitar", y tocar esos botones justo después
 | de una partida contestaba "demasiados pedidos". Con nombre, cada grupo lleva su propia cuenta.
 */

Route::get('/', [PaginaController::class, 'portada'])->name('portada');
Route::get('/ranking', [RankingController::class, 'ver'])->name('ranking');
Route::get('/historial', [HistorialController::class, 'lista'])->name('historial');
Route::get('/historial/{partida}', [HistorialController::class, 'ver'])->whereNumber('partida')->middleware('auth')->name('historial.ver');
Route::get('/historial/{partida}/cuadros', [HistorialController::class, 'cuadros'])->whereNumber('partida')->middleware(['auth', 'throttle:120,1,historial'])->name('historial.cuadros');
Route::get('/como-se-juega', [PaginaController::class, 'comoSeJuega'])->name('como-se-juega');
Route::get('/identidad', [PaginaController::class, 'identidad'])->name('identidad');
Route::get('/modos', [PaginaController::class, 'modos'])->name('modos');

// Entrar a jugar: con cuenta, o como invitado creado en el momento.
Route::post('/jugar', JugarController::class)->middleware('throttle:10,1,entrar')->name('jugar');
Route::get('/mesa', [MesaController::class, 'ver'])->middleware('auth')->name('mesa');

// Jugar con otra persona: se abre una sala y se le manda el link. El código tiene 16 caracteres sorteados.
Route::post('/invitar', [SalaController::class, 'crear'])->middleware('throttle:10,1,entrar')->name('invitar');
Route::get('/invitacion/{codigo}', [InvitacionController::class, 'ver'])->where('codigo', '[a-z0-9]{16}')->middleware('throttle:60,1,invitacion')->name('invitacion');
Route::post('/invitacion/{codigo}', [InvitacionController::class, 'entrar'])->where('codigo', '[a-z0-9]{16}')->middleware('throttle:10,1,entrar')->name('invitacion.entrar');

Route::middleware(['auth', 'throttle:240,1,sala'])->where(['codigo' => '[a-z0-9]{16}'])->group(function () {
    Route::get('/sala/{codigo}', [SalaController::class, 'ver'])->name('sala');
    Route::get('/sala/{codigo}/estado', [SalaController::class, 'estado'])->name('sala.estado');
    Route::post('/sala/{codigo}/cancelar', [SalaController::class, 'cancelar'])->name('sala.cancelar');
});

// Lo que se hace desde la mesa: siempre sobre la partida en curso de quien hace el pedido.
Route::middleware(['auth', 'throttle:240,1,mesa'])->group(function () {
    Route::get('/mesa/estado', [MesaController::class, 'estado'])->name('mesa.estado');
    Route::post('/mesa/accion', [MesaController::class, 'accion'])->name('mesa.accion');
    Route::post('/mesa/repartir', [MesaController::class, 'repartir'])->name('mesa.repartir');
    Route::post('/mesa/abandonar', [MesaController::class, 'abandonar'])->name('mesa.abandonar');
    Route::post('/mesa/bot', [MesaController::class, 'despertarAlBot'])->name('mesa.bot');
    Route::post('/mesa/plazo', [MesaController::class, 'resolverPlazo'])->name('mesa.plazo');
    Route::post('/mesa/presente', [MesaController::class, 'presente'])->name('mesa.presente');
});

// La revancha, desde el final de una partida. Cada pedido dice de qué partida habla, y tiene que ser de quien lo hace.
Route::middleware(['auth', 'throttle:240,1,revancha'])->group(function () {
    Route::get('/revancha', [RevanchaController::class, 'estado'])->name('revancha.estado');
    Route::post('/revancha/pedir', [RevanchaController::class, 'pedir'])->name('revancha.pedir');
    Route::post('/revancha/aceptar', [RevanchaController::class, 'aceptar'])->name('revancha.aceptar');
    Route::post('/revancha/rechazar', [RevanchaController::class, 'rechazar'])->name('revancha.rechazar');
    Route::post('/revancha/cancelar', [RevanchaController::class, 'cancelar'])->name('revancha.cancelar');
});

// El panel de administración. A quien no administra el sitio estas direcciones le contestan lo mismo que
// una página que no está. No llevan límite de pedidos: solo las pasa una cuenta, y su ingreso ya tiene el suyo.
// La seguridad del panel no depende de que nadie sepa que existe: este archivo es público.
Route::middleware('solo-administracion')->prefix('administracion')->group(function () {
    Route::get('/', [AdministracionController::class, 'ver'])->name('administracion');
    Route::post('/partidas/{partida}/cerrar', [AdministracionController::class, 'cerrarPartida'])->whereNumber('partida')->name('administracion.partida.cerrar');
    Route::post('/trabajos/{trabajo}/reintentar', [AdministracionController::class, 'reintentarTrabajo'])->where('trabajo', '[0-9a-fA-F-]{1,40}')->name('administracion.trabajo.reintentar');
    Route::post('/trabajos/{trabajo}/descartar', [AdministracionController::class, 'descartarTrabajo'])->where('trabajo', '[0-9a-fA-F-]{1,40}')->name('administracion.trabajo.descartar');
    Route::post('/limpieza', [AdministracionController::class, 'limpiar'])->name('administracion.limpiar');
    Route::post('/jugadores/{jugador}/ocultar-apodo', [AdministracionController::class, 'ocultarApodo'])->whereNumber('jugador')->name('administracion.apodo.ocultar');
});

Route::middleware('sin-cuenta')->group(function () {
    Route::get('/registro', [RegistroController::class, 'formulario'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'registrar'])->middleware('throttle:10,1,registro');
    Route::get('/ingresar', [SesionController::class, 'formulario'])->name('ingresar');
    Route::post('/ingresar', [SesionController::class, 'ingresar']);
});

Route::post('/salir', [SesionController::class, 'salir'])->middleware('auth')->name('salir');

Route::middleware('con-cuenta')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'ver'])->name('perfil');
    Route::put('/perfil', [PerfilController::class, 'guardar']);
});
