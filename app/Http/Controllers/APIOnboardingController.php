<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Onboarding;

class APIOnboardingController extends Controller
{
    protected $onboarding;

    public function __construct()
    {
        $this->onboarding = new Onboarding();
    }

    /**
     * 1. Add Onboarding
     */
    public function addOnboarding(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['konten'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $konten = $request->konten;
        $filename = $request->filename ?? null;
        $judul = $request->judul ?? null;
        $urutan = $request->urutan ?? null;

        try {
            // Create onboarding
            $onboarding = new Onboarding([
                'konten' => $konten,
                'filename' => $filename,
                'judul' => $judul,
                'urutan' => $urutan
            ]);
            $onboarding->save();

            return ResponseController::sendSuccess([
                'message' => 'Onboarding berhasil ditambahkan',
                'data' => [
                    'filename' => $filename,
                    'judul' => $judul
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Get List Onboarding
     */
    public function getListOnboarding(Request $request)
    {
        // Validate JWT Token
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        try {
            $onboardingList = $this->onboarding->getOnboardings();

            $keyword = $request->keyword;
            if (!empty($keyword)) {
                $kw = mb_strtolower($keyword);
                $onboardingList = array_values(array_filter($onboardingList, function ($row) use ($kw) {
                    $judul = isset($row->judul) ? mb_strtolower((string) $row->judul) : '';
                    $konten = isset($row->konten) ? mb_strtolower((string) $row->konten) : '';
                    return strpos($judul, $kw) !== false || strpos($konten, $kw) !== false;
                }));
            }

            $paged = GlobalHelperController::paginate($onboardingList, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data onboarding berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Get Detail Onboarding
     */
    public function getDetailOnboarding(Request $request)
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
            // Get onboarding detail
            $onboarding = DB::select(
                'SELECT 
                    md5(concat(o.id, "'.$this->onboarding->imbuhan.'")) AS encrypted_id,
                    o.filename,
                    o.konten,
                    o.judul,
                    o.urutan
                FROM m_onboarding o
                WHERE md5(concat(o.id, "'.$this->onboarding->imbuhan.'")) = ?
                ', [$id]
            );

            if (empty($onboarding)) {
                return ResponseController::sendError(50); // Onboarding tidak ditemukan
            }

            $detail = $onboarding[0];

            return ResponseController::sendSuccess([
                'message' => 'Detail onboarding berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Edit Onboarding
     */
    public function editOnboarding(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $konten = $request->konten;
        $filename = $request->filename;
        $judul = $request->judul;
        $urutan = $request->urutan;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get onboarding
            $onboarding = $this->onboarding->getOnboardingByEncId($id);
            if (!$onboarding) {
                return ResponseController::sendError(50); // Onboarding tidak ditemukan
            }

            // Find onboarding model
            $updateOnboarding = Onboarding::find($onboarding->id);
            if (!$updateOnboarding) {
                return ResponseController::sendError(50);
            }

            // Update fields if provided
            if (!empty($konten)) {
                $updateOnboarding->konten = $konten;
            }

            if (isset($filename)) {
                $updateOnboarding->filename = $filename;
            }

            if (isset($judul)) {
                $updateOnboarding->judul = 
                $judul;
            }
            
            if (isset($urutan)) {
                $updateOnboarding->urutan = $urutan;
            }

            $updateOnboarding->save();

            return ResponseController::sendSuccess([
                'message' => 'Data onboarding berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete Onboarding (Hard Delete)
     */
    public function deleteOnboarding(Request $request)
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
            // Get onboarding
            $onboarding = $this->onboarding->getOnboardingByEncId($id);
            if (!$onboarding) {
                return ResponseController::sendError(50); // Onboarding tidak ditemukan
            }

            // Hard delete (no status field in m_onboarding)
            $deleteOnboarding = Onboarding::find($onboarding->id);
            $deleteOnboarding->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data onboarding berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function uploadImageOnboarding(Request $request){
        $filename = 'noname';

        if($request->filename){
            $filename = $request->filename;
        }
 
        if($request->hasfile('uploaded_file')){
            $file = $request->file('uploaded_file');
            $file->move('onboarding', $filename); 
        }
        
        return ResponseController::sendSuccess([
            'message' => 'success', 
        ]); 
    }

}
