<?php

use App\Http\Controllers\InvitacionController;
use App\Http\Controllers\JugarController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\PaginaController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SalaController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PaginaController::class, 'portada'])->name('portada');
Route::get('/ranking', [PaginaController::class, 'ranking'])->name('ranking');
Route::get('/historial', [PaginaController::class, 'historial'])->name('historial');
Route::get('/como-se-juega', [PaginaController::class, 'comoSeJuega'])->name('como-se-juega');
Route::get('/identidad', [PaginaController::class, 'identidad'])->name('identidad');
Route::get('/modos', [PaginaController::class, 'modos'])->name('modos');

// Entrar a jugar: con cuenta, o como invitado creado en el momento.
Route::post('/jugar', JugarController::class)->middleware('throttle:10,1')->name('jugar');
Route::get('/mesa', [MesaController::class, 'ver'])->middleware('auth')->name('mesa');

// Jugar con otra persona: se abre una sala y se le manda el link. El código tiene 16 caracteres sorteados.
Route::post('/invitar', [SalaController::class, 'crear'])->middleware('throttle:10,1')->name('invitar');
Route::get('/invitacion/{codigo}', [InvitacionController::class, 'ver'])->where('codigo', '[a-z0-9]{16}')->middleware('throttle:60,1')->name('invitacion');
Route::post('/invitacion/{codigo}', [InvitacionController::class, 'entrar'])->where('codigo', '[a-z0-9]{16}')->middleware('throttle:10,1')->name('invitacion.entrar');

Route::middleware(['auth', 'throttle:240,1'])->where(['codigo' => '[a-z0-9]{16}'])->group(function () {
    Route::get('/sala/{codigo}', [SalaController::class, 'ver'])->name('sala');
    Route::get('/sala/{codigo}/estado', [SalaController::class, 'estado'])->name('sala.estado');
    Route::post('/sala/{codigo}/cancelar', [SalaController::class, 'cancelar'])->name('sala.cancelar');
});

// Lo que se hace desde la mesa: siempre sobre la partida en curso de quien hace el pedido.
Route::middleware(['auth', 'throttle:240,1'])->group(function () {
    Route::get('/mesa/estado', [MesaController::class, 'estado'])->name('mesa.estado');
    Route::post('/mesa/accion', [MesaController::class, 'accion'])->name('mesa.accion');
    Route::post('/mesa/repartir', [MesaController::class, 'repartir'])->name('mesa.repartir');
    Route::post('/mesa/abandonar', [MesaController::class, 'abandonar'])->name('mesa.abandonar');
    Route::post('/mesa/bot', [MesaController::class, 'despertarAlBot'])->name('mesa.bot');
});

Route::middleware('sin-cuenta')->group(function () {
    Route::get('/registro', [RegistroController::class, 'formulario'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'registrar'])->middleware('throttle:10,1');
    Route::get('/ingresar', [SesionController::class, 'formulario'])->name('ingresar');
    Route::post('/ingresar', [SesionController::class, 'ingresar']);
});

Route::post('/salir', [SesionController::class, 'salir'])->middleware('auth')->name('salir');

Route::middleware('con-cuenta')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'ver'])->name('perfil');
    Route::put('/perfil', [PerfilController::class, 'guardar']);
});
