<?php

use App\Http\Controllers\XapiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// xAPI statements rotası - middleware olmadan test için
Route::match(['get', 'post'], '/statements', [XapiController::class, 'statements']);


// xAPI activities/state endpoint'i
Route::match(['get', 'put', 'delete'], '/statements/activities/state', [XapiController::class, 'activitiesState']);

// xAPI statements/statementId endpoint'i
Route::match(['get', 'put'], '/statements/statements', [XapiController::class, 'statementsWithId']);
