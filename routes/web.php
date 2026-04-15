<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationReportController;
use App\Http\Controllers\ApplicationReportFooterController;
use App\Http\Controllers\ApplicationReportHeaderController;
use App\Http\Controllers\AdminDatabaseRestoreController;
use App\Http\Controllers\ForemanSubdivisionAssignmentController;
use App\Http\Controllers\MaterialAccountingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::post('/{user}/block', [UserController::class, 'block'])->name('block');
    Route::post('/{user}/unblock', [UserController::class, 'unblock'])->name('unblock');
});

Route::middleware(['auth', 'admin'])->prefix('admin/database')->name('admin.database.')->group(function () {
    Route::get('/restore', [AdminDatabaseRestoreController::class, 'index'])->name('restore.index');
    Route::post('/restore', [AdminDatabaseRestoreController::class, 'restore'])->name('restore.store');
    Route::post('/backup', [AdminDatabaseRestoreController::class, 'backup'])->name('backup.store');
});

Route::middleware(['auth', 'applications', 'supply_head'])->prefix('applications/report')->name('applications.report.')->group(function () {
    Route::get('/', [ApplicationReportController::class, 'index'])->name('index');
    Route::post('/layout', [ApplicationReportController::class, 'updateLayout'])->name('layout');
    Route::post('/preview', [ApplicationReportController::class, 'preview'])->name('preview');
    Route::post('/pdf', [ApplicationReportController::class, 'pdf'])->name('pdf');
    Route::resource('headers', ApplicationReportHeaderController::class)->except(['show'])->names('headers');
    Route::resource('footers', ApplicationReportFooterController::class)->except(['show'])->names('footers');
});

Route::middleware(['auth', 'applications'])->prefix('applications')->name('applications.')->group(function () {
    Route::get('/', [ApplicationController::class, 'index'])->name('index');
    Route::get('/create', [ApplicationController::class, 'create'])->name('create');
    Route::get('/{application}/repeat', [ApplicationController::class, 'repeat'])->name('repeat');
    Route::get('/{application}/commercial-offer', [ApplicationController::class, 'viewCommercialOffer'])->name('commercial-offer.view');
    Route::get('/{application}/commercial-offer/download', [ApplicationController::class, 'downloadCommercialOffer'])->name('commercial-offer.download');
    Route::post('/', [ApplicationController::class, 'store'])->name('store');
    Route::post('/{application}/approval', [ApplicationController::class, 'saveApproval'])->name('approval');
    Route::post('/{application}/issue-stock', [ApplicationController::class, 'issueStock'])->name('issue-stock');
    Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
    Route::get('/{application}/edit', [ApplicationController::class, 'edit'])->name('edit');
    Route::put('/{application}', [ApplicationController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('foreman-subdivisions')->name('foreman-subdivisions.')->group(function () {
    Route::get('/', [ForemanSubdivisionAssignmentController::class, 'index'])->name('index');
    Route::get('/assignments', [ForemanSubdivisionAssignmentController::class, 'assignments'])->name('assignments');
    Route::post('/subdivisions', [ForemanSubdivisionAssignmentController::class, 'storeSubdivision'])->name('subdivisions.store');
    Route::post('/warehouses', [ForemanSubdivisionAssignmentController::class, 'storeWarehouse'])->name('warehouses.store');
    Route::get('/{foreman}/edit', [ForemanSubdivisionAssignmentController::class, 'edit'])->name('edit');
    Route::put('/{foreman}', [ForemanSubdivisionAssignmentController::class, 'update'])->name('update');
});

Route::middleware(['auth', 'supply_head'])->prefix('materials')->name('materials.')->group(function () {
    Route::get('/', [MaterialAccountingController::class, 'index'])->name('index');
    Route::post('/catalog', [MaterialAccountingController::class, 'storeMaterial'])->name('store-material');
    Route::post('/movements', [MaterialAccountingController::class, 'storeMovement'])->name('store-movement');
});

require __DIR__.'/auth.php';
