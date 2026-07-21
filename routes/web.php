<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/register', [ContactController::class, 'create'])->name('contact');
Route::post('/register', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
