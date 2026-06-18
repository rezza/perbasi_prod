<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Banner;
use App\Admin;

class APIBannerController extends Controller
{
    protected $banner;
    protected $admin;

    public function __construct()
    {
        $this->banner = new Banner();
        $this->admin = new Admin();
    }

    /**
     * 1. Add Banner
     */
    public function addBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['judul', 'tanggal', 'konten', 'id_admin'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $judul = $request->judul;
        $tanggal = $request->tanggal;
        $konten = $request->konten;
        $id_admin = $request->id_admin;
        $filename = $request->filename ?? null;

        try {
            // Validate admin exists
            $admin = $this->admin->getAdminByEncId($id_admin);
            if (!$admin) {
                return ResponseController::sendError(47); // Admin tidak ditemukan
            }

            // Create banner
            $banner = new Banner([
                'judul' => $judul,
                'tanggal' => $tanggal,
                'konten' => $konten,
                'id_admin' => $admin->id,
                'filename' => $filename,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $banner->save();

            return ResponseController::sendSuccess([
                'message' => 'Banner berhasil ditambahkan',
                'data' => [
                    'id' => $banner->id,
                    'judul' => $judul,
                    'tanggal' => $tanggal
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Get List Banner
     */
    public function getListBanner(Request $request)
    {
        // Validate JWT Token
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        $keyword = $request->keyword;
        $status = $request->status ?? 1; // default: active only

        try {
            $bannerList = $this->banner->getBannerList($status, $keyword);

            $paged = GlobalHelperController::paginate($bannerList, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data banner berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Get Detail Banner
     */
    public function getDetailBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get banner detail with all information
            $banner = $this->banner->getDetailBanner($id);
            if (!$banner) {
                return ResponseController::sendError(49); // Banner tidak ditemukan
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail banner berhasil diambil',
                'data' => $banner
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Edit Banner
     */
    public function editBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $judul = $request->judul;
        $tanggal = $request->tanggal;
        $konten = $request->konten;
        $id_admin = $request->id_admin;
        $filename = $request->filename;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get banner
            $banner = $this->banner->getBannerByEncId($id);
            if (!$banner) {
                return ResponseController::sendError(49); // Banner tidak ditemukan
            }

            // Find banner model
            $updateBanner = Banner::find($banner->id);
            if (!$updateBanner) {
                return ResponseController::sendError(49);
            }

            // Update fields if provided
            if (!empty($judul)) {
                $updateBanner->judul = $judul;
            }

            if (!empty($tanggal)) {
                $updateBanner->tanggal = $tanggal;
            }

            if (!empty($konten)) {
                $updateBanner->konten = $konten;
            }

            if (!empty($id_admin)) {
                $admin = $this->admin->getAdminByEncId($id_admin);
                if (!$admin) {
                    return ResponseController::sendError(47);
                }
                $updateBanner->id_admin = $admin->id;
            }

            if (isset($filename)) {
                $updateBanner->filename = $filename;
            }

            $updateBanner->save();

            return ResponseController::sendSuccess([
                'message' => 'Data banner berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete Banner (Soft Delete)
     */
    public function deleteBanner(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get banner
            $banner = $this->banner->getBannerByEncId($id);
            if (!$banner) {
                return ResponseController::sendError(49); // Banner tidak ditemukan
            }

            // Soft delete
            $deleteBanner = Banner::find($banner->id);
            $deleteBanner->status = -1;
            $deleteBanner->save();

            return ResponseController::sendSuccess([
                'message' => 'Data banner berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }



    public function uploadImageBanner(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $filename = 'noname';

        if($request->filename){
            $filename = $request->filename;
        }
 
        if($request->hasfile('uploaded_file')){
            $file = $request->file('uploaded_file');
            $file->move('banner', $filename); 
        }
        
        return ResponseController::sendSuccess([
            'message' => 'success', 
        ]); 
    }
}
