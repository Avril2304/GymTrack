<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EjercicioController;
use App\Http\Controllers\RutinaController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\GimnasioController;
use App\Http\Controllers\SolicitudController;

/*
|--------------------------------------------------------------------------
| Rutas API de GymTrack (Entrega 4 - Laravel & Eloquent ORM)
|--------------------------------------------------------------------------
*/

// Rutas de Autenticación
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Rutas de Ejercicios
Route::get('/ejercicios', [EjercicioController::class, 'index']);

// Rutas de Rutinas
Route::get('/rutinas', [RutinaController::class, 'obtenerRutinas']);
Route::get('/mis-rutinas', [RutinaController::class, 'misRutinas']);
Route::get('/rutina-detalle', [RutinaController::class, 'detalle']);
Route::post('/crear-rutina', [RutinaController::class, 'store']);
Route::post('/toggle-favorito', [RutinaController::class, 'toggleFavorito']);
Route::post('/eliminar-rutina', [RutinaController::class, 'destroy']);

// Rutas de Historial / Entrenamientos
Route::get('/historial', [HistorialController::class, 'index']);
Route::post('/guardar-entrenamiento', [HistorialController::class, 'store']);
Route::post('/eliminar-historial', [HistorialController::class, 'destroy']);

// Rutas de Perfil de Usuario
Route::get('/perfil', [PerfilController::class, 'show']);
Route::post('/editar-perfil', [PerfilController::class, 'update']);
Route::post('/subir-foto-perfil', [PerfilController::class, 'subirFoto']);

// Rutas de Administración
Route::get('/admin/usuarios', [AdminController::class, 'usuarios']);
Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

// Rutas de Gimnasio
Route::post('/admin/asignar-gimnasio', [GimnasioController::class, 'asignarGimnasio']);
Route::get('/admin/gimnasios', [GimnasioController::class, 'listar']);

// Rutas de Gimnasio propio y alta de socios (crear/buscar gimnasio, solicitudes de ingreso)
Route::post('/gimnasio/crear', [SolicitudController::class, 'crearGimnasio']);
Route::get('/gimnasio/mio', [SolicitudController::class, 'miGimnasio']);
Route::get('/gimnasio/buscar', [SolicitudController::class, 'buscarGimnasio']);
Route::post('/gimnasio/salir', [SolicitudController::class, 'salirGimnasio']);
Route::post('/solicitudes/crear', [SolicitudController::class, 'crear']);
Route::get('/solicitudes/mia', [SolicitudController::class, 'mia']);
Route::post('/solicitudes/cancelar', [SolicitudController::class, 'cancelar']);
Route::get('/solicitudes/pendientes', [SolicitudController::class, 'pendientes']);
Route::post('/solicitudes/resolver', [SolicitudController::class, 'resolver']);
Route::get('/admin/buscar-usuario', [SolicitudController::class, 'buscarUsuario']);

// Ruta de Coach IA Virtual (OpenAI)
Route::post('/coach/chat', [CoachController::class, 'chat']);


