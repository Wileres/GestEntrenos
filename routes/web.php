<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AdminUserController;
use App\Controllers\AttendanceController;
use App\Controllers\AttendanceReportController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\ExerciseController;
use App\Controllers\FileController;
use App\Controllers\IdentityController;
use App\Controllers\NotificationController;
use App\Controllers\PlayerController;
use App\Controllers\PwaController;
use App\Controllers\TeamController;
use App\Controllers\TrainerController;
use App\Controllers\TrainingController;
use App\Services\Router;

return static function (Router $r): void {
    // PWA (públiques)
    $r->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
    $r->get('/service-worker.js', [PwaController::class, 'serviceWorker']);
    $r->get('/offline', [PwaController::class, 'offline']);
    $r->get('/icones/{name}', [PwaController::class, 'icon']);
    $r->get('/favicon.ico', [PwaController::class, 'favicon']);

    // Públiques
    $r->get('/acces', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/acces', [AuthController::class, 'login'], ['guest']);
    $r->get('/registre', [AuthController::class, 'showRegister'], ['guest']);
    $r->post('/registre', [AuthController::class, 'register'], ['guest']);
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

    $r->get('/notificacions', [NotificationController::class, 'index'], ['auth']);
    $r->post('/notificacions/preferencies', [NotificationController::class, 'preferences'], ['auth']);
    $r->post('/push/subscriure', [NotificationController::class, 'subscribe'], ['auth']);
    $r->post('/push/cancela', [NotificationController::class, 'unsubscribe'], ['auth']);
    // Equips
    $r->get('/equips', [TeamController::class, 'index'], ['admin']);
    $r->get('/equips/nou', [TeamController::class, 'create'], ['admin']);
    $r->post('/equips', [TeamController::class, 'store'], ['admin']);
    $r->get('/equips/{id}', [TeamController::class, 'show'], ['auth']);
    $r->get('/equips/{id}/editar', [TeamController::class, 'edit'], ['admin']);
    $r->post('/equips/{id}', [TeamController::class, 'update'], ['admin']);
    $r->post('/equips/{id}/eliminar', [TeamController::class, 'delete'], ['admin']);

    // Jugadors
    $r->get('/jugadors', [PlayerController::class, 'index'], ['admin']);
    $r->get('/jugadors/nou', [PlayerController::class, 'create'], ['admin']);
    $r->get('/jugadors/importa', [PlayerController::class, 'importForm'], ['admin']);
    $r->post('/jugadors/importa', [PlayerController::class, 'importPreview'], ['admin']);
    $r->post('/jugadors/importa/confirma', [PlayerController::class, 'importConfirm'], ['admin']);
    $r->get('/jugadors/plantilla', [PlayerController::class, 'template'], ['admin']);
    $r->post('/jugadors', [PlayerController::class, 'store'], ['admin']);
    $r->get('/jugadors/{id}/editar', [PlayerController::class, 'edit'], ['admin']);
    $r->post('/jugadors/{id}', [PlayerController::class, 'update'], ['admin']);
    $r->post('/jugadors/{id}/eliminar', [PlayerController::class, 'delete'], ['admin']);

    // Assistència
    $r->get('/assistencia', [AttendanceController::class, 'index'], ['auth']);
    $r->get('/assistencia/resum', [AttendanceReportController::class, 'week'], ['admin']);
    $r->get('/assistencia/equip/{id}', [AttendanceReportController::class, 'team'], ['admin']);
    $r->post('/assistencia/extra', [AttendanceController::class, 'addExtra'], ['admin']);
    $r->get('/assistencia/{id}', [AttendanceController::class, 'show'], ['auth']);
    $r->post('/assistencia/{id}', [AttendanceController::class, 'save'], ['auth']);
    $r->post('/assistencia/{id}/cancela', [AttendanceController::class, 'cancel'], ['admin']);

    // Calendari i festius
    $r->get('/calendari', [CalendarController::class, 'index'], ['admin']);
    $r->post('/calendari/festius', [CalendarController::class, 'holidayAdd'], ['admin']);
    $r->post('/calendari/festius/{id}/elimina', [CalendarController::class, 'holidayDelete'], ['admin']);

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

    $r->get('/administradors', [AdminUserController::class, 'index'], ['admin']);
    $r->get('/administradors/nou', [AdminUserController::class, 'create'], ['admin']);
    $r->post('/administradors', [AdminUserController::class, 'store'], ['admin']);
    $r->get('/administradors/{id}/editar', [AdminUserController::class, 'edit'], ['admin']);
    $r->post('/administradors/{id}', [AdminUserController::class, 'update'], ['admin']);
    $r->post('/administradors/{id}/estat', [AdminUserController::class, 'toggle'], ['admin']);
    $r->post('/administradors/{id}/activacio', [AdminUserController::class, 'activation'], ['admin']);
    $r->post('/entrenadors/{id}/eliminar', [TrainerController::class, 'delete'], ['admin']);

    $r->get('/categories', [CategoryController::class, 'index'], ['admin']);
    $r->get('/categories/nova', [CategoryController::class, 'create'], ['admin']);
    $r->post('/categories', [CategoryController::class, 'store'], ['admin']);
    $r->get('/categories/{id}/editar', [CategoryController::class, 'edit'], ['admin']);
    $r->post('/categories/{id}', [CategoryController::class, 'update'], ['admin']);
    $r->post('/categories/{id}/eliminar', [CategoryController::class, 'delete'], ['admin']);

    $r->get('/admin/correu', [AdminController::class, 'mailForm'], ['admin']);
    $r->post('/admin/correu', [AdminController::class, 'mailSave'], ['admin']);
    $r->post('/admin/correu/prova', [AdminController::class, 'mailTest'], ['admin']);
    $r->get('/admin/identitat', [IdentityController::class, 'index'], ['admin']);
    $r->post('/admin/identitat/logotip', [IdentityController::class, 'logoSave'], ['admin']);
    $r->post('/admin/identitat/generar', [IdentityController::class, 'generate'], ['admin']);
    $r->post('/admin/identitat/icona/{name}', [IdentityController::class, 'iconUpload'], ['admin']);
    $r->post('/admin/identitat/icona/{name}/restaura', [IdentityController::class, 'iconReset'], ['admin']);
    $r->post('/admin/identitat/pwa', [IdentityController::class, 'pwaSave'], ['admin']);
    $r->get('/admin/registre', [AdminController::class, 'audit'], ['admin']);
    $r->post('/admin/avisos/correus', [AdminController::class, 'dismissMailAlert'], ['admin']);
    $r->get('/admin/correus', [AdminController::class, 'emails'], ['admin']);
};
