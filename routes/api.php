<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceEmployeeApiController;
use App\Http\Controllers\Api\ClientVisitApiController; 
use App\Http\Controllers\Api\BreakController;


// Public auth routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes - require valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout']);

    // Attendance related routes under the 'attendance' prefix
    Route::prefix('attendance')->group(function () {
        // Check-in attendance
        Route::post('/check-in', [AttendanceEmployeeApiController::class, 'checkIn']);

        // Check-out attendance
        Route::post('/check-out', [AttendanceEmployeeApiController::class, 'checkOut']);

        // Get attendance history list for employee
        Route::get('/list', [AttendanceEmployeeApiController::class, 'attendanceList']);

        // Get today's attendance status (checked in/out state)
        Route::get('/status', [AttendanceEmployeeApiController::class, 'getStatus']);
    });
    
     // Client Visit routes (NEW)
    Route::prefix('client-visits')->group(function () {
        Route::post('/save', [ClientVisitApiController::class, 'saveVisits']);
        Route::get('/list', [ClientVisitApiController::class, 'getVisitList']);
    });
    
    // Break routes (NEW)
    Route::prefix('breaks')->group(function () {
        Route::post('/start', [BreakController::class, 'startBreak']);
        Route::post('/end', [BreakController::class, 'endBreak']);
        Route::get('/status', [BreakController::class, 'getBreakStatus']);
        Route::get('/list', [BreakController::class, 'getBreakList']);
    });
});

