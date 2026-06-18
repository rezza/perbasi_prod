<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Posisi;
use App\Entitas;
use App\StatusAnggota;
use App\Provinsi;
use App\Kota;
use App\Banner;
use App\News;
use App\User;
use App\Perkumpulan;
use App\News as NewsModel;

class APIDashboardController extends Controller
{
    protected $posisi;
    protected $entitas;
    protected $statusAnggota;
    protected $provinsi;
    protected $kota;
    protected $banner;
    protected $news;
    protected $user;

    public function __construct()
    {
        $this->posisi = new Posisi();
        $this->entitas = new Entitas();
        $this->statusAnggota = new StatusAnggota();
        $this->provinsi = new Provinsi();
        $this->kota = new Kota();
        $this->banner = new Banner();
        $this->news = new News();
        $this->user = new User();
    }

    /**
     * Get Public Stats (no auth required)
     * Returns counts of active clubs, members, and events for the company profile page.
     */
    public function getPublicStats(Request $request)
    {
        try {
            $jumlah_klub    = Perkumpulan::where('status', 1)->count();
            $jumlah_anggota = User::where('status', 1)->count();
            $jumlah_event   = NewsModel::where('kategori', 2)->where('status', 1)->count();

            return ResponseController::sendSuccess([
                'message' => 'Data statistik berhasil diambil',
                'data'    => [
                    'jumlah_klub'    => $jumlah_klub,
                    'jumlah_anggota' => $jumlah_anggota,
                    'jumlah_event'   => $jumlah_event,
                ],
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get Helper Data for Admin
     * Data master: posisi, entitas, status anggota
     */
    public function getHelper(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        try {
            $data = [
                'posisi' => $this->posisi->getPosisis(),
                'entitas' => $this->entitas->getEntitas(null, null, 1),
                'status_anggota' => $this->statusAnggota->getStatusAnggotas(),
            ];

            $fcmtoken = $request->fcmtoken;
            $os_type = $request->osType;
            $os_version = $request->osVersion;
            $userUpdate = User::find($currentUser["user_id"]);
            if($fcmtoken != "" && $fcmtoken != "-"){
                $userUpdate->fcm_token = $fcmtoken;
            }
            if($os_type != "" && $os_type != "-"){
                $userUpdate->os_type = $os_type;
                $userUpdate->os_version = $os_version;
            }
            $userUpdate->save();

            return ResponseController::sendSuccess([
                'message' => 'Data helper berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get All Provinsi
     */
    public function getProvinsi(Request $request)
    {
        // // Validate JWT Token
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        try {
            $provinsi = $this->provinsi->getProvinsis();

            return ResponseController::sendSuccess([
                'message' => 'Data provinsi berhasil diambil',
                'data' => $provinsi
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get Kota by ID Provinsi
     */
    public function getKota(Request $request)
    {
        // // Validate JWT Token
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        $id_provinsi = $request->id_provinsi;

        if (empty($id_provinsi)) {
            return ResponseController::sendErrorData(98, ['field' => 'ID Provinsi is required']);
        }

        try {
            // Get provinsi untuk validasi
            $provinsi = $this->provinsi->getProvinsiByEncId($id_provinsi);
            if (!$provinsi) {
                return ResponseController::sendError(97);
            }

            $kota = $this->kota->getKotasByProvinsi($provinsi->id);

            return ResponseController::sendSuccess([
                'message' => 'Data kota berhasil diambil',
                'provinsi' => $provinsi,
                'data' => $kota
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get Member Dashboard
     * Data: banner, jadwal pertandingan (news kategori 2), news
     */
    public function getMemberDashboard(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_userenc = $request->id_user;
        $user = $this->user->getUserByEncId($id_userenc);
        if (!$user) {
            return ResponseController::sendError(code: 1);
        }

        try {
            // Get active banners
            $banners = $this->news->getActiveBanner();

            // Get jadwal pertandingan (news kategori = 2)
            $jadwal_pertandingan = $this->news->getActiveNews(2);

            // Get news (news kategori = 1)
            $news = $this->news->getActiveNews(1);

            $data = [
                'banners' => $banners,
                'jadwal_pertandingan' => $jadwal_pertandingan,
                'news' => $news,
            ];

            $fcmtoken = $request->fcmtoken;
            $os_type = $request->osType;
            $os_version = $request->osVersion;
            $userUpdate = User::find($user->id);
            if($fcmtoken != "" && $fcmtoken != "-"){
                $userUpdate->fcm_token = $fcmtoken;
            }
            if($os_type != "" && $os_type != "-"){
                $userUpdate->os_type = $os_type;
                $userUpdate->os_version = $os_version;
            }
            $userUpdate->save();

            return ResponseController::sendSuccess([
                'message' => 'Dashboard data berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }


    public function getNewsByKategori(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $kategori = $request->kategori;

        if (empty($kategori)) {
            return ResponseController::sendErrorData(98, ['field' => 'Kategori is required']);
        }

        try {
            $news = $this->news->getActiveNews($kategori);

            return ResponseController::sendSuccess([
                'message' => 'Data news berhasil diambil',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get News Detail by Encrypted ID
     */
    public function getNewsDetail(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_news = $request->id;

        if (empty($id_news)) {
            return ResponseController::sendErrorData(98, ['field' => 'ID News is required']);
        }

        try {
            $news = $this->news->getNewsByEncId($id_news);

            if (!$news) {
                return ResponseController::sendError(97);
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail news berhasil diambil',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get Banner Detail by Encrypted ID
     */
    public function getBannerDetail(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_banner = $request->id;

        if (empty($id_banner)) {
            return ResponseController::sendErrorData(98, ['field' => 'ID Banner is required']);
        }

        try {
            $banner = $this->banner->getBannerByEncId($id_banner);

            if (!$banner) {
                return ResponseController::sendError(97);
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail banner berhasil diambil',
                'data' => $banner
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
