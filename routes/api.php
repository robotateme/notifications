<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications', [NotificationController::class, 'store']);
Route::post('/notifications/bulk', [NotificationController::class, 'storeBulk']);
Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
Route::post('/notifications/{notification}/delivery-status', [NotificationController::class, 'confirmDelivery']);
Route::get('/subscribers/{subscriber}/notifications', [NotificationController::class, 'subscriberHistory']);
