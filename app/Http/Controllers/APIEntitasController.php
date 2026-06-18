<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Entitas;
use App\Kota;

class APIEntitasController extends Controller
{
    protected $entitas;
    protected $kota;

    public function __construct()
    {
        $this->entitas = new Entitas();
        $this->kota = new Kota();
    }

    /**
     * 1. Tambah Entitas
     */
    public function createEntitas(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['no_pengkot', 'id_kota', 'no_sk'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $no_pengkot = $request->no_pengkot;
        $id_kota = $request->id_kota;
        $no_sk = $request->no_sk;

        try {
            // Validate kota exists
            $kota = $this->kota->getKotaByEncId($id_kota);
            if (!$kota) {
                return ResponseController::sendError(36); // Kota tidak ditemukan
            }

            // Check if no_pengkot already exists
            $existing = DB::select(
                'SELECT id FROM m_entitas WHERE no_pengkot = ? AND status = 1',
                [$no_pengkot]
            );
            if (!empty($existing)) {
                return ResponseController::sendError(37); // No Pengkot sudah terdaftar
            }

            // Create entitas
            $entitas = new Entitas([
                'id_kota' => $kota->id,
                'no_pengkot' => $no_pengkot,
                'no_sk' => $no_sk,
                'status' => 1,
            ]);
            $entitas->save();

            return ResponseController::sendSuccess([
                'message' => 'Entitas berhasil ditambahkan',
                'data' => [
                    'id' => $entitas->id,
                    'no_pengkot' => $no_pengkot,
                    'no_sk' => $no_sk
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. List Entitas
     */
    public function getListEntitas(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;
        $id_kota = $request->id_kota;
        $status = $request->status ?? 1;

        try {
            // Decode id_kota if provided
            $kota_id = null;
            if (!empty($id_kota)) {
                $kota = $this->kota->getKotaByEncId($id_kota);
                if ($kota) {
                    $kota_id = $kota->id;
                }
            }

            $entitasList = $this->entitas->getEntitas($keyword, $kota_id, $status);

            $paged = GlobalHelperController::paginate($entitasList, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data entitas berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Edit Entitas
     */
    public function updateEntitas(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $no_pengkot = $request->no_pengkot;
        $id_kota = $request->id_kota;
        $no_sk = $request->no_sk;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get entitas
            $entitas = $this->entitas->getEntitasByEncId($id);
            if (!$entitas) {
                return ResponseController::sendError(97);
            }

            // Find entitas model
            $updateEntitas = Entitas::find($entitas->id);
            if (!$updateEntitas) {
                return ResponseController::sendError(97);
            }

            // Update fields if provided
            if (!empty($no_pengkot)) {
                // Check if no_pengkot already exists (exclude current entitas)
                $existing = DB::select(
                    'SELECT id FROM m_entitas WHERE no_pengkot = ? AND id != ? AND status = 1',
                    [$no_pengkot, $entitas->id]
                );
                if (!empty($existing)) {
                    return ResponseController::sendError(37); // No Pengkot sudah terdaftar
                }
                $updateEntitas->no_pengkot = $no_pengkot;
            }

            if (!empty($id_kota)) {
                // Validate kota exists
                $kota = $this->kota->getKotaByEncId($id_kota);
                if (!$kota) {
                    return ResponseController::sendError(36); // Kota tidak ditemukan
                }
                $updateEntitas->id_kota = $kota->id;
            }

            if (!empty($no_sk)) {
                $updateEntitas->no_sk = $no_sk;
            }

            $updateEntitas->save();

            return ResponseController::sendSuccess([
                'message' => 'Data entitas berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Hapus Entitas (Soft Delete)
     */
    public function deleteEntitas(Request $request)
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
            // Get entitas
            $entitas = $this->entitas->getEntitasByEncId($id);
            if (!$entitas) {
                return ResponseController::sendError(97);
            }

            // Check if entitas has active perkumpulan
            $hasPerkumpulan = DB::select(
                'SELECT COUNT(*) as total FROM m_perkumpulan WHERE id_entitas = ? AND status = 1',
                [$entitas->id]
            );

            if ($hasPerkumpulan[0]->total > 0) {
                return ResponseController::sendError(38); // Entitas masih memiliki perkumpulan aktif
            }

            // Soft delete
            $deleteEntitas = Entitas::find($entitas->id);
            $deleteEntitas->status = -1;
            $deleteEntitas->save();

            return ResponseController::sendSuccess([
                'message' => 'Data entitas berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Get Detail Entitas
     */
    public function getDetailEntitas(Request $request)
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
            $entitas = DB::select(
                'SELECT 
                    e.id,
                    md5(concat(e.id, "'.$this->entitas->imbuhan.'")) AS encrypted_id,
                    e.id_kota,
                    md5(concat(k.id, "K0T4")) AS encrypted_id_kota,
                    e.no_pengkot,
                    e.no_sk,
                    e.status,
                    k.name as nama_kota,
                    k.type as tipe_kota,
                    k.provinsi_id,
                    p.name as nama_provinsi,
                    p.code as kode_provinsi
                FROM m_entitas e
                LEFT JOIN m_kota k ON e.id_kota = k.id
                LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
                WHERE md5(concat(e.id, "'.$this->entitas->imbuhan.'")) = ?
                ', [$id]
            );

            if (empty($entitas)) {
                return ResponseController::sendError(97);
            }

            // Get total perkumpulan aktif
            $perkumpulanCount = DB::select(
                'SELECT COUNT(*) as total FROM m_perkumpulan WHERE id_entitas = ? AND status = 1',
                [$entitas[0]->id]
            );

            $result = $entitas[0];
            $result->total_perkumpulan = $perkumpulanCount[0]->total;

            return ResponseController::sendSuccess([
                'message' => 'Detail entitas berhasil diambil',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
