<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Posisi;
use App\PengajuanNonAnggota;

class APIPosisiController extends Controller
{
    protected $posisi;
    protected $pengajuanNonAnggota;

    public function __construct()
    {
        $this->posisi = new Posisi();
        $this->pengajuanNonAnggota = new PengajuanNonAnggota();
    }

    /**
     * 1. Create Posisi
     */
    public function createPosisi(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        if (empty($request->nama)) {
            return ResponseController::sendErrorData(98, ['field' => 'nama is required']);
        }

        $validKategori = ['sdm', 'pemain'];
        $kategori = $request->kategori;
        if (empty($kategori) || !in_array($kategori, $validKategori)) {
            return ResponseController::sendErrorData(98, ['field' => 'kategori must be sdm or pemain']);
        }

        $nama = $request->nama;

        try {
            // Check if nama already exists
            $existing = DB::select(
                'SELECT id FROM m_posisi WHERE nama = ?',
                [$nama]
            );
            if (!empty($existing)) {
                return ResponseController::sendError(43); // Nama posisi sudah terdaftar
            }

            // Create posisi
            $posisi = new Posisi([
                'nama'     => $nama,
                'kategori' => $kategori
            ]);
            $posisi->save();

            return ResponseController::sendSuccess([
                'message' => 'Posisi berhasil ditambahkan',
                'data' => [
                    'id'       => $posisi->id,
                    'nama'     => $nama,
                    'kategori' => $kategori
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Get List Posisi
     */
    public function getListPosisi(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            $kategori = $request->kategori ?? "";
            $posisiList = $this->posisi->getPosisis($kategori);

            $keyword = $request->keyword;
            if (!empty($keyword)) {
                $kw = mb_strtolower($keyword);
                $posisiList = array_values(array_filter($posisiList, function ($row) use ($kw) {
                    $nama = isset($row->nama) ? mb_strtolower($row->nama) : '';
                    return strpos($nama, $kw) !== false;
                }));
            }

            $paged = GlobalHelperController::paginate($posisiList, $request);

            return ResponseController::sendSuccess([
                'kategori' => $kategori,
                'message' => 'Data posisi berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Get Detail Posisi with Users
     */
    public function getDetailPosisi(Request $request)
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
            // Get posisi detail
            $posisi = $this->posisi->getPosisiByEncId($id);
            if (!$posisi) {
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }

            // Get users with this posisi
            $users = $this->pengajuanNonAnggota->getApprovedNonAnggotaByIdPosisi($posisi->id);

            $detail = [
                'id'          => md5($posisi->id . $this->posisi->imbuhan),
                'nama'        => $posisi->nama,
                'kategori'    => $posisi->kategori,
                'jumlah_user' => count($users),
                'users'       => $users
            ];

            return ResponseController::sendSuccess([
                'message' => 'Detail posisi berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Update Posisi
     */
    public function updatePosisi(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $nama = $request->nama;
        $kategori = $request->kategori;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        if (empty($nama)) {
            return ResponseController::sendErrorData(98, ['field' => 'nama is required']);
        }

        $validKategori = ['sdm', 'pemain'];
        if (!empty($kategori) && !in_array($kategori, $validKategori)) {
            return ResponseController::sendErrorData(98, ['field' => 'kategori must be sdm or pemain']);
        }

        try {
            // Get posisi
            $posisi = $this->posisi->getPosisiByEncId($id);
            if (!$posisi) {
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }

            // Check if nama already exists (exclude current)
            $existing = DB::select(
                'SELECT id FROM m_posisi WHERE nama = ? AND id != ?',
                [$nama, $posisi->id]
            );
            if (!empty($existing)) {
                return ResponseController::sendError(43); // Nama posisi sudah terdaftar
            }

            // Update posisi
            $updatePosisi = Posisi::find($posisi->id);
            $updatePosisi->nama = $nama;
            if (!empty($kategori)) {
                $updatePosisi->kategori = $kategori;
            }
            $updatePosisi->save();

            return ResponseController::sendSuccess([
                'message' => 'Data posisi berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Delete Posisi
     */
    public function deletePosisi(Request $request)
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
            // Get posisi
            $posisi = $this->posisi->getPosisiByEncId($id);
            if (!$posisi) {
                return ResponseController::sendErrorData(44, ["data" => $id]); // Posisi tidak ditemukan
            }

            // Check if has active users
            $hasUsers = DB::select(
                'SELECT COUNT(*) as total FROM m_user WHERE posisi = ? AND status = 1',
                [$posisi->id]
            );

            if ($hasUsers[0]->total > 0) {
                return ResponseController::sendError(45); // Posisi masih memiliki user aktif
            }

            $hasPengajuanSDM = DB::select(
                'SELECT COUNT(*) as total FROM m_pengajuan_nonaggota WHERE id_posisi = ? AND status >= 0',
                [$posisi->id]
            );

            if ($hasPengajuanSDM[0]->total > 0) {
                return ResponseController::sendError(71);
            }

            // Delete posisi (hard delete karena tidak ada field status)
            $deletePosisi = Posisi::find($posisi->id);
            $deletePosisi->delete();

            return ResponseController::sendSuccess([
                'message' => 'Data posisi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
