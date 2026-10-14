<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/privacy', 'privacy')->name('privacy');

Route::view('/support', 'support')->name('support');

Route::get('/invite/accepted', [InvitationController::class, 'accepted'])->name('invitation.accepted');
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('/invite/{token}', [InvitationController::class, 'accept'])
    ->middleware('throttle:5,1')
    ->name('invitation.accept');

Route::get('/register', [ContactController::class, 'create'])->name('contact');
Route::post('/register', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');
