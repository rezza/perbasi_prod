<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Struktur;

class APIStrukturController extends Controller
{
    protected $struktur;

    public function __construct()
    {
        $this->struktur = new Struktur();
    }

    /**
     * 1. List Struktur Organisasi
     */
    public function getListStruktur(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;

        try {
            $list = $this->struktur->getStrukturList($keyword);

            $paged = GlobalHelperController::paginate($list, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data struktur berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Detail Struktur
     */
    public function getDetailStruktur(Request $request)
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
            $detail = $this->struktur->getStrukturDetail($id);
            if (!$detail) {
                return ResponseController::sendError(97);
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail struktur berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Add Struktur
     */
    public function addStruktur(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $required = ['nama', 'jabatan', 'urutan', 'filename'];
        foreach ($required as $field) {
            if ($request->$field === null || $request->$field === '') {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $urutan = (int) $request->urutan;
        if ($urutan < 1) {
            return ResponseController::sendErrorData(98, ['field' => 'urutan must be >= 1']);
        }

        try {
            $struktur = new Struktur([
                'nama' => $request->nama,
                'jabatan' => $request->jabatan,
                'filename' => $request->filename,
                'urutan' => $urutan,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $struktur->save();

            return ResponseController::sendSuccess([
                'message' => 'Struktur berhasil ditambahkan',
                'data' => [
                    'id' => md5($struktur->id . $this->struktur->imbuhan),
                    'nama' => $struktur->nama,
                    'jabatan' => $struktur->jabatan,
                    'urutan' => $struktur->urutan
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Edit Struktur
     */
    public function editStruktur(Request $request)
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
            $struktur = $this->struktur->getStrukturByEncId($id);
            if (!$struktur) {
                return ResponseController::sendError(97);
            }

            $update = Struktur::find($struktur->id);
            if (!$update) {
                return ResponseController::sendError(97);
            }

            if (!empty($request->nama)) {
                $update->nama = $request->nama;
            }
            if (!empty($request->jabatan)) {
                $update->jabatan = $request->jabatan;
            }
            if ($request->has('filename')) {
                $update->filename = $request->filename;
            }
            if ($request->urutan !== null && $request->urutan !== '') {
                $urutan = (int) $request->urutan;
                if ($urutan < 1) {
                    return ResponseController::sendErrorData(98, ['field' => 'urutan must be >= 1']);
                }
                $update->urutan = $urutan;
            }

            $update->save();

            return ResponseController::sendSuccess([
                'message' => 'Data struktur berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete Struktur
     */
    public function deleteStruktur(Request $request)
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
            $struktur = $this->struktur->getStrukturByEncId($id);
            if (!$struktur) {
                return ResponseController::sendError(97);
            }

            $delete = Struktur::find($struktur->id);
            $delete->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data struktur berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 6. Upload Image Struktur
     */
    public function uploadImageStruktur(Request $request)
    {
        // Endpoint upload dipanggil dari halaman admin sebelum CRUD final.

        $filename = $request->filename ?? 'noname';
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

        $targetDir = public_path('struktur');
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
