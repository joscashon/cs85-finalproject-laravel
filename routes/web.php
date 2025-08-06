<?php

use App\Http\Controllers\RunController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RunController::class, 'index']);
Route::post('/add-run', [RunController::class, 'addRun']);
Route::post('/delete-run', [RunController::class, 'deleteRun']);
Route::post('/generate-feedback', [RunController::class, 'generateFeedback']);
Route::post('/save-profile', [RunController::class, 'saveProfile']);
Route::post('/delete-feedback', [RunController::class, 'deleteFeedback']);
