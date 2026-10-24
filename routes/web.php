<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailAuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/catalog', [PublicCatalogController::class, 'catalog'])->name('catalog');
Route::get('/informasi/tentang', fn () => app(PublicController::class)->information('tentang'))->name('about');
Route::get('/informasi/{topic}', [PublicController::class, 'information'])->name('information');
Route::view('/faq', 'faq')->name('faq');
Route::get('/peta', [PublicController::class, 'map'])->name('public.map');
Route::get('/peta/data', [PublicController::class, 'mapData'])->name('public.map.data');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [EmailAuthController::class, 'forgot'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', compact('token')))->name('password.reset');
    Route::post('/reset-password', [EmailAuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::get('/email/verify/{id}/{hash}', [EmailAuthController::class, 'link'])->middleware('throttle:10,1')->name('verification.verify');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [EmailAuthController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailAuthController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('/email/verify-code', [EmailAuthController::class, 'code'])->middleware('throttle:10,1')->name('verification.code');
    Route::view('/settings', 'portal.placeholder', ['pageTitle' => 'Pengaturan Akun'])->name('settings');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::view('/notifications', 'portal.placeholder', ['pageTitle' => 'Notifikasi'])->name('notifications');
    Route::get('/files/{type}/{id}', FileController::class)->where('type', 'legal')->whereNumber('id')->name('files');
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/partners', [AdminController::class, 'queue'])->defaults('type', 'partners')->name('admin.partners');
        Route::post('/admin/{type}/{id}/review', [AdminController::class, 'review'])->where('type', 'partners|plots')->whereNumber('id');
        Route::get('/admin/plots', [AdminController::class, 'queue'])->defaults('type', 'plots')->name('admin.plots');
        Route::view('/admin/plantings', 'portal.placeholder', ['pageTitle' => 'Verifikasi Bukti Tanam'])->name('admin.plantings');
        Route::view('/admin/monitorings', 'portal.placeholder', ['pageTitle' => 'Monitoring Berkala'])->name('admin.monitorings');
        Route::view('/admin/catalog', 'portal.placeholder', ['pageTitle' => 'Pengelolaan Katalog'])->name('admin.catalog');
        Route::view('/admin/disbursements', 'portal.placeholder', ['pageTitle' => 'Pencairan Dana'])->name('admin.funds');
        Route::view('/admin/reports', 'portal.placeholder', ['pageTitle' => 'Rekapitulasi dan Laporan'])->name('admin.reports');
        Route::view('/admin/settings', 'portal.placeholder', ['pageTitle' => 'Pengaturan Sistem'])->name('admin.settings');
    });
    Route::middleware('role:partner')->group(function () {
        Route::get('/partner/partnership', [PartnerController::class, 'partnership'])->name('partner.partnership');
        Route::post('/partner/partnership', [PartnerController::class, 'savePartnership']);
        Route::get('/partner/plots', [PartnerController::class, 'plots'])->name('partner.plots');
        Route::post('/partner/plots', [PartnerController::class, 'savePlot']);
        Route::view('/partner/work-orders', 'portal.placeholder', ['pageTitle' => 'Work Order Penanaman'])->name('partner.works');
        Route::view('/partner/work-orders/{work}', 'portal.placeholder', ['pageTitle' => 'Detail Work Order'])->name('partner.work');
        Route::view('/partner/monitoring', 'portal.placeholder', ['pageTitle' => 'Log Monitoring'])->name('partner.monitoring');
        Route::view('/partner/funds', 'portal.placeholder', ['pageTitle' => 'Pencairan Dana'])->name('partner.funds');
        Route::view('/partner/members', 'portal.placeholder', ['pageTitle' => 'Data Anggota Kelompok'])->name('partner.members');
    });
    Route::middleware('role:donor')->group(function () {
        Route::view('/donor/catalog', 'portal.placeholder', ['pageTitle' => 'Katalog Donatur'])->name('donor.catalog');
        Route::view('/trees', 'portal.placeholder', ['pageTitle' => 'Portofolio Pohon dan WebGIS'])->name('trees');
        Route::view('/certificates', 'portal.placeholder', ['pageTitle' => 'E-Sertifikat Adopsi'])->name('certificates');
        Route::view('/orders', 'portal.placeholder', ['pageTitle' => 'Riwayat Transaksi'])->name('orders');
        Route::view('/orders/{order}', 'portal.placeholder', ['pageTitle' => 'Detail Transaksi'])->name('orders.show');
        Route::view('/checkout/{offer}', 'portal.placeholder', ['pageTitle' => 'Checkout Adopsi'])->name('checkout');
    });
});
