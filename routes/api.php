<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/wiregaurd/peers/activate', [ApiController::class, 'activatePeer']);
Route::post('/wiregaurd/peers/toggleEnable', [ApiController::class, 'toggleEnable']);
Route::post('/wiregaurd/peers/renew', [ApiController::class, 'renewPeer']);
