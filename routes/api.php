<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuoteRequestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/quote-request', [QuoteRequestController::class, 'processQuoteRequest']);
Route::get('/quote-request/{uuid}', [QuoteRequestController::class, 'getQuoteRequest']);
Route::post('/quote-request/{uuid}/submit', [QuoteRequestController::class, 'submitQuoteRequest']);