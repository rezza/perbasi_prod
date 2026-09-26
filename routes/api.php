<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ==================== PUBLIC ROUTES (No JWT Required) ====================

// User Authentication
Route::post('/login', [\App\Http\Controllers\APIUserController::class, 'submitLogin']);
Route::post('/login-admin', [\App\Http\Controllers\APIUserController::class, 'submitLoginAdmin']);
Route::post('/register', [\App\Http\Controllers\APIUserController::class, 'registerUser']);
Route::post('/sso/exchange', [\App\Http\Controllers\APIUserController::class, 'ssoExchange']);

Route::post('/helper/provinsi', [\App\Http\Controllers\APIDashboardController::class, 'getProvinsi']);
Route::post('/helper/kota', [\App\Http\Controllers\APIDashboardController::class, 'getKota']);

Route::post('/forgot-password', [\App\Http\Controllers\APIUserController::class, 'requestResetPassword']);
Route::post('/verify-otp', [\App\Http\Controllers\APIUserController::class, 'verifyOTP']);
Route::post('/reset-password', [\App\Http\Controllers\APIUserController::class, 'resetPasswordWithOTP']);

Route::post('/onboarding/list', [\App\Http\Controllers\APIOnboardingController::class, 'getListOnboarding']);

Route::get('/public/stats',  [\App\Http\Controllers\APIDashboardController::class, 'getPublicStats']);
Route::get('/public/pemain',   [\App\Http\Controllers\ComproController::class, 'apiDaftarPemain']);
Route::get('/public/pengurus', [\App\Http\Controllers\ComproController::class, 'apiDaftarPengurus']);

// Upload image endpoints (public - dipanggil dari halaman admin sebelum auth header siap)
Route::post('/sponsor/uploadimage', [\App\Http\Controllers\APISponsorController::class, 'uploadImageSponsor']);
Route::post('/banner-program/uploadimage', [\App\Http\Controllers\APIBannerProgramController::class, 'uploadImageBannerProgram']);
Route::post('/struktur/uploadimage', [\App\Http\Controllers\APIStrukturController::class, 'uploadImageStruktur']);
Route::post('/perkumpulan/uploadlogo', [\App\Http\Controllers\APIPerkumpulanController::class, 'uploadLogo']);
Route::post('/news/uploadimage', [\App\Http\Controllers\APINewsController::class, 'uploadImageNews']);
Route::post('/onboarding/uploadimage', [\App\Http\Controllers\APIOnboardingController::class, 'uploadImageOnboarding']);
Route::post('/banner/list', [\App\Http\Controllers\APIBannerController::class, 'getListBanner']);
Route::post('/news/list', [\App\Http\Controllers\APINewsController::class, 'getListNews']);


Route::post('/testnotification', [\App\Http\Controllers\APIUserController::class, 'testNotificationSingle']);
 

// ==================== PROTECTED ROUTES (JWT Required) ====================

Route::middleware(['jwt.verify'])->group(function () {
    
    // User Management
    Route::post('/user/list', [\App\Http\Controllers\APIUserController::class, 'getUserList']);
    Route::post('/user/profile', [\App\Http\Controllers\APIUserController::class, 'getProfile']);
    Route::post('/user/member-detail', [\App\Http\Controllers\APIUserController::class, 'getMemberDetail']);
    Route::post('/user/reset-password', [\App\Http\Controllers\APIUserController::class, 'resetPasswordUserByAdmin']);
    Route::post('/user/change-password', [\App\Http\Controllers\APIUserController::class, 'ubahPassword']);
    Route::post('/user/update-profile', [\App\Http\Controllers\APIUserController::class, 'updateProfile']);
    Route::post('/user/delete', [\App\Http\Controllers\APIUserController::class, 'deleteUserByAdmin']);
    Route::post('/user/ubah-posisi-terkini', [\App\Http\Controllers\APIUserController::class, 'ubahPosisiTerkini']);
    Route::post('/user/upload-image-profil', [\App\Http\Controllers\APIUserController::class, 'uploadImageProfil']);
    Route::post('/user/update-profile-image', [\App\Http\Controllers\APIUserController::class, 'updateProfileImage']);

    // Admin Management
    Route::post('/admin/search-by-email', [\App\Http\Controllers\APIAdminController::class, 'searchUserByEmail']);
    Route::post('/admin/register', [\App\Http\Controllers\APIAdminController::class, 'registerUserAdmin']);
    Route::post('/admin/list', [\App\Http\Controllers\APIAdminController::class, 'getListAdmin']);
    Route::post('/admin/detail', [\App\Http\Controllers\APIAdminController::class, 'getDetailAdmin']);
    Route::post('/admin/update', [\App\Http\Controllers\APIAdminController::class, 'updateAdmin']);
    Route::post('/admin/update-status', [\App\Http\Controllers\APIAdminController::class, 'updateAdminStatus']);
    Route::post('/admin/delete', [\App\Http\Controllers\APIAdminController::class, 'deleteAdmin']);
    
    // Entitas Management
    Route::post('/entitas/create', [\App\Http\Controllers\APIEntitasController::class, 'createEntitas']);
    Route::post('/entitas/list', [\App\Http\Controllers\APIEntitasController::class, 'getListEntitas']);
    Route::post('/entitas/detail', [\App\Http\Controllers\APIEntitasController::class, 'getDetailEntitas']);
    Route::post('/entitas/update', [\App\Http\Controllers\APIEntitasController::class, 'updateEntitas']);
    Route::post('/entitas/delete', [\App\Http\Controllers\APIEntitasController::class, 'deleteEntitas']);
    
    // Perkumpulan Management
    Route::post('/perkumpulan/create', [\App\Http\Controllers\APIPerkumpulanController::class, 'createPerkumpulan']);
    Route::post('/perkumpulan/list', [\App\Http\Controllers\APIPerkumpulanController::class, 'getListPerkumpulan']);
    Route::post('/perkumpulan/detail', [\App\Http\Controllers\APIPerkumpulanController::class, 'getDetailPerkumpulan']);
    Route::post('/perkumpulan/update', [\App\Http\Controllers\APIPerkumpulanController::class, 'updatePerkumpulan']);
    Route::post('/perkumpulan/delete', [\App\Http\Controllers\APIPerkumpulanController::class, 'deletePerkumpulan']);
    
    // Posisi Management
    Route::post('/posisi/create', [\App\Http\Controllers\APIPosisiController::class, 'createPosisi']);
    Route::post('/posisi/list', [\App\Http\Controllers\APIPosisiController::class, 'getListPosisi']);
    Route::post('/posisi/detail', [\App\Http\Controllers\APIPosisiController::class, 'getDetailPosisi']);
    Route::post('/posisi/update', [\App\Http\Controllers\APIPosisiController::class, 'updatePosisi']);
    Route::post('/posisi/delete', [\App\Http\Controllers\APIPosisiController::class, 'deletePosisi']);
    
    // News Management
    Route::post('/news/add', [\App\Http\Controllers\APINewsController::class, 'addNews']);
    Route::post('/news/detail', [\App\Http\Controllers\APINewsController::class, 'getDetailNews']);
    Route::post('/news/edit', [\App\Http\Controllers\APINewsController::class, 'editNews']);
    Route::post('/news/update-status-banner', [\App\Http\Controllers\APINewsController::class, 'updateStatusBanner']);
    Route::post('/news/delete', [\App\Http\Controllers\APINewsController::class, 'deleteNews']);
    Route::post('/news/getbanner', [\App\Http\Controllers\APINewsController::class, 'getListBanner']);
    
    // Banner Program Management
    Route::post('/banner-program/list', [\App\Http\Controllers\APIBannerProgramController::class, 'getListBannerProgram']);
    Route::post('/banner-program/detail', [\App\Http\Controllers\APIBannerProgramController::class, 'getDetailBannerProgram']);
    Route::post('/banner-program/add', [\App\Http\Controllers\APIBannerProgramController::class, 'addBannerProgram']);
    Route::post('/banner-program/edit', [\App\Http\Controllers\APIBannerProgramController::class, 'editBannerProgram']);
    Route::post('/banner-program/delete', [\App\Http\Controllers\APIBannerProgramController::class, 'deleteBannerProgram']);

    // Banner Management
    Route::post('/banner/add', [\App\Http\Controllers\APIBannerController::class, 'addBanner']);
    Route::post('/banner/detail', [\App\Http\Controllers\APIBannerController::class, 'getDetailBanner']);
    Route::post('/banner/edit', [\App\Http\Controllers\APIBannerController::class, 'editBanner']);
    Route::post('/banner/delete', [\App\Http\Controllers\APIBannerController::class, 'deleteBanner']);
    
    // Onboarding Management
    Route::post('/onboarding/add', [\App\Http\Controllers\APIOnboardingController::class, 'addOnboarding']);
    Route::post('/onboarding/detail', [\App\Http\Controllers\APIOnboardingController::class, 'getDetailOnboarding']);
    Route::post('/onboarding/edit', [\App\Http\Controllers\APIOnboardingController::class, 'editOnboarding']);
    Route::post('/onboarding/delete', [\App\Http\Controllers\APIOnboardingController::class, 'deleteOnboarding']);

    // Pengajuan Management
    Route::post('/pengajuan/pemain', [\App\Http\Controllers\APIPengajuanController::class, 'pengajuanPemain']);
    Route::post('/pengajuan/edit-pemain', [\App\Http\Controllers\APIPengajuanController::class, 'editPengajuanPemain']);
    Route::post('/pengajuan/sdm', [\App\Http\Controllers\APIPengajuanController::class, 'pengajuanSDM']);
    Route::post('/pengajuan/edit-sdm', [\App\Http\Controllers\APIPengajuanController::class, 'editPengajuanSDM']);
    Route::post('/pengajuan/detail-pemain', [\App\Http\Controllers\APIPengajuanController::class, 'detailPengajuanPemain']);
    Route::post('/pengajuan/detail-sdm', [\App\Http\Controllers\APIPengajuanController::class, 'detailPengajuanSDM']);
    Route::post('/pengajuan/list', [\App\Http\Controllers\APIPengajuanController::class, 'listPengajuan']);
    Route::post('/pengajuan/approve-perkumpulan', [\App\Http\Controllers\APIPengajuanController::class, 'approvePerkumpulan']);
    Route::post('/pengajuan/reject-perkumpulan', [\App\Http\Controllers\APIPengajuanController::class, 'rejectPerkumpulan']);
    Route::post('/pengajuan/approve-master', [\App\Http\Controllers\APIPengajuanController::class, 'approveMaster']);
    Route::post('/pengajuan/reject-master', [\App\Http\Controllers\APIPengajuanController::class, 'rejectMaster']);
    Route::post('/pengajuan/upload-file', [\App\Http\Controllers\APIPengajuanController::class, 'uploadFilePengajuan']);
    Route::post('/pengajuan/notify-admin', [\App\Http\Controllers\APIPengajuanController::class, 'notifyAdmin']);
    
    // Sponsor & Mitra Management
    Route::post('/sponsor/list', [\App\Http\Controllers\APISponsorController::class, 'getListSponsor']);
    Route::post('/sponsor/detail', [\App\Http\Controllers\APISponsorController::class, 'getDetailSponsor']);
    Route::post('/sponsor/add', [\App\Http\Controllers\APISponsorController::class, 'addSponsor']);
    Route::post('/sponsor/edit', [\App\Http\Controllers\APISponsorController::class, 'editSponsor']);
    Route::post('/sponsor/delete', [\App\Http\Controllers\APISponsorController::class, 'deleteSponsor']);

    // Visi & Misi Management
    Route::post('/visi-misi/list', [\App\Http\Controllers\APIVisiMisiController::class, 'getListVisiMisi']);
    Route::post('/visi-misi/add', [\App\Http\Controllers\APIVisiMisiController::class, 'addVisiMisi']);
    Route::post('/visi-misi/edit', [\App\Http\Controllers\APIVisiMisiController::class, 'editVisiMisi']);
    Route::post('/visi-misi/delete', [\App\Http\Controllers\APIVisiMisiController::class, 'deleteVisiMisi']);

    // Struktur Organisasi Management
    Route::post('/struktur/list', [\App\Http\Controllers\APIStrukturController::class, 'getListStruktur']);
    Route::post('/struktur/detail', [\App\Http\Controllers\APIStrukturController::class, 'getDetailStruktur']);
    Route::post('/struktur/add', [\App\Http\Controllers\APIStrukturController::class, 'addStruktur']);
    Route::post('/struktur/edit', [\App\Http\Controllers\APIStrukturController::class, 'editStruktur']);
    Route::post('/struktur/delete', [\App\Http\Controllers\APIStrukturController::class, 'deleteStruktur']);

    // Helper Data
    Route::post('/helper/splashscreen', [\App\Http\Controllers\APIDashboardController::class, 'getHelper']);

    // Perkumpulan the logged-in user is an approved member of
    Route::post('/user/my-perkumpulan', [\App\Http\Controllers\APIUserController::class, 'myPerkumpulan']);

    // Master Persyaratan Event
    Route::post('/master-persyaratan-event/list', [\App\Http\Controllers\APIMasterPersyaratanEventController::class, 'getList']);
    Route::post('/master-persyaratan-event/create', [\App\Http\Controllers\APIMasterPersyaratanEventController::class, 'create']);
    Route::post('/master-persyaratan-event/update', [\App\Http\Controllers\APIMasterPersyaratanEventController::class, 'update']);
    Route::post('/master-persyaratan-event/delete', [\App\Http\Controllers\APIMasterPersyaratanEventController::class, 'delete']);

    // Event Pendaftaran - Admin config
    Route::post('/event-pendaftaran/create', [\App\Http\Controllers\APIEventPendaftaranController::class, 'createEvent']);
    Route::post('/event-pendaftaran/bulk-create', [\App\Http\Controllers\APIEventPendaftaranController::class, 'bulkCreateEvents']);
    Route::post('/event-pendaftaran/bulk-update', [\App\Http\Controllers\APIEventPendaftaranController::class, 'bulkUpdateEvents']);
    Route::post('/event-pendaftaran/list-by-news', [\App\Http\Controllers\APIEventPendaftaranController::class, 'getListByNews']);
    Route::post('/event-pendaftaran/list', [\App\Http\Controllers\APIEventPendaftaranController::class, 'getListEvent']);
    Route::post('/event-pendaftaran/detail', [\App\Http\Controllers\APIEventPendaftaranController::class, 'getDetailEvent']);
    Route::post('/event-pendaftaran/update', [\App\Http\Controllers\APIEventPendaftaranController::class, 'updateEvent']);
    Route::post('/event-pendaftaran/update-status', [\App\Http\Controllers\APIEventPendaftaranController::class, 'updateStatusEvent']);
    Route::post('/event-pendaftaran/delete', [\App\Http\Controllers\APIEventPendaftaranController::class, 'deleteEvent']);

    // Event Pendaftaran - Member browse & register
    Route::post('/event-pendaftaran/detail-for-member', [\App\Http\Controllers\APIEventPendaftaranController::class, 'detailEventForMember']);
    Route::post('/event-peserta/upload-file', [\App\Http\Controllers\APIEventPendaftaranController::class, 'uploadFilePeserta']);
    Route::post('/event-peserta/daftar', [\App\Http\Controllers\APIEventPendaftaranController::class, 'daftarPeserta']);
    Route::post('/event-peserta/status', [\App\Http\Controllers\APIEventPendaftaranController::class, 'statusPeserta']);
    Route::post('/event-peserta/my-list', [\App\Http\Controllers\APIEventPendaftaranController::class, 'myEventList']);

    // Event Pendaftaran - Admin approval & report
    Route::post('/event-peserta/list', [\App\Http\Controllers\APIEventPendaftaranController::class, 'getListPeserta']);
    Route::post('/event-peserta/detail', [\App\Http\Controllers\APIEventPendaftaranController::class, 'getDetailPeserta']);
    Route::post('/event-peserta/approve', [\App\Http\Controllers\APIEventPendaftaranController::class, 'approvePeserta']);
    Route::post('/event-peserta/reject', [\App\Http\Controllers\APIEventPendaftaranController::class, 'rejectPeserta']);
    Route::post('/event-peserta/report', [\App\Http\Controllers\APIEventPendaftaranController::class, 'reportPeserta']);
    Route::post('/event-peserta/bulk-add', [\App\Http\Controllers\APIEventPendaftaranController::class, 'bulkAddPeserta']);
    Route::post('/event-peserta/search-member', [\App\Http\Controllers\APIEventPendaftaranController::class, 'searchMemberForEvent']);

    // Pertandingan (jadwal + check-in) - Admin config
    Route::post('/pertandingan/save', [\App\Http\Controllers\APIPertandinganController::class, 'savePertandingan']);
    Route::post('/pertandingan/detail', [\App\Http\Controllers\APIPertandinganController::class, 'getDetailPertandingan']);
    Route::post('/pertandingan/list', [\App\Http\Controllers\APIPertandinganController::class, 'getListPertandingan']);

    // Pertandingan - Member check-in
    Route::post('/pertandingan/detail-for-member', [\App\Http\Controllers\APIPertandinganController::class, 'detailForMember']);
    Route::post('/pertandingan/checkin', [\App\Http\Controllers\APIPertandinganController::class, 'submitCheckin']);
    Route::post('/pertandingan/attendance-list', [\App\Http\Controllers\APIPertandinganController::class, 'getAttendanceList']);

    // Dashboard
    Route::post('/member/dashboard', [\App\Http\Controllers\APIDashboardController::class, 'getMemberDashboard']);
    Route::post('/dashboard/news/detail', [\App\Http\Controllers\APIDashboardController::class, 'getNewsDetail']);
    Route::post('/dashboard/banner/detail', [\App\Http\Controllers\APIDashboardController::class, 'getBannerDetail']);
    Route::post('/dashboard/news/kategori', [\App\Http\Controllers\APIDashboardController::class, 'getNewsByKategori']);

});
