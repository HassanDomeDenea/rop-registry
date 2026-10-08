<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientPrintController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReviewItemController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitListController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('visits', VisitListController::class)->name('visits.index');

    Route::get('captures', [CaptureController::class, 'index'])->name('captures.index');
    Route::get('captures/status', [CaptureController::class, 'status'])->name('captures.status');
    Route::post('captures/assign', [CaptureController::class, 'assign'])->name('captures.assign');
    Route::post('captures/discard', [CaptureController::class, 'destroy'])->name('captures.discard');
    Route::put('captures/settings', [CaptureController::class, 'settings'])->name('captures.settings');
    Route::post('captures/receive/{patient}', [CaptureController::class, 'receive'])->name('captures.receive');
    Route::delete('captures/receive', [CaptureController::class, 'stop'])->name('captures.stop');
    Route::get('captures/{capture}', [CaptureController::class, 'show'])->name('captures.show');

    Route::get('patients/export', [PatientController::class, 'export'])->name('patients.export');
    Route::get('patients/workbook', [PatientController::class, 'workbook'])->name('patients.workbook');
    Route::get('patients/lookup', [PatientController::class, 'lookup'])->name('patients.lookup');
    Route::get('patients/duplicates', [PatientController::class, 'duplicates'])->name('patients.duplicates');
    Route::patch('patients/{patient}/verify', [PatientController::class, 'verify'])->name('patients.verify');
    Route::post('patients/{patient}/restore', [PatientController::class, 'restore'])->withTrashed()->name('patients.restore');
    Route::patch('patients/{patient}/status', [PatientController::class, 'status'])->name('patients.status');
    Route::get('patients/{patient}/print', PatientPrintController::class)->name('patients.print');
    Route::resource('patients', PatientController::class);

    Route::scopeBindings()->group(function () {
        Route::get('patients/{patient}/visits/{visit}/print', [VisitController::class, 'print'])->name('patients.visits.print');
        Route::resource('patients.visits', VisitController::class)->except(['index', 'show']);
        Route::resource('patients.treatments', TreatmentController::class)->only(['store', 'update', 'destroy']);
    });

    Route::post('patients/{patient}/attachments', [AttachmentController::class, 'store'])->name('patients.attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::patch('attachments/{attachment}', [AttachmentController::class, 'update'])->name('attachments.update');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('review', [ReviewItemController::class, 'index'])->name('review.index');
    Route::post('patients/{patient}/review-items', [ReviewItemController::class, 'store'])->name('patients.review-items.store');
    Route::patch('review-items/{reviewItem}', [ReviewItemController::class, 'update'])->name('review-items.update');
    Route::delete('review-items/{reviewItem}', [ReviewItemController::class, 'destroy'])->name('review-items.destroy');

    Route::get('reminders', ReminderController::class)->name('reminders.index');
    Route::get('statistics', StatisticsController::class)->name('statistics.index');
    Route::get('audits', AuditController::class)->name('audits.index');

    Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('backups/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::put('backups/settings', [BackupController::class, 'settings'])->name('backups.settings');
    Route::get('backups/{backup}', [BackupController::class, 'show'])->name('backups.show');
    Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
});

require __DIR__.'/settings.php';
