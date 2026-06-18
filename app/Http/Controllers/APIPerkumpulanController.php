<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Perkumpulan;
use App\Kota;
use App\Entitas;
use App\StatusAnggota;
use App\PengajuanPemain;
use App\PengajuanNonAnggota;

class APIPerkumpulanController extends Controller
{
    protected $perkumpulan;
    protected $kota;
    protected $entitas;
    protected $statusAnggota;
    protected $pengajuanPemain;
    protected $pengajuanNonAnggota;

    public function __construct()
    {
        $this->perkumpulan = new Perkumpulan();
        $this->kota = new Kota();
        $this->entitas = new Entitas();
        $this->statusAnggota = new StatusAnggota();
        $this->pengajuanPemain = new PengajuanPemain();
        $this->pengajuanNonAnggota = new PengajuanNonAnggota();
    }

    public function createPerkumpulan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['id_entitas', 'no_perkumpulan', 'nama', 'tahun_gabung', 'id_status_anggota'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id_entitas = $request->id_entitas;
        $no_perkumpulan = $request->no_perkumpulan;
        $nama = $request->nama;
        $tahun_gabung = $request->tahun_gabung;
        $id_status_anggota = $request->id_status_anggota;
        $no_sk = $request->no_sk ?? null;
        $keterangan = $request->keterangan ?? null;
        $logo  = $request->logo  ?? null;

        try {
            // Validate entitas exists
            $entitas = $this->entitas->getEntitasByEncId($id_entitas);
            if (!$entitas) {
                return ResponseController::sendError(39); // Entitas tidak ditemukan
            }

            // Validate status anggota exists
            $statusAnggota = $this->statusAnggota->getStatusAnggotaByEncId($id_status_anggota);
            if (!$statusAnggota) {
                return ResponseController::sendError(40); // Status anggota tidak ditemukan
            }

            // Check if no_perkumpulan already exists
            $existing = DB::select(
                'SELECT id FROM m_perkumpulan WHERE no_perkumpulan = ? AND status = 1',
                [$no_perkumpulan]
            );
            if (!empty($existing)) {
                return ResponseController::sendError(41); // No perkumpulan sudah terdaftar
            }

            // Create perkumpulan
            $perkumpulan = new Perkumpulan([
                'id_entitas' => $entitas->id,
                'no_perkumpulan' => $no_perkumpulan,
                'nama' => $nama,
                'tahun_gabung' => $tahun_gabung,
                'id_status_anggota' => $statusAnggota->id,
                'no_sk' => $no_sk,
                'keterangan' => $keterangan,
                'logo' => $logo ?: null,
                'status' => 1,
            ]);
            $perkumpulan->save();

            return ResponseController::sendSuccess([
                'message' => 'Perkumpulan berhasil ditambahkan',
                'data' => [
                    'id' => $perkumpulan->id,
                    'no_perkumpulan' => $no_perkumpulan,
                    'nama' => $nama
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
    
    public function getListPerkumpulan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;
        $id_entitas = $request->id_entitas;
        $id_status_anggota = $request->id_status_anggota;
        $status = $request->status ?? 1;
        $id_kotaenc = $request->id_kota;

        try {
            // Decode id_entitas if provided
            $entitas_id = null;
            if (!empty($id_entitas)) {
                $entitas = $this->entitas->getEntitasByEncId($id_entitas);
                if ($entitas) {
                    $entitas_id = $entitas->id;
                }
            }

            // Decode id_status_anggota if provided
            $status_anggota_id = null;
            if (!empty($id_status_anggota)) {
                $statusAnggota = $this->statusAnggota->getStatusAnggotaByEncId($id_status_anggota);
                if ($statusAnggota) {
                    $status_anggota_id = $statusAnggota->id;
                }
            }

            // Validate kota exists
            $id_kota = null;
            if (!empty($id_kotaenc)) {
                $kota = $this->kota->getKotaByEncId($id_kotaenc);
                if ($kota) {
                    $id_kota = $kota->id;
                }
            }

            // Get perkumpulan list with jumlah anggota
            $perkumpulanList = $this->perkumpulan->getPerkumpulanList($status, $keyword, $entitas_id,$status_anggota_id, $id_kota);

            $paged = GlobalHelperController::paginate($perkumpulanList, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data perkumpulan berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function updatePerkumpulan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $id_entitas = $request->id_entitas;
        $no_perkumpulan = $request->no_perkumpulan;
        $nama = $request->nama;
        $tahun_gabung = $request->tahun_gabung;
        $id_status_anggota = $request->id_status_anggota;
        $no_sk = $request->no_sk;
        $keterangan = $request->keterangan;
        $logo  = $request->logo;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            // Get perkumpulan
            $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id);
            if (!$perkumpulan) {
                return ResponseController::sendError(4); // Perkumpulan tidak ditemukan
            }

            // Find perkumpulan model
            $updatePerkumpulan = Perkumpulan::find($perkumpulan->id);
            if (!$updatePerkumpulan) {
                return ResponseController::sendError(4);
            }

            // Update fields if provided
            if (!empty($id_entitas)) {
                $entitas = $this->entitas->getEntitasByEncId($id_entitas);
                if (!$entitas) {
                    return ResponseController::sendError(39);
                }
                $updatePerkumpulan->id_entitas = $entitas->id;
            }

            if (!empty($no_perkumpulan)) {
                // Check duplicate (exclude current)
                $existing = DB::select(
                    'SELECT id FROM m_perkumpulan WHERE no_perkumpulan = ? AND id != ? AND status = 1',
                    [$no_perkumpulan, $perkumpulan->id]
                );
                if (!empty($existing)) {
                    return ResponseController::sendError(41);
                }
                $updatePerkumpulan->no_perkumpulan = $no_perkumpulan;
            }

            if (!empty($nama)) {
                $updatePerkumpulan->nama = $nama;
            }

            if (!empty($tahun_gabung)) {
                $updatePerkumpulan->tahun_gabung = $tahun_gabung;
            }

            if (!empty($id_status_anggota)) {
                $statusAnggota = $this->statusAnggota->getStatusAnggotaByEncId($id_status_anggota);
                if (!$statusAnggota) {
                    return ResponseController::sendError(40);
                }
                $updatePerkumpulan->id_status_anggota = $statusAnggota->id;
            }

            if ($request->exists('no_sk')) {
                $updatePerkumpulan->no_sk = $no_sk ?: null;
            }

            if ($request->exists('keterangan')) {
                $updatePerkumpulan->keterangan = $keterangan ?: null;
            }

            // logo dikirim sebagai nama file; string kosong berarti hapus logo
            if (isset($logo)) {
                $updatePerkumpulan->logo = $logo ?: null;
            }

            $updatePerkumpulan->save();

            return ResponseController::sendSuccess([
                'message' => 'Data perkumpulan berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function deletePerkumpulan(Request $request)
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
            // Get perkumpulan
            $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id);
            if (!$perkumpulan) {
                return ResponseController::sendError(4);
            }

            // Check if has active members
            $hasMembers = DB::select(
                'SELECT COUNT(*) as total FROM m_pengajuan_pemain WHERE id_perkumpulan = ? AND status = 1',
                [$perkumpulan->id]
            );

            if ($hasMembers[0]->total > 0) {
                return ResponseController::sendError(42); // Perkumpulan masih memiliki anggota aktif
            }

            $hasPengajuanSDM = DB::select(
                'SELECT COUNT(*) as total FROM m_pengajuan_nonaggota WHERE id_perkumpulan = ? AND status >= 0',
                [$perkumpulan->id]
            );

            if ($hasPengajuanSDM[0]->total > 0) {
                return ResponseController::sendError(71);
            }

            $hasAdmin = DB::select(
                'SELECT COUNT(*) as total FROM m_admin WHERE id_perkumpulan = ? AND status = 1',
                [$perkumpulan->id]
            );

            if ($hasAdmin[0]->total > 0) {
                return ResponseController::sendError(71);
            }

            // Soft delete
            $deletePerkumpulan = Perkumpulan::find($perkumpulan->id);
            $deletePerkumpulan->status = -1;
            $deletePerkumpulan->save();

            return ResponseController::sendSuccess([
                'message' => 'Data perkumpulan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function uploadLogo(Request $request)
    {
        // $validation = GlobalHelperController::validateJWT($request);
        // if (!$validation['valid']) {
        //     return GlobalHelperController::sendUnauthorized($validation['message']);
        // }

        $filename = $request->filename ?? '';
        $filename = basename(trim($filename));
        if (empty($filename)) {
            return ResponseController::sendErrorData(98, ['field' => 'filename is required']);
        }

        $file = null;
        foreach (['uploaded_file', 'file', 'image'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                break;
            }
        }

        if (!$file) {
            return ResponseController::sendErrorData(98, ['field' => 'uploaded_file is required']);
        }

        $targetDir = public_path('perkumpulan');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        try {
            $file->move($targetDir, $filename);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }

        return ResponseController::sendSuccess([
            'message' => 'Logo berhasil diupload',
            'filename' => $filename,
        ]);
    }

    public function getDetailPerkumpulan(Request $request)
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
            // Get perkumpulan detail
            $detail = $this->perkumpulan->getPerkumpulanDetails($id);

            if (!$detail) {
                return ResponseController::sendError(4);
            }

            // Get anggota (pemain) - approved members
            $anggota = $this->pengajuanPemain->getApprovedPemain($detail->id);

            // Get non anggota (SDM) - approved non members
            $nonAnggota = $this->pengajuanNonAnggota->getApprovedNonAnggota($detail->id);

            $detail->jumlah_anggota = count($anggota);
            $detail->jumlah_non_anggota = count($nonAnggota);
            $detail->anggota = $anggota;
            $detail->non_anggota = $nonAnggota;
            $detail->id = $id; // Return encrypted id

            return ResponseController::sendSuccess([
                'message' => 'Detail perkumpulan berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
