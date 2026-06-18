<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/clear-config', function () {
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('route:clear');
    return "Config cleared and cached.";
});


Route::get('privacy-policy', function () {
    return view('privacypolicy');
});

/*
|--------------------------------------------------------------------------
| Company Profile (Compro) Routes
|--------------------------------------------------------------------------
| Sementara menggunakan prefix /compro karena / masih dipakai welcome screen
| selama development.
*/
Route::name('compro.')->controller(\App\Http\Controllers\ComproController::class)->group(function () {
    Route::get('/',                'dashboard')->name('dashboard');
    Route::get('/tentang-kami',    'tentang')->name('tentang');
    Route::get('/visi-misi/{id}',  'visiMisiDetail')->name('visi-misi.detail');
    Route::get('/perkumpulan',      'perkumpulan')->name('perkumpulan');
    Route::get('/perkumpulan/{id}', 'perkumpulanDetail')->name('perkumpulan.detail');
    Route::get('/daftar-pemain',    'daftarPemain')->name('daftar-pemain');
    Route::get('/daftar-pengurus',  'daftarPengurus')->name('daftar-pengurus');

    Route::get('/agenda',          'agenda')->name('agenda');
    Route::get('/agenda/{id}',     'agendaDetail')->name('agenda.detail');

    Route::get('/sponsor',         'sponsor')->name('sponsor');
    Route::get('/sponsor/{id}',    'sponsorDetail')->name('sponsor.detail');

    Route::get('/berita',          'berita')->name('berita');
    Route::get('/berita/{id}',     'beritaDetail')->name('berita.detail');

    Route::get('/program/{id}',    'bannerProgramDetail')->name('program.detail');
});

Route::get('send-email', 'App\Http\Controllers\EmailController@showForm')->name('send-email.form');
Route::post('send-email', 'App\Http\Controllers\EmailController@sendEmail')->name('send-email.submit');
