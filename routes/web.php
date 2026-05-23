<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BoilerChiefDocumentHeaderLayoutController;
use App\Http\Controllers\BoilerChiefLayoutApplicationController;
use App\Http\Controllers\BoilerChiefRequestLayoutController;
use App\Http\Controllers\BoilerChiefSubdivisionAssignmentController;
use App\Http\Controllers\DadataAddressController;
use App\Http\Controllers\DashboardController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('api/dadata/address')->name('api.dadata.address.')->middleware('throttle:30,1')->group(function () {
        Route::get('/suggest', [DadataAddressController::class, 'suggest'])->name('suggest');
        Route::post('/clean', [DadataAddressController::class, 'clean'])->name('clean');
    });
});

Route::middleware(['auth', 'admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/block-preview', [UserController::class, 'blockPreview'])->name('block.preview');
    Route::get('/{user}/reassign-applications', [UserController::class, 'reassignApplications'])->name('reassign-applications');
    Route::post('/{user}/reassign-applications', [UserController::class, 'storeReassignApplications'])->name('reassign-applications.store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::post('/{user}/block', [UserController::class, 'block'])->name('block');
    Route::post('/{user}/unblock', [UserController::class, 'unblock'])->name('unblock');
});

Route::middleware(['auth', 'applications'])->prefix('applications')->name('applications.')->group(function () {
    Route::get('/', [ApplicationController::class, 'index'])->name('index');
    Route::get('/archive', function () {
        $query = array_merge(
            request()->except('page'),
            ['archive' => 'archived']
        );

        return redirect()->route('applications.index', $query);
    })->name('archive');
    Route::get('/create', [ApplicationController::class, 'create'])->name('create');
    Route::get('/commercial-proposal/fill', [ApplicationController::class, 'createCommercialProposalFill'])->name('commercial-proposal.fill');
    Route::post('/commercial-proposal/fill', [ApplicationController::class, 'storeCommercialProposalFill'])->name('commercial-proposal.fill.store');
    Route::get('/installation-act/upload', [ApplicationController::class, 'createInstallationActUpload'])->name('installation-act.upload');
    Route::post('/installation-act/upload', [ApplicationController::class, 'storeInstallationActUpload'])->name('installation-act.upload.store');
    Route::get('/installation-act/browse', [ApplicationController::class, 'browseInstallationActs'])->name('installation-act.browse');
    Route::get('/installation-act/layout-fill', [BoilerChiefRequestLayoutController::class, 'foremanFillIndex'])->name('installation-act.layout-fill.index');
    Route::get('/installation-act/layout-fill/submissions', [BoilerChiefRequestLayoutController::class, 'foremanSubmissionsIndex'])->name('installation-act.layout-fill.submissions');
    Route::get('/installation-act/layout-fill/{requestLayout}', [BoilerChiefRequestLayoutController::class, 'foremanFill'])->name('installation-act.layout-fill.fill');
    Route::post('/installation-act/layout-fill/{requestLayout}/pdf', [BoilerChiefRequestLayoutController::class, 'foremanDownloadFilledPdf'])->name('installation-act.layout-fill.pdf');
    Route::get('/installation-act/layout-fill/submissions/{submission}/pdf', [BoilerChiefRequestLayoutController::class, 'foremanSubmissionPdf'])->name('installation-act.layout-fill.submission-pdf');
    Route::get('/installation-act/layout-schema/{requestLayout}', [BoilerChiefRequestLayoutController::class, 'layoutSchemaJsonForReportFillers'])->name('installation-act.layout-schema');
    Route::get('/custom-equipment-to-order', [ApplicationController::class, 'customEquipmentToOrder'])->name('custom-equipment-to-order');
    Route::get('/commercial-offer-procurement', [ApplicationController::class, 'commercialOfferProcurementIndex'])->name('commercial-offer-procurement');
    Route::get('/{application}/custom-equipment-order', [ApplicationController::class, 'customEquipmentOrderForm'])->name('custom-equipment-order');
    Route::post('/{application}/custom-equipment-order/ordered', [ApplicationController::class, 'markCustomEquipmentOrderedBulk'])->name('custom-equipment-order.ordered');
    Route::post('/{application}/custom-equipment-order/on-warehouse', [ApplicationController::class, 'markCustomEquipmentOnWarehouseBulk'])->name('custom-equipment-order.on-warehouse');
    Route::get('/{application}/commercial-offer-procurement', [ApplicationController::class, 'commercialOfferProcurementForm'])->name('commercial-offer-procurement.show');
    Route::get('/{application}/repeat', [ApplicationController::class, 'repeat'])->name('repeat');
    Route::get('/{application}/installation-act/photos/{installationActPhoto}', [ApplicationController::class, 'viewInstallationActPhoto'])
        ->name('installation-act.photo');
    Route::get('/{application}/installation-act/download', [ApplicationController::class, 'downloadInstallationAct'])->name('installation-act.download');
    Route::get('/{application}/installation-act', [ApplicationController::class, 'viewInstallationAct'])->name('installation-act.view');
    Route::get('/{application}/commercial-offer', [ApplicationController::class, 'viewCommercialOffer'])->name('commercial-offer.view');
    Route::get('/{application}/commercial-offer/download', [ApplicationController::class, 'downloadCommercialOffer'])->name('commercial-offer.download');
    Route::get('/{application}/commercial-proposal/fill', [ApplicationController::class, 'editCommercialProposalFill'])->name('commercial-proposal.fill.edit');
    Route::post('/{application}/commercial-proposal/fill', [ApplicationController::class, 'storeCommercialProposalFillForEdit'])->name('commercial-proposal.fill.edit.store');
    Route::post('/', [ApplicationController::class, 'store'])->name('store');
    Route::post('/{application}/archive-completion', [ApplicationController::class, 'tryArchiveCompletion'])->name('archive-completion');
    Route::post('/{application}/admin-archive', [ApplicationController::class, 'adminArchive'])->name('admin-archive');
    Route::post('/{application}/admin-unarchive', [ApplicationController::class, 'adminUnarchive'])->name('admin-unarchive');
    Route::post('/{application}/approval', [ApplicationController::class, 'saveApproval'])->name('approval');
    Route::post('/{application}/commercial-offer-order-lines', [ApplicationController::class, 'storeCommercialOfferOrderLines'])->name('commercial-offer-order-lines.store');
    Route::post('/{application}/boiler-chief-approval', [ApplicationController::class, 'saveBoilerChiefApproval'])->name('boiler-chief-approval');
    Route::post('/{application}/delivery-in-transit', [ApplicationController::class, 'markApplicationDeliveryInTransit'])->name('delivery-in-transit');
    Route::post('/{application}/items/delivery-delivered/bulk', [ApplicationController::class, 'markItemsDeliveryDeliveredBulk'])->name('delivery-delivered.bulk');
    Route::post('/{application}/items/{item}/delivery-delivered', [ApplicationController::class, 'markItemDeliveryDelivered'])->name('delivery-delivered');
    Route::post('/{application}/items/{item}/custom-supply-ordered', [ApplicationController::class, 'markCustomEquipmentOrdered'])->name('custom-supply-ordered');
    Route::post('/{application}/items/{item}/custom-supply-in-transit', [ApplicationController::class, 'markCustomEquipmentSupplyInTransit'])->name('custom-supply-in-transit');
    Route::post('/{application}/items/{item}/custom-supply-on-warehouse', [ApplicationController::class, 'markCustomEquipmentOnWarehouse'])->name('custom-supply-on-warehouse');
    Route::post('/{application}/issue-stock', [ApplicationController::class, 'issueStock'])->name('issue-stock');
    Route::post('/{application}/issue-delivered-warehouse-stock', [ApplicationController::class, 'issueDeliveredWarehouseStock'])->name('issue-delivered-warehouse-stock');
    Route::get('/{application}/responsible', [ApplicationController::class, 'editResponsible'])->name('responsible.edit');
    Route::patch('/{application}/responsible', [ApplicationController::class, 'updateResponsible'])->name('responsible.update');
    Route::post('/{application}/submit-to-boiler-chief', [ApplicationController::class, 'submitToBoilerChief'])->name('submit-to-boiler-chief');
    Route::post('/{application}/submit-for-management', [ApplicationController::class, 'submitForManagement'])->name('submit-for-management');
    Route::get('/{application}', [ApplicationController::class, 'show'])->name('show');
    Route::get('/{application}/edit', [ApplicationController::class, 'edit'])->name('edit');
    Route::put('/{application}', [ApplicationController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('foreman-subdivisions')->name('foreman-subdivisions.')->group(function () {
    Route::get('/', [ForemanSubdivisionAssignmentController::class, 'index'])->name('index');
    Route::get('/archive', [ForemanSubdivisionAssignmentController::class, 'archiveIndex'])->name('archive');
    Route::get('/assignments', [ForemanSubdivisionAssignmentController::class, 'assignments'])->name('assignments');
    Route::post('/subdivisions', [ForemanSubdivisionAssignmentController::class, 'storeSubdivision'])->name('subdivisions.store');
    Route::post('/warehouses', [ForemanSubdivisionAssignmentController::class, 'storeWarehouse'])->name('warehouses.store');
    Route::get('/subdivisions/{subdivision}/deactivate-preview', [ForemanSubdivisionAssignmentController::class, 'subdivisionDeactivatePreview'])->name('subdivisions.deactivate-preview');
    Route::post('/subdivisions/{subdivision}/deactivate', [ForemanSubdivisionAssignmentController::class, 'deactivateSubdivision'])->name('subdivisions.deactivate');
    Route::get('/{foreman}/update-preview', [ForemanSubdivisionAssignmentController::class, 'updatePreview'])->name('update.preview');
    Route::get('/{foreman}/edit', [ForemanSubdivisionAssignmentController::class, 'edit'])->name('edit');
    Route::put('/{foreman}', [ForemanSubdivisionAssignmentController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('boiler-chief-subdivisions')->name('boiler-chief-subdivisions.')->group(function () {
    Route::get('/assignments', [BoilerChiefSubdivisionAssignmentController::class, 'assignments'])->name('assignments');
    Route::get('/{chief}/edit', [BoilerChiefSubdivisionAssignmentController::class, 'edit'])->name('edit');
    Route::put('/{chief}', [BoilerChiefSubdivisionAssignmentController::class, 'update'])->name('update');
});

Route::middleware(['auth', 'layout_application_reports'])->prefix('boiler-chief/request-layouts')->name('boiler-chief.request-layouts.')->group(function () {
    Route::get('/{requestLayout}/fill', [BoilerChiefRequestLayoutController::class, 'fill'])->name('fill');
    Route::post('/{requestLayout}/filled-pdf', [BoilerChiefRequestLayoutController::class, 'downloadFilledPdf'])->name('filled-pdf');
});

Route::middleware(['auth', 'layout_application_reports'])->prefix('boiler-chief/layout-applications')->name('boiler-chief.layout-applications.')->group(function () {
    Route::get('/', [BoilerChiefLayoutApplicationController::class, 'index'])->name('index');
    Route::get('/create', [BoilerChiefLayoutApplicationController::class, 'create'])->name('create');
    Route::post('/', [BoilerChiefLayoutApplicationController::class, 'store'])->name('store');
    Route::get('/submissions/{submission}/edit', [BoilerChiefLayoutApplicationController::class, 'edit'])->name('edit');
    Route::put('/submissions/{submission}', [BoilerChiefLayoutApplicationController::class, 'update'])->name('update');
    Route::get('/submissions/{submission}/pdf', [BoilerChiefLayoutApplicationController::class, 'pdf'])->name('pdf');
    Route::delete('/submissions/{submission}', [BoilerChiefLayoutApplicationController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'report_layout_designer'])->prefix('boiler-chief/document-header-layouts')->name('boiler-chief.document-header-layouts.')->group(function () {
    Route::get('/', [BoilerChiefDocumentHeaderLayoutController::class, 'index'])->name('index');
    Route::get('/create', [BoilerChiefDocumentHeaderLayoutController::class, 'create'])->name('create');
    Route::post('/', [BoilerChiefDocumentHeaderLayoutController::class, 'store'])->name('store');
    Route::get('/{documentHeaderLayout}/edit', [BoilerChiefDocumentHeaderLayoutController::class, 'edit'])->name('edit');
    Route::put('/{documentHeaderLayout}', [BoilerChiefDocumentHeaderLayoutController::class, 'update'])->name('update');
    Route::delete('/{documentHeaderLayout}', [BoilerChiefDocumentHeaderLayoutController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'report_layout_catalog'])->prefix('boiler-chief/request-layouts')->name('boiler-chief.request-layouts.')->group(function () {
    Route::get('/', [BoilerChiefRequestLayoutController::class, 'index'])->name('index');
    Route::get('/{requestLayout}/fill-schema-json', [BoilerChiefRequestLayoutController::class, 'layoutFillSchemaJsonForCatalog'])->name('fill-schema-json');
});

Route::middleware(['auth', 'report_layout_designer'])->prefix('boiler-chief/request-layouts')->name('boiler-chief.request-layouts.')->group(function () {
    Route::get('/create', [BoilerChiefRequestLayoutController::class, 'create'])->name('create');
    Route::get('/{requestLayout}/schema-json', [BoilerChiefRequestLayoutController::class, 'layoutSchemaJson'])->name('schema-json');
    Route::post('/', [BoilerChiefRequestLayoutController::class, 'store'])->name('store');
    Route::get('/{requestLayout}/edit', [BoilerChiefRequestLayoutController::class, 'edit'])->name('edit');
    Route::put('/{requestLayout}', [BoilerChiefRequestLayoutController::class, 'update'])->name('update');
    Route::delete('/{requestLayout}', [BoilerChiefRequestLayoutController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'supply_head'])->prefix('materials')->name('materials.')->group(function () {
    Route::get('/', [MaterialAccountingController::class, 'index'])->name('index');
    Route::post('/catalog', [MaterialAccountingController::class, 'storeMaterial'])->name('store-material');
    Route::post('/movements', [MaterialAccountingController::class, 'storeMovement'])->name('store-movement');
});

Route::middleware(['auth', 'applications'])->prefix('materials')->name('materials.')->group(function () {
    Route::get('/overview', [MaterialAccountingController::class, 'overview'])->name('overview');
    Route::get('/movements', [MaterialAccountingController::class, 'movementsJournal'])->name('movements');
});

require __DIR__.'/auth.php';
