<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Sponsor;

class APISponsorController extends Controller
{
    protected $sponsor;

    public function __construct()
    {
        $this->sponsor = new Sponsor();
    }

    /**
     * 1. List Sponsor
     */
    public function getListSponsor(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;

        try {
            $list = $this->sponsor->getSponsorList($keyword);

            $paged = GlobalHelperController::paginate($list, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data sponsor berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Detail Sponsor
     */
    public function getDetailSponsor(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $detail = $this->sponsor->getSponsorDetail($id);
            if (!$detail) {
                return ResponseController::sendError(97);
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail sponsor berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Add Sponsor
     */
    public function addSponsor(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $required = ['nama', 'deskripsi', 'filename'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        try {
            $sponsor = new Sponsor([
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi,
                'filename' => $request->filename,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $sponsor->save();

            return ResponseController::sendSuccess([
                'message' => 'Sponsor berhasil ditambahkan',
                'data' => [
                    'id' => md5($sponsor->id . $this->sponsor->imbuhan),
                    'nama' => $sponsor->nama,
                    'filename' => $sponsor->filename
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Edit Sponsor
     */
    public function editSponsor(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $sponsor = $this->sponsor->getSponsorByEncId($id);
            if (!$sponsor) {
                return ResponseController::sendError(97);
            }

            $update = Sponsor::find($sponsor->id);
            if (!$update) {
                return ResponseController::sendError(97);
            }

            if (!empty($request->nama)) {
                $update->nama = $request->nama;
            }
            if ($request->has('deskripsi')) {
                $update->deskripsi = $request->deskripsi;
            }
            if ($request->has('filename')) {
                $update->filename = $request->filename;
            }

            $update->save();

            return ResponseController::sendSuccess([
                'message' => 'Data sponsor berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete Sponsor (hard delete)
     */
    public function deleteSponsor(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $sponsor = $this->sponsor->getSponsorByEncId($id);
            if (!$sponsor) {
                return ResponseController::sendError(97);
            }

            $delete = Sponsor::find($sponsor->id);
            $delete->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data sponsor berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 6. Upload Image Sponsor
     */
    public function uploadImageSponsor(Request $request)
    {
        // Endpoint upload dipanggil dari halaman admin sebelum CRUD final.
        // JWT validasi opsional di sini (route sudah dipindah ke public group).

        $filename = $request->filename ?? 'noname';

        // Sanitize filename: ambil basename saja & buang prefix "blob:" jika dikirim client.
        $filename = basename($filename);
        if (stripos($filename, 'blob:') === 0) {
            return ResponseController::sendErrorData(98, [
                'field' => 'filename should be a plain file name, got blob URL'
            ]);
        }

        $file = null;
        foreach (['uploaded_file', 'file', 'image'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                break;
            }
        }

        if (!$file) {
            return ResponseController::sendErrorData(98, [
                'field' => 'file (binary) is required (key: uploaded_file / file / image)'
            ]);
        }

        $targetDir = public_path('sponsor');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        try {
            $file->move($targetDir, $filename);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }

        return ResponseController::sendSuccess([
            'message' => 'success',
            'filename' => $filename
        ]);
    }
}
