<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\MasterPersyaratanEvent;

class APIMasterPersyaratanEventController extends Controller
{
    protected $master;

    const TIPE_INPUT_OPTIONS = ['text', 'number', 'textarea', 'date', 'dropdown', 'upload'];
    const SUMBER_OPTIONS = ['profil', 'registrasi', 'custom'];

    public function __construct()
    {
        $this->master = new MasterPersyaratanEvent();
    }

    public function getList(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            $rows = $this->master->getList($request->status ?: 'aktif');

            return ResponseController::sendSuccess([
                'message' => 'List persyaratan berhasil diambil',
                'data' => $rows
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function create(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $key = $request->key;
        $label = $request->label;
        $tipe_input = $request->tipe_input ?: 'text';
        $sumber = $request->sumber ?: 'custom';

        if (empty($key) || empty($label)) {
            return ResponseController::sendError(98);
        }
        if (!in_array($tipe_input, self::TIPE_INPUT_OPTIONS) || !in_array($sumber, self::SUMBER_OPTIONS)) {
            return ResponseController::sendError(98);
        }

        // Keys double as the JSON field name stored in data_persyaratan, so keep them slug-safe.
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($key)));

        try {
            if ($this->master->keyExists($key)) {
                return ResponseController::sendError(90);
            }

            $id = DB::table('m_master_persyaratan_event')->insertGetId([
                'key' => $key,
                'label' => $label,
                'tipe_input' => $tipe_input,
                'sumber' => $sumber,
                'profil_field_map' => $request->profil_field_map ?: null,
                'is_default' => false,
                'urutan' => (int) DB::table('m_master_persyaratan_event')->max('urutan') + 1,
                'status' => 'aktif',
            ]);

            return ResponseController::sendSuccess([
                'message' => 'Persyaratan berhasil ditambahkan',
                'data' => ['id' => md5($id . $this->master->imbuhan), 'key' => $key]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function update(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->master->getByEncId($id);
            if (!$row) return ResponseController::sendError(89);

            $update = [
                'label' => $request->label ?: $row->label,
                'urutan' => $request->has('urutan') ? (int) $request->urutan : $row->urutan,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($request->has('tipe_input') && !empty($request->tipe_input)) {
                if (!in_array($request->tipe_input, self::TIPE_INPUT_OPTIONS)) return ResponseController::sendError(98);
                $update['tipe_input'] = $request->tipe_input;
            }
            if ($request->has('status') && !empty($request->status)) {
                $update['status'] = $request->status;
            }

            DB::table('m_master_persyaratan_event')->where('id', $row->id)->update($update);

            return ResponseController::sendSuccess(['message' => 'Persyaratan berhasil diperbarui']);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function delete(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->master->getByEncId($id);
            if (!$row) return ResponseController::sendError(89);

            // Soft delete: events created earlier may still reference this key in
            // their required_fields, so the row must keep existing rather than be dropped.
            DB::table('m_master_persyaratan_event')->where('id', $row->id)->update([
                'status' => 'nonaktif',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ResponseController::sendSuccess(['message' => 'Persyaratan berhasil dinonaktifkan']);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
