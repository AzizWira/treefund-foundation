<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdoptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/catalog', [AdoptionController::class, 'catalog'])->name('catalog');
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
    Route::get('/settings', [AuthController::class, 'profile'])->name('settings');
    Route::put('/settings', [AuthController::class, 'updateProfile']);
    Route::put('/email/preferences', [EmailAuthController::class, 'preferences'])->name('email.preferences');
});
Route::post('/payments/midtrans/notification', MidtransWebhookController::class)->middleware('throttle:120,1')->name('midtrans.notification');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [DashboardController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [DashboardController::class, 'readNotification']);
    Route::get('/files/{type}/{id}', FileController::class)->name('files');
    Route::middleware('role:donor')->group(function () {
        Route::get('/donor/catalog', [AdoptionController::class, 'catalog'])->name('donor.catalog');
        Route::get('/certificates', [AdoptionController::class, 'certificates'])->name('certificates');
        Route::get('/checkout/{offer}', [AdoptionController::class, 'checkout'])->name('checkout');
        Route::post('/orders', [AdoptionController::class, 'store'])->name('orders.store')->middleware('throttle:10,1');
        Route::get('/orders', [AdoptionController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AdoptionController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/pay', [AdoptionController::class, 'pay'])->name('orders.pay')->middleware('throttle:10,1');
        Route::post('/orders/{order}/sync', [AdoptionController::class, 'sync'])->name('orders.sync')->middleware('throttle:10,1');
        Route::get('/orders/{order}/certificate', [AdoptionController::class, 'certificate'])->name('orders.certificate');
        Route::get('/trees', [AdoptionController::class, 'trees'])->name('trees');
        Route::get('/map/data', [AdoptionController::class, 'mapData'])->name('map.data');
    });
    Route::prefix('partner')->middleware('role:partner')->group(function () {
        Route::get('/partnership', [PartnerController::class, 'partnership'])->name('partner.partnership');
        Route::post('/partnership', [PartnerController::class, 'savePartnership']);
        Route::get('/plots', [PartnerController::class, 'plots'])->name('partner.plots');
        Route::post('/plots', [PartnerController::class, 'savePlot']);
        Route::get('/work-orders', [PartnerController::class, 'workOrders'])->name('partner.works');
        Route::get('/work-orders/{work}', [PartnerController::class, 'workOrder'])->name('partner.work');
        Route::post('/trees/{tree}/planting', [PartnerController::class, 'planting']);
        Route::get('/monitoring', [PartnerController::class, 'monitoring'])->name('partner.monitoring');
        Route::post('/trees/{tree}/monitoring', [PartnerController::class, 'saveMonitoring']);
        Route::get('/funds', [PartnerController::class, 'funds'])->name('partner.funds');
        Route::post('/funds', [PartnerController::class, 'submitFunds']);
        Route::get('/members', [PartnerController::class, 'members'])->name('partner.members');
        Route::post('/members', [PartnerController::class, 'saveMember']);
        Route::put('/members/{member}', [PartnerController::class, 'updateMember']);
        Route::delete('/members/{member}', [PartnerController::class, 'deleteMember']);
    });
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/catalog', [AdminController::class, 'catalog'])->name('admin.catalog');
        Route::post('/catalog', [AdminController::class, 'saveOffer']);
        Route::get('/disbursements', [AdminController::class, 'funds'])->name('admin.funds');
        Route::post('/disbursements/{disbursement}/review', [AdminController::class, 'reviewFunds']);
        Route::post('/disbursements/{disbursement}/transfer', [AdminController::class, 'recordTransfer']);
        Route::post('/allocations/{work}', [AdminController::class, 'allocation']);
        Route::get('/reports', [AdminController::class, 'reports'])->name('admin.reports');
        Route::get('/reports/export', [AdminController::class, 'export'])->name('admin.export');
        Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/settings/terms', [AdminController::class, 'rules']);
        Route::get('/orders', [AdminController::class, 'reports']);
        Route::get('/{type}', [AdminController::class, 'queue'])->whereIn('type', ['partners', 'plots', 'plantings', 'monitorings'])->name('admin.queue');
        Route::post('/{type}/{id}/review', [AdminController::class, 'review'])->whereIn('type', ['partners', 'plots', 'plantings', 'monitorings']);
    });
});
