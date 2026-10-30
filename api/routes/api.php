<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankTypeController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\DisbursementController;
use App\Http\Controllers\Api\MemberPaymentReportController;
use App\Http\Controllers\Api\MemberPortalController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\SavingsCycleController;
use App\Http\Controllers\Api\SavingsCycleMemberController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', AdminDashboardController::class);

        Route::get('/bank-types', [BankTypeController::class, 'index']);

        Route::get('/people', [PersonController::class, 'index']);
        Route::post('/people', [PersonController::class, 'store']);
        Route::get('/people/{person}', [PersonController::class, 'show']);
        Route::put('/people/{person}', [PersonController::class, 'update']);
        Route::patch('/people/{person}/status', [PersonController::class, 'updateStatus']);
        Route::post('/people/{person}/account', [PersonController::class, 'createAccount']);
        Route::patch('/people/{person}/account', [PersonController::class, 'updateAccount']);
        Route::post('/people/{person}/account/reset-password', [PersonController::class, 'resetPassword']);

        Route::post('/savings-cycles', [SavingsCycleController::class, 'store']);
        Route::put('/savings-cycles/{savings_cycle}', [SavingsCycleController::class, 'update']);
        Route::post('/savings-cycles/{savings_cycle}/start', [SavingsCycleController::class, 'start']);
        Route::post('/savings-cycles/{savings_cycle}/close', [SavingsCycleController::class, 'close']);
        Route::post('/savings-cycles/{savings_cycle}/finalize', [SavingsCycleController::class, 'finalize']);
        Route::put('/savings-cycles/{savings_cycle}/turns/{turn}', [SavingsCycleController::class, 'updateTurn']);

        Route::post('/savings-cycles/{savings_cycle}/members', [SavingsCycleMemberController::class, 'store']);
        Route::put('/savings-cycles/{savings_cycle}/members/order', [SavingsCycleMemberController::class, 'reorder']);
        Route::delete('/savings-cycles/{savings_cycle}/members/{member}', [SavingsCycleMemberController::class, 'destroy']);

        Route::get('/payment-reports', [MemberPaymentReportController::class, 'index']);
        Route::post('/payment-reports/{payment_report}/review', [MemberPaymentReportController::class, 'review']);

        Route::post('/contributions/{contribution}/payments', [ContributionController::class, 'storePayment']);
        Route::post('/payments/{payment}/annul', [ContributionController::class, 'annulPayment']);
        Route::post('/turns/{turn}/disbursement', [DisbursementController::class, 'store']);
        Route::post('/disbursements/{disbursement}/annul', [DisbursementController::class, 'annul']);
    });

    Route::middleware('role:admin,socio')->group(function () {
        Route::get('/savings-cycles', [SavingsCycleController::class, 'index']);
        Route::get('/savings-cycles/{savings_cycle}', [SavingsCycleController::class, 'show']);
        Route::get('/savings-cycles/{savings_cycle}/calendar-preview', [SavingsCycleController::class, 'calendarPreview']);
        Route::get('/savings-cycles/{savings_cycle}/summary', [SavingsCycleController::class, 'summary']);
        Route::get('/payment-reports/{payment_report}', [MemberPaymentReportController::class, 'show']);
        Route::get('/payment-reports/{payment_report}/receipt', [MemberPaymentReportController::class, 'receipt']);
    });

    Route::middleware('role:socio')->prefix('member')->group(function () {
        Route::get('/dashboard', [MemberPortalController::class, 'dashboard']);
        Route::get('/cycles', [MemberPortalController::class, 'cycles']);
        Route::get('/cycles/{savings_cycle}', [MemberPortalController::class, 'showCycle']);
        Route::post('/cycles/{savings_cycle}/payment-reports', [MemberPaymentReportController::class, 'store']);
        Route::get('/current-turn', [MemberPortalController::class, 'currentTurn']);
    });
});
