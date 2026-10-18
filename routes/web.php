<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\ExerciseController;
use App\Controllers\FileController;
use App\Controllers\TeamController;
use App\Controllers\TrainerController;
use App\Controllers\TrainingController;
use App\Services\Router;

return static function (Router $r): void {
    // Públiques
    $r->get('/acces', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/acces', [AuthController::class, 'login'], ['guest']);
    $r->get('/contrasenya/oblidada', [AuthController::class, 'showForgot'], ['guest']);
    $r->post('/contrasenya/oblidada', [AuthController::class, 'forgot'], ['guest']);
    $r->get('/contrasenya/restablir/{token}', [AuthController::class, 'showReset']);
    $r->post('/contrasenya/restablir/{token}', [AuthController::class, 'reset']);
    $r->get('/activar/{token}', [AuthController::class, 'showActivate']);
    $r->post('/activar/{token}', [AuthController::class, 'activate']);
    $r->get('/logotip', [FileController::class, 'logo']);

    // Autenticades (administrador i entrenador)
    $r->post('/sortir', [AuthController::class, 'logout'], ['auth']);
    $r->get('/', [DashboardController::class, 'index'], ['auth']);
    $r->get('/compte', [AuthController::class, 'profile'], ['auth']);
    $r->get('/compte/contrasenya', [AuthController::class, 'showChangePassword'], ['auth']);
    $r->post('/compte/contrasenya', [AuthController::class, 'changePassword'], ['auth']);

    $r->get('/exercicis', [ExerciseController::class, 'index'], ['auth']);
    $r->get('/exercicis/nou', [ExerciseController::class, 'create'], ['admin']);
    $r->post('/exercicis', [ExerciseController::class, 'store'], ['admin']);
    $r->get('/exercicis/{id}', [ExerciseController::class, 'show'], ['auth']);
    $r->get('/exercicis/{id}/editar', [ExerciseController::class, 'edit'], ['admin']);
    $r->post('/exercicis/{id}', [ExerciseController::class, 'update'], ['admin']);
    $r->post('/exercicis/{id}/eliminar', [ExerciseController::class, 'delete'], ['admin']);
    $r->post('/fitxers/exercici/{id}/eliminar', [ExerciseController::class, 'deleteFile'], ['admin']);
    $r->get('/fitxers/exercici/{id}', [FileController::class, 'exercise'], ['auth']);

    $r->get('/entrenaments', [TrainingController::class, 'index'], ['auth']);
    $r->get('/entrenaments/nou', [TrainingController::class, 'create'], ['admin']);
    $r->post('/entrenaments', [TrainingController::class, 'store'], ['admin']);
    $r->get('/entrenaments/{id}', [TrainingController::class, 'show'], ['auth']);
    $r->get('/entrenaments/{id}/editar', [TrainingController::class, 'edit'], ['admin']);
    $r->post('/entrenaments/{id}', [TrainingController::class, 'update'], ['admin']);
    $r->post('/entrenaments/{id}/estat', [TrainingController::class, 'status'], ['admin']);
    $r->post('/entrenaments/{id}/eliminar', [TrainingController::class, 'delete'], ['admin']);
    $r->post('/fitxers/entrenament/{id}/eliminar', [TrainingController::class, 'deleteFile'], ['admin']);
    $r->get('/fitxers/entrenament/{id}', [FileController::class, 'training'], ['auth']);

    // Administració
    $r->get('/entrenadors', [TrainerController::class, 'index'], ['admin']);
    $r->get('/entrenadors/nou', [TrainerController::class, 'create'], ['admin']);
    $r->post('/entrenadors', [TrainerController::class, 'store'], ['admin']);
    $r->get('/entrenadors/{id}/editar', [TrainerController::class, 'edit'], ['admin']);
    $r->post('/entrenadors/{id}', [TrainerController::class, 'update'], ['admin']);
    $r->post('/entrenadors/{id}/estat', [TrainerController::class, 'toggle'], ['admin']);
    $r->post('/entrenadors/{id}/activacio', [TrainerController::class, 'activation'], ['admin']);

    $r->get('/categories', [CategoryController::class, 'index'], ['admin']);
    $r->get('/categories/nova', [CategoryController::class, 'create'], ['admin']);
    $r->post('/categories', [CategoryController::class, 'store'], ['admin']);
    $r->get('/categories/{id}/editar', [CategoryController::class, 'edit'], ['admin']);
    $r->post('/categories/{id}', [CategoryController::class, 'update'], ['admin']);
    $r->post('/categories/{id}/eliminar', [CategoryController::class, 'delete'], ['admin']);

    $r->get('/equips', [TeamController::class, 'index'], ['admin']);
    $r->get('/equips/nou', [TeamController::class, 'create'], ['admin']);
    $r->post('/equips', [TeamController::class, 'store'], ['admin']);
    $r->get('/equips/{id}/editar', [TeamController::class, 'edit'], ['admin']);
    $r->post('/equips/{id}', [TeamController::class, 'update'], ['admin']);
    $r->post('/equips/{id}/eliminar', [TeamController::class, 'delete'], ['admin']);

    $r->get('/admin/correu', [AdminController::class, 'mailForm'], ['admin']);
    $r->post('/admin/correu', [AdminController::class, 'mailSave'], ['admin']);
    $r->post('/admin/correu/prova', [AdminController::class, 'mailTest'], ['admin']);
    $r->get('/admin/logotip', [AdminController::class, 'logoForm'], ['admin']);
    $r->post('/admin/logotip', [AdminController::class, 'logoSave'], ['admin']);
    $r->get('/admin/registre', [AdminController::class, 'audit'], ['admin']);
    $r->get('/admin/correus', [AdminController::class, 'emails'], ['admin']);
};
