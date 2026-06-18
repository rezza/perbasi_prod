<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\VisiMisi;

class APIVisiMisiController extends Controller
{
    protected $visiMisi;

    public function __construct()
    {
        $this->visiMisi = new VisiMisi();
    }

    /**
     * 1. List Visi & Misi
     */
    public function getListVisiMisi(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;
        $tipe = $request->tipe; // optional: 'visi' / 'misi'

        try {
            if (!empty($tipe) && !in_array($tipe, ['visi', 'misi'])) {
                return ResponseController::sendErrorData(98, ['field' => 'tipe must be visi or misi']);
            }

            $list = $this->visiMisi->getVisiMisiList($keyword, $tipe);

            $paged = GlobalHelperController::paginate($list, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data visi misi berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Add Visi/Misi
     */
    public function addVisiMisi(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $required = ['tipe', 'judul', 'isi'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        if (!in_array($request->tipe, ['visi', 'misi'])) {
            return ResponseController::sendErrorData(98, ['field' => 'tipe must be visi or misi']);
        }

        try {
            $vm = new VisiMisi([
                'tipe' => $request->tipe,
                'judul' => $request->judul,
                'isi' => $request->isi,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $vm->save();

            return ResponseController::sendSuccess([
                'message' => 'Data visi misi berhasil ditambahkan',
                'data' => [
                    'id' => md5($vm->id . $this->visiMisi->imbuhan),
                    'tipe' => $vm->tipe,
                    'judul' => $vm->judul
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Edit Visi/Misi
     */
    public function editVisiMisi(Request $request)
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
            $vm = $this->visiMisi->getVisiMisiByEncId($id);
            if (!$vm) {
                return ResponseController::sendError(97);
            }

            $update = VisiMisi::find($vm->id);
            if (!$update) {
                return ResponseController::sendError(97);
            }

            if (!empty($request->tipe)) {
                if (!in_array($request->tipe, ['visi', 'misi'])) {
                    return ResponseController::sendErrorData(98, ['field' => 'tipe must be visi or misi']);
                }
                $update->tipe = $request->tipe;
            }
            if (!empty($request->judul)) {
                $update->judul = $request->judul;
            }
            if ($request->has('isi')) {
                $update->isi = $request->isi;
            }

            $update->save();

            return ResponseController::sendSuccess([
                'message' => 'Data visi misi berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Delete Visi/Misi
     */
    public function deleteVisiMisi(Request $request)
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
            $vm = $this->visiMisi->getVisiMisiByEncId($id);
            if (!$vm) {
                return ResponseController::sendError(97);
            }

            $delete = VisiMisi::find($vm->id);
            $delete->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data visi misi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
