<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\CheckoutRecordController;
use App\Http\Controllers\CheckupRecordController;
use App\Http\Controllers\DoorEventController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('parts', PartController::class);
    Route::apiResource('checkout-records', CheckoutRecordController::class);
    Route::apiResource('checkup-records', CheckupRecordController::class);
    Route::apiResource('door-events', DoorEventController::class);


    Route::get('/parts/qr/{qrCode}', [PartController::class,'showByQr']);
    Route::get('/parts/qr_code/{part_id}', [PartController::class,'']);
});