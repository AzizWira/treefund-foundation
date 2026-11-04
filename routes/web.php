<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdoptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailAuthController;
use App\Http\Controllers\FieldReviewController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MidtransWebhookController;
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
    Route::get('/settings', [AuthController::class, 'profile'])->name('settings');
    Route::put('/settings', [AuthController::class, 'updateProfile']);
    Route::put('/email/preferences', [EmailAuthController::class, 'preferences'])->name('email.preferences');
});

Route::post('/payments/midtrans/notification', MidtransWebhookController::class)->middleware('throttle:120,1')->name('midtrans.notification');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [PortalController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read-all', [PortalController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [PortalController::class, 'readNotification'])->whereNumber('notification');
    Route::get('/files/{type}/{id}', FileController::class)->where('type', 'legal|planting|monitoring|transfer')->whereNumber('id')->name('files');
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/partners', [AdminController::class, 'queue'])->defaults('type', 'partners')->name('admin.partners');
        Route::post('/admin/{type}/{id}/review', [AdminController::class, 'review'])->where('type', 'partners|plots')->whereNumber('id');
        Route::get('/admin/plots', [AdminController::class, 'queue'])->defaults('type', 'plots')->name('admin.plots');
        Route::get('/admin/plantings', [FieldReviewController::class, 'plantings'])->name('admin.plantings');
        Route::post('/admin/plantings/{planting}/review', [FieldReviewController::class, 'reviewPlanting'])->whereNumber('planting')->name('admin.plantings.review');
        Route::get('/admin/monitorings', [FieldReviewController::class, 'monitorings'])->name('admin.monitorings');
        Route::post('/admin/monitorings/{monitoring}/review', [FieldReviewController::class, 'reviewMonitoring'])->whereNumber('monitoring')->name('admin.monitorings.review');
        Route::get('/admin/catalog', [AdminController::class, 'catalog'])->name('admin.catalog');
        Route::post('/admin/catalog', [AdminController::class, 'saveOffer']);
        Route::get('/admin/disbursements', [AdminController::class, 'funds'])->name('admin.funds');
        Route::post('/admin/disbursements/{disbursement}/review', [AdminController::class, 'reviewFunds'])->whereNumber('disbursement');
        Route::post('/admin/disbursements/{disbursement}/transfer', [AdminController::class, 'recordTransfer'])->whereNumber('disbursement');
        Route::post('/admin/allocations/{work}', [AdminController::class, 'allocation'])->whereNumber('work');
        Route::view('/admin/reports', 'portal.placeholder', ['pageTitle' => 'Rekapitulasi dan Laporan'])->name('admin.reports');
        Route::get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
        Route::post('/admin/settings/terms', [AdminController::class, 'rules']);
    });
    Route::middleware('role:partner')->group(function () {
        Route::get('/partner/partnership', [PartnerController::class, 'partnership'])->name('partner.partnership');
        Route::post('/partner/partnership', [PartnerController::class, 'savePartnership']);
        Route::get('/partner/plots', [PartnerController::class, 'plots'])->name('partner.plots');
        Route::post('/partner/plots', [PartnerController::class, 'savePlot']);
        Route::get('/partner/work-orders', [PartnerController::class, 'workOrders'])->name('partner.works');
        Route::post('/partner/trees/{tree}/planting', [PartnerController::class, 'planting'])->whereNumber('tree')->name('partner.planting');
        Route::get('/partner/work-orders/{work}', [PartnerController::class, 'workOrder'])->whereNumber('work')->name('partner.work');
        Route::get('/partner/monitoring', [PartnerController::class, 'monitoring'])->name('partner.monitoring');
        Route::post('/partner/trees/{tree}/monitoring', [PartnerController::class, 'saveMonitoring'])->whereNumber('tree')->name('partner.monitoring.store');
        Route::get('/partner/funds', [PartnerController::class, 'funds'])->name('partner.funds');
        Route::post('/partner/funds', [PartnerController::class, 'submitFunds']);
        Route::get('/partner/members', [PartnerController::class, 'members'])->name('partner.members');
        Route::post('/partner/members', [PartnerController::class, 'saveMember']);
        Route::put('/partner/members/{member}', [PartnerController::class, 'updateMember'])->whereNumber('member');
        Route::delete('/partner/members/{member}', [PartnerController::class, 'deleteMember'])->whereNumber('member');
    });
    Route::middleware('role:donor')->group(function () {
        Route::get('/donor/catalog', [PublicCatalogController::class, 'catalog'])->name('donor.catalog');
        Route::get('/trees', [AdoptionController::class, 'trees'])->name('trees');
        Route::get('/map/data', [AdoptionController::class, 'mapData'])->name('map.data');
        Route::get('/certificates', [AdoptionController::class, 'certificates'])->name('certificates');
        Route::get('/orders/{order}/certificate', [AdoptionController::class, 'certificate'])->whereNumber('order')->name('orders.certificate');
        Route::get('/orders', [AdoptionController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AdoptionController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::post('/orders/{order}/sync', [AdoptionController::class, 'sync'])->whereNumber('order')->middleware('throttle:10,1')->name('orders.sync');
        Route::post('/orders/{order}/pay', [AdoptionController::class, 'pay'])->whereNumber('order')->middleware('throttle:10,1')->name('orders.pay');
        Route::post('/orders', [AdoptionController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
        Route::get('/checkout/{offer}', [AdoptionController::class, 'checkout'])->whereNumber('offer')->name('checkout');
    });
});
