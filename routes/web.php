<?php

use App\Http\Controllers\PaginaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PaginaController::class, 'portada'])->name('portada');
Route::get('/mesa', [PaginaController::class, 'mesa'])->name('mesa');
Route::get('/ranking', [PaginaController::class, 'ranking'])->name('ranking');
Route::get('/historial', [PaginaController::class, 'historial'])->name('historial');
Route::get('/como-se-juega', [PaginaController::class, 'comoSeJuega'])->name('como-se-juega');
Route::get('/identidad', [PaginaController::class, 'identidad'])->name('identidad');
