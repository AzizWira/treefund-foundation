<?php
use Illuminate\Support\Facades\Route;
Route::view('/', 'foundation-start', ['pageTitle' => 'Beranda'])->name('home');
Route::view('/catalog', 'foundation-start', ['pageTitle' => 'Katalog Lahan'])->name('catalog');
Route::view('/peta', 'foundation-start', ['pageTitle' => 'Peta Lahan'])->name('public.map');
Route::get('/peta/data', fn () => response()->json([]))->name('public.map.data');
Route::view('/informasi/tentang', 'foundation-start', ['pageTitle' => 'Tentang TreeFund'])->name('about');
Route::view('/informasi/{topic}', 'foundation-start', ['pageTitle' => 'Informasi TreeFund'])->name('information');
Route::view('/faq', 'foundation-start', ['pageTitle' => 'FAQ'])->name('faq');
