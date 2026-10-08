<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckupController;
use App\Http\Controllers\Api\V1\CheckupTemplateController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\KasirController;
use App\Http\Controllers\Api\V1\MechanicController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RewardController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SparepartController;
use App\Http\Controllers\Api\V1\UnitEntryController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Aplikasi Manajemen Service Bengkel
|--------------------------------------------------------------------------
| Semua respons memakai amplop: { success, message, data, meta }
| Otorisasi di server: `auth:sanctum` + `aktif` + permission/role spatie.
*/

/* -------------------------------------------------------------- publik --- */
Route::get('/ping', [DashboardController::class, 'ping']);
Route::get('/health', [DashboardController::class, 'health']);
Route::post('/auth/login', [AuthController::class, 'login']);

/* ------------------------------------------------------- butuh login ----- */
Route::middleware(['auth:sanctum', 'aktif'])->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    /* ------------------------------------------------------- pengaturan --- */
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:setting.manage');

    /* --------------------------------------------------------- pengguna --- */
    Route::middleware('permission:user.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    /* ------------------------------------------------ pelanggan & kendaraan */
    Route::middleware('permission:customer.manage')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('role:owner|admin');

        Route::post('/customers/{customer}/vehicles', [VehicleController::class, 'store']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);

        Route::post('/customers/{customer}/membership', [CustomerController::class, 'jadikanMember']);
        Route::post('/customers/{customer}/membership/renew', [CustomerController::class, 'perpanjangMember']);
        Route::get('/customers/{customer}/points', [CustomerController::class, 'points']);
        Route::post('/customers/{customer}/redeem', [CustomerController::class, 'redeem']);
        Route::get('/customers/{customer}/history', [CustomerController::class, 'history']);

        Route::get('/rewards', [RewardController::class, 'index']);
        Route::post('/rewards', [RewardController::class, 'store']);
        Route::get('/rewards/{reward}', [RewardController::class, 'show']);
        Route::put('/rewards/{reward}', [RewardController::class, 'update']);
        Route::delete('/rewards/{reward}', [RewardController::class, 'destroy']);
    });

    /* ------------------------------------------------------------ master --- */
    Route::middleware('permission:mechanic.manage')->group(function () {
        Route::get('/mechanics', [MechanicController::class, 'index']);
        Route::post('/mechanics', [MechanicController::class, 'store']);
        Route::get('/mechanics/{mechanic}', [MechanicController::class, 'show']);
        Route::put('/mechanics/{mechanic}', [MechanicController::class, 'update']);
        Route::delete('/mechanics/{mechanic}', [MechanicController::class, 'destroy']);
    });

    // Dropdown pencarian jasa: dipakai juga oleh kasir saat mengisi Form SA.
    Route::get('/services/pilihan', [ServiceController::class, 'pilihan'])
        ->middleware('permission:service.manage|service-order.manage');

    Route::middleware('permission:service.manage')->group(function () {
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);
    });

    // Dropdown pencarian sparepart: dipakai gudang & kasir (Form SA).
    Route::get('/spareparts/pilihan', [SparepartController::class, 'pilihan'])
        ->middleware('permission:sparepart.manage|stock.input|service-order.manage');

    Route::middleware('permission:sparepart.manage|stock.input')->group(function () {
        Route::get('/spareparts', [SparepartController::class, 'index']);
        Route::get('/spareparts/{sparepart}', [SparepartController::class, 'show']);
        Route::post('/spareparts', [SparepartController::class, 'store'])->middleware('permission:sparepart.manage');
        Route::put('/spareparts/{sparepart}', [SparepartController::class, 'update'])->middleware('permission:sparepart.manage');
        Route::delete('/spareparts/{sparepart}', [SparepartController::class, 'destroy'])->middleware('permission:sparepart.manage');

        Route::get('/part-purchases', [SparepartController::class, 'pembelian']);
        Route::post('/part-purchases', [SparepartController::class, 'simpanPembelian']);
        Route::get('/part-purchases/{partPurchase}', [SparepartController::class, 'detailPembelian']);

        Route::post('/stock-adjustments', [SparepartController::class, 'penyesuaian']);
        Route::get('/stock-movements', [SparepartController::class, 'kartuStok']);
    });

    /* ---------------------------------------------------- template check up */
    Route::middleware('permission:checkup.manage|setting.manage')->group(function () {
        Route::get('/checkup-templates', [CheckupTemplateController::class, 'index']);
        Route::get('/checkup-templates/untuk-kendaraan', [CheckupTemplateController::class, 'untukKendaraan']);
        Route::get('/checkup-templates/{checkupTemplate}', [CheckupTemplateController::class, 'show']);
        Route::post('/checkup-templates', [CheckupTemplateController::class, 'store'])->middleware('permission:setting.manage');
        Route::put('/checkup-templates/{checkupTemplate}', [CheckupTemplateController::class, 'update'])->middleware('permission:setting.manage');
        Route::delete('/checkup-templates/{checkupTemplate}', [CheckupTemplateController::class, 'destroy'])->middleware('permission:setting.manage');

        Route::post('/checkup-templates/{checkupTemplate}/duplikat', [CheckupTemplateController::class, 'duplikat'])->middleware('permission:setting.manage');
        Route::post('/checkup-templates/{checkupTemplate}/items', [CheckupTemplateController::class, 'storeItem'])->middleware('permission:setting.manage');
        Route::put('/checkup-templates/{checkupTemplate}/items/{item}', [CheckupTemplateController::class, 'updateItem'])->middleware('permission:setting.manage');
        Route::delete('/checkup-templates/{checkupTemplate}/items/{item}', [CheckupTemplateController::class, 'destroyItem'])->middleware('permission:setting.manage');
        Route::post('/checkup-templates/{checkupTemplate}/items/reorder', [CheckupTemplateController::class, 'reorderItem'])->middleware('permission:setting.manage');
    });

    /* -------------------------------------------------------- general check up */
    Route::middleware('permission:checkup.manage')->group(function () {
        Route::get('/checkups', [CheckupController::class, 'index']);
        Route::post('/checkups', [CheckupController::class, 'store']);
        Route::get('/checkups/{checkup}', [CheckupController::class, 'show']);
        Route::put('/checkups/{checkup}', [CheckupController::class, 'update']);
        Route::post('/checkups/{checkup}/finish', [CheckupController::class, 'finish']);
        Route::delete('/checkups/{checkup}', [CheckupController::class, 'destroy'])->middleware('role:owner|admin');
    });

    /* ------------------------------------------------------------- Form SA --- */
    Route::middleware('permission:service-order.manage')->group(function () {
        Route::get('/service-orders', [ServiceOrderController::class, 'index']);
        Route::post('/service-orders', [ServiceOrderController::class, 'store']);
        Route::get('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'show']);
        Route::put('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'update']);
        Route::post('/service-orders/{serviceOrder}/start', [ServiceOrderController::class, 'start']);
        Route::post('/service-orders/{serviceOrder}/finish', [ServiceOrderController::class, 'finish']);
        Route::post('/service-orders/{serviceOrder}/cancel', [ServiceOrderController::class, 'cancel']);
        Route::get('/service-orders/{serviceOrder}/print', [ServiceOrderController::class, 'print']);
        Route::delete('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'destroy'])->middleware('role:owner|admin');

        Route::get('/unit-entries', [UnitEntryController::class, 'index']);
    });

    /* ---------------------------------------------------------------- KASIR --- */
    // Modul kasir terpisah dari Form SA: Form SA hanya mencatat pekerjaan,
    // pembayaran & nota hanya untuk SA yang sudah berstatus "selesai".
    Route::middleware('permission:kasir.manage')->prefix('kasir')->group(function () {
        Route::get('/tagihan', [KasirController::class, 'tagihan']);
        Route::get('/ringkasan', [KasirController::class, 'ringkasan']);
        Route::get('/transaksi', [KasirController::class, 'transaksi']);
    });

    Route::post('/service-orders/{serviceOrder}/pay', [ServiceOrderController::class, 'pay'])
        ->middleware('permission:kasir.manage');

    /* ------------------------------------------------------------ laporan --- */
    Route::middleware('permission:report.finance')->group(function () {
        Route::get('/reports/finance', [ReportController::class, 'finance']);
        Route::get('/reports/finance/export', [ReportController::class, 'financeExport']);
        Route::get('/expenses', [ReportController::class, 'expenses']);
        Route::post('/expenses', [ReportController::class, 'storeExpense']);
    });

    Route::middleware('permission:report.stock')->group(function () {
        Route::get('/reports/stock', [ReportController::class, 'stock']);
        Route::get('/reports/stock/pdf', [ReportController::class, 'stockPdf']);
        Route::get('/reports/unit-entries', [ReportController::class, 'unitEntries']);
        Route::get('/reports/unit-entries/export', [ReportController::class, 'unitEntriesExport']);
    });

    /* ------------------------------------------------------- portal member --- */
    Route::middleware('role:member')->prefix('portal')->group(function () {
        Route::get('/profile', [PortalController::class, 'profile']);
        Route::get('/history', [PortalController::class, 'history']);
        Route::get('/history/{tipe}/{id}', [PortalController::class, 'detail'])->whereNumber('id');
        Route::get('/vehicles', [PortalController::class, 'vehicles']);
    });
});
