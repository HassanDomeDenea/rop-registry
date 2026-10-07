<?php

use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SuggestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => to_route('appearance.edit'));

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
    Route::put('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::get('settings/lists', [SuggestionController::class, 'index'])->name('lists.index');
    Route::post('settings/lists', [SuggestionController::class, 'store'])->name('lists.store');
    Route::put('settings/lists/{suggestion}', [SuggestionController::class, 'update'])->name('lists.update');
    Route::delete('settings/lists/{suggestion}', [SuggestionController::class, 'destroy'])->name('lists.destroy');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
