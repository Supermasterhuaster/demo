<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('welcome');
//});

Route::post('/create', [UserController::class, 'create']);
Route::get('/get/{id?}', [UserController::class, 'get']);
Route::patch('/update/{id}', [UserController::class, 'update']);
Route::delete('/delete/{id?}', [UserController::class, 'delete']);
