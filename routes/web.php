<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/clear-config', function () {
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('route:clear');
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
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
    Route::get('/agenda/{id}/attendance', 'pertandinganAttendanceList')->name('agenda.checkin.attendance');
    Route::get('/event/{id}/attendance', 'eventAttendanceList')->name('event.checkin.attendance');

    Route::get('/sponsor',         'sponsor')->name('sponsor');
    Route::get('/sponsor/{id}',    'sponsorDetail')->name('sponsor.detail');

    Route::get('/berita',          'berita')->name('berita');
    Route::get('/berita/{id}',     'beritaDetail')->name('berita.detail');

    Route::get('/program/{id}',    'bannerProgramDetail')->name('program.detail');

    // Auth (session-based; bridges to the same JWT API the mobile app uses)
    Route::get('/masuk',        'loginForm')->name('login');
    Route::post('/masuk',       'loginSubmit');
    Route::get('/daftar-akun',  'registerForm')->name('register');
    Route::post('/daftar-akun', 'registerSubmit');
    Route::post('/keluar',      'logout')->name('logout');

    // Event registration (requires a logged-in compro session)
    Route::middleware('compro.auth')->group(function () {
        Route::get('/sso/member', 'ssoMemberRedirect')->name('sso.member');

        Route::get('/event/{id}',         'eventForm')->name('event.form');
        Route::post('/event/{id}/daftar', 'eventSubmit')->name('event.submit');
        Route::post('/event/upload-file', 'eventUploadFile')->name('event.upload');

        Route::post('/agenda/{id}/checkin', 'pertandinganCheckinSubmit')->name('agenda.checkin.submit');
        Route::post('/event/{id}/checkin', 'eventCheckinSubmit')->name('event.checkin.submit');
    });
});

Route::get('send-email', 'App\Http\Controllers\EmailController@showForm')->name('send-email.form');
Route::post('send-email', 'App\Http\Controllers\EmailController@sendEmail')->name('send-email.submit');
