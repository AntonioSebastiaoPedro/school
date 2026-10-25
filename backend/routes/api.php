<?php

use App\Modules\Identity\Http\AuthController;
use App\Modules\Platform\Http\AdminApplicationController;
use App\Modules\Platform\Http\ApplicationController;
use App\Modules\Platform\Http\PlanController;
use App\Modules\Platform\Http\SubscriptionController;
use App\Modules\Schools\Http\CurriculumController;
use App\Modules\Schools\Http\EnrollmentController;
use App\Modules\Schools\Http\GeographyController;
use App\Modules\Schools\Http\GradeController;
use App\Modules\Schools\Http\GuardianController;
use App\Modules\Schools\Http\SchoolProfileController;
use App\Modules\Schools\Http\StructureController;
use App\Modules\Schools\Http\StudentController;
use Illuminate\Support\Facades\Route;

// Público
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/institution-applications', [ApplicationController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/geography/countries', [GeographyController::class, 'countries']);
    Route::get('/geography/provinces', [GeographyController::class, 'provinces']);
    Route::get('/geography/municipalities', [GeographyController::class, 'municipalities']);

    // Administração SaaS
    Route::prefix('admin')->middleware('can:platform.admin')->group(function () {
        Route::get('/institution-applications', [AdminApplicationController::class, 'index']);
        Route::post('/institution-applications/{id}/decision', [AdminApplicationController::class, 'decide']);

        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::patch('/plans/{id}', [PlanController::class, 'update']);

        Route::post('/institutions/{id}/subscriptions', [SubscriptionController::class, 'store']);
        Route::post('/institutions/{id}/suspend', [SubscriptionController::class, 'suspend']);
        Route::post('/institutions/{id}/reactivate', [SubscriptionController::class, 'reactivate']);
        Route::post('/subscriptions/{id}/payments', [SubscriptionController::class, 'payment']);
    });

    // Área institucional: exige instituição activa validada contra a associação do utilizador
    Route::middleware('tenant')->group(function () {
        Route::get('/school-profile', [SchoolProfileController::class, 'show']);
        Route::put('/school-profile', [SchoolProfileController::class, 'update'])->middleware('can:profile.manage');
        Route::get('/school-links', [SchoolProfileController::class, 'links']);
        Route::put('/school-links', [SchoolProfileController::class, 'putLink'])->middleware('can:profile.manage');

        // Catálogo e estrutura
        Route::get('/grade-levels', [StructureController::class, 'gradeLevels']);
        Route::middleware('can:students.view')->group(function () {
            Route::get('/school-years', [StructureController::class, 'years']);
            Route::get('/rooms', [StructureController::class, 'rooms']);
            Route::get('/sections', [StructureController::class, 'sections']);
            Route::get('/subjects', [CurriculumController::class, 'subjects']);
            Route::get('/curriculum', [CurriculumController::class, 'index']);
        });
        Route::middleware('can:structure.manage')->group(function () {
            Route::post('/school-years', [StructureController::class, 'storeYear']);
            Route::post('/school-years/{id}/activate', [StructureController::class, 'activateYear']);
            Route::post('/rooms', [StructureController::class, 'storeRoom']);
            Route::post('/sections', [StructureController::class, 'storeSection']);
            Route::post('/sections/{id}/renumber', [StructureController::class, 'renumber']);
        });
        Route::middleware('can:curriculum.manage')->group(function () {
            Route::post('/subjects', [CurriculumController::class, 'storeSubject']);
            Route::put('/curriculum/{gradeLevelId}/subjects', [CurriculumController::class, 'sync']);
        });
        Route::get('/assessment-rules', [CurriculumController::class, 'rules'])->middleware('can:students.view');
        Route::put('/assessment-rules', [CurriculumController::class, 'putRules'])->middleware('can:rules.manage');

        // Alunos e encarregados
        Route::get('/students', [StudentController::class, 'index'])->middleware('can:students.view');
        Route::get('/students/{id}', [StudentController::class, 'show'])->middleware('can:students.view');
        Route::post('/students', [StudentController::class, 'store'])->middleware('can:students.manage');
        Route::patch('/students/{id}', [StudentController::class, 'update'])->middleware('can:students.manage');
        Route::get('/guardians', [GuardianController::class, 'index'])->middleware('can:students.view');
        Route::post('/guardians', [GuardianController::class, 'store'])->middleware('can:guardians.manage');
        Route::post('/students/{id}/guardians', [GuardianController::class, 'attach'])->middleware('can:guardians.manage');

        // Matrículas
        Route::get('/enrollments', [EnrollmentController::class, 'index'])->middleware('can:students.view');
        // Notas e pautas
        Route::middleware('can:grades.view')->group(function () {
            Route::get('/grades', [GradeController::class, 'grid']);
            Route::get('/grade-sheets/{sectionId}', [GradeController::class, 'sheet']);
        });
        Route::put('/grades', [GradeController::class, 'save'])->middleware('can:grades.write');
        Route::put('/grade-sheets/{sectionId}/locks', [GradeController::class, 'lock'])->middleware('can:grades.lock');
        Route::post('/sections/{id}/close-year', [GradeController::class, 'closeYear'])->middleware('can:results.close');

        Route::middleware('can:enrollments.manage')->group(function () {
            Route::post('/enrollments', [EnrollmentController::class, 'store']);
            Route::post('/enrollments/bulk-renew', [EnrollmentController::class, 'bulkRenew']);
            Route::post('/enrollments/{id}/decision', [EnrollmentController::class, 'decision']);
            Route::post('/enrollments/{id}/transfer', [EnrollmentController::class, 'transfer']);
        });
    });
});
