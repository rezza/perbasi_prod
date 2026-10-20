<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\PengajuanPemain;
use App\PengajuanNonAnggota;
use App\Prestasi;
use App\PrestasiSDM;
use App\Training;
use App\Lisensi;
use App\Event;
use App\Perkumpulan;
use App\Kota;
use App\Posisi;
use App\Pendidikan;
use App\Admin;
use App\User;

class APIPengajuanController extends Controller
{
    protected $pengajuanPemain;
    protected $pengajuanNonAnggota;
    protected $prestasi;
    protected $prestasiSDM;
    protected $training;
    protected $lisensi;
    protected $event;
    protected $perkumpulan;
    protected $kota;
    protected $posisi;
    protected $admin;
    protected $user;
    protected $pendidikan;

    public function __construct()
    {
        $this->pengajuanPemain = new PengajuanPemain();
        $this->pengajuanNonAnggota = new PengajuanNonAnggota();
        $this->prestasi = new Prestasi();
        $this->prestasiSDM = new PrestasiSDM();
        $this->training = new Training();
        $this->lisensi = new Lisensi();
        $this->event = new Event();
        $this->perkumpulan = new Perkumpulan();
        $this->kota = new Kota();
        $this->posisi = new Posisi();
        $this->admin = new Admin();
        $this->user = new User();
        $this->pendidikan = new Pendidikan();
    }

    /**
     * 1. Pengajuan Keanggotaan Pemain
     */
    public function pengajuanPemain(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        // Validate required fields
        $required = ['tahun', 'id_kota', 'posisi'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $tahun = $request->tahun;
        $id_kota = $request->id_kota;
        $id_perkumpulan = $request->id_perkumpulan;
        $id_posisi = $request->id_posisi;
        $posisi = $request->posisi;
        $no_punggung = $request->no_punggung;
        $tinggi = $request->tinggi;
        $berat = $request->berat;
        $penyakit = $request->penyakit ?? null;
        $operasi = $request->operasi ?? null;
        $list_prestasi = $request->list_prestasi; // array of [{tahun, kompetisi, prestasi}]

        try {
            DB::beginTransaction();

            // Validate kota exists
            $kota = $this->kota->getKotaByEncId($id_kota);
            if (!$kota) {
                return ResponseController::sendError(36); // Kota tidak ditemukan
            }

            // Validate perkumpulan exist
            $selectedIdPerkumpulan = "";
            if($id_perkumpulan != ""){
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulan);
                if ($perkumpulan) {
                    $selectedIdPerkumpulan = $perkumpulan->id;
                }
            }

            $data_posisi = $this->posisi->getPosisiByEncId($id_posisi);
            if (!$data_posisi) {
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }


            // Check if user already has pending pengajuan
            $existingPengajuan = DB::select(
                'SELECT id FROM m_pengajuan_pemain 
                WHERE id_user = ? AND id_kota = ? AND id_perkumpulan = ? and posisi = ? AND status IN (0, 1, 2) AND tahun = ?',
                [$currentUser["user_id"], $kota->id, $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan, $posisi, $tahun]
            );
            if (!empty($existingPengajuan)) {
                DB::rollback();
                return ResponseController::sendErrorData(51, [
                    "existing" => $existingPengajuan
                ]); // Sudah ada pengajuan aktif
            }

            // Create pengajuan pemain
            $pengajuan = new PengajuanPemain([
                'id_user' => $currentUser["user_id"],
                'tahun' => $tahun,
                'id_kota' => $kota->id,
                'id_perkumpulan' => $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan,
                "id_posisi" => $data_posisi->id,
                'posisi' => $data_posisi->nama,
                'no_punggung' => $no_punggung,
                'tinggi' => $tinggi,
                'berat' => $berat,
                'penyakit' => $penyakit,
                'operasi' => $operasi,
                'tgl_pengajuan' => date('Y-m-d H:i:s'),
                'status' => $selectedIdPerkumpulan == "" ? 1 : 0,
            ]);
            $pengajuan->save();

            // Insert prestasi if provided
            if (!empty($list_prestasi) && is_array($list_prestasi)) {
                foreach ($list_prestasi as $prest) {
                    if (!empty($prest['tahun']) && !empty($prest['kompetisi']) && !empty($prest['prestasi'])) {
                        $prestasi = new Prestasi([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $prest['tahun'],
                            'kompetisi' => $prest['kompetisi'],
                            'prestasi' => $prest['prestasi']
                        ]);
                        $prestasi->save();
                    }
                }
            }

            DB::commit();


            // Notify admin master
            $user = User::find($currentUser["user_id"]);
            $tokenAdminMaster = $this->user->getAdminMasterFCMTokens();
            if(!empty($tokenAdminMaster)){
                GlobalHelperController::sendNotif($tokenAdminMaster, "Pengajuan Anggota", $user->nama." mengajukan pendaftaran baru sebagai ".$posisi, $pengajuan->id);
            }
            
            // Notify admin perkumpulan
            if($selectedIdPerkumpulan != ""){
                $tokenAdminPerkumpulan = $this->user->getAdminPerkumpulanFCMTokens($selectedIdPerkumpulan);
                if(!empty($tokenAdminPerkumpulan)){
                    GlobalHelperController::sendNotif($tokenAdminPerkumpulan, "Pengajuan Anggota", $user->nama." mengajukan pendaftaran baru sebagai ".$posisi, $pengajuan->id);
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan pemain berhasil diajukan',
                // "kota" => $kota,
                // "current_user" => $currentUser["user_id"],
                'data' => [
                    // 'id' => $pengajuan->id,
                    'tahun' => $tahun,
                    // 'perkumpulan' => $perkumpulan ? $perkumpulan->nama : ""
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(90, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 1.1 Edit Pengajuan Keanggotaan Pemain
     */
    public function editPengajuanPemain(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        // Validate required fields
        $required = ['id', 'tahun', 'id_kota', 'posisi'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id = $request->id;
        $tahun = $request->tahun;
        $id_kota = $request->id_kota;
        $id_perkumpulan = $request->id_perkumpulan;
        $id_posisi = $request->id_posisi;
        $posisi = $request->posisi;
        $no_punggung = $request->no_punggung;
        $tinggi = $request->tinggi;
        $berat = $request->berat;
        $penyakit = $request->penyakit ?? null;
        $operasi = $request->operasi ?? null;
        $list_prestasi = $request->list_prestasi; // array of [{tahun, kompetisi, prestasi}]

        try {
            DB::beginTransaction();

            // Get existing pengajuan
            $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
            if (!$pengajuan) {
                DB::rollback();
                return ResponseController::sendError(52); // Pengajuan tidak ditemukan
            }

            // Check if user owns this pengajuan
            if ($pengajuan->id_user != $currentUser["user_id"]) {
                DB::rollback();
                return ResponseController::sendError(99); // Tidak memiliki akses
            }

            // Only allow edit if status is pending (0) or rejected (-1, -2)
            if (!in_array($pengajuan->status, [0, -1, -2])) {
                DB::rollback();
                return ResponseController::sendError(53); // Pengajuan sudah diproses, tidak bisa diedit
            }

            // Validate kota exists
            $kota = $this->kota->getKotaByEncId($id_kota);
            if (!$kota) {
                DB::rollback();
                return ResponseController::sendError(36); // Kota tidak ditemukan
            }

            $data_posisi = $this->posisi->getPosisiByEncId($id_posisi);
            if (!$data_posisi) {
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }

            // Validate perkumpulan exist
            $selectedIdPerkumpulan = "";
            if($id_perkumpulan != ""){
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulan);
                if ($perkumpulan) {
                    $selectedIdPerkumpulan = $perkumpulan->id;
                }
            }

            // Update pengajuan pemain
            DB::update(
                'UPDATE m_pengajuan_pemain 
                SET tahun = ?,
                    id_kota = ?,
                    id_perkumpulan = ?,
                    id_posisi = ?,
                    posisi = ?,
                    no_punggung = ?,
                    tinggi = ?,
                    berat = ?,
                    penyakit = ?,
                    operasi = ?,
                    tgl_pengajuan = ?,
                    status = ?,
                    reason = NULL
                WHERE id = ?',
                [
                    $tahun,
                    $kota->id,
                    $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan,
                    $data_posisi->id,
                    $data_posisi->nama,
                    $no_punggung,
                    $tinggi,
                    $berat,
                    $penyakit,
                    $operasi,
                    date('Y-m-d H:i:s'),
                    $selectedIdPerkumpulan == "" ? 1 : 0, // Reset status to pending
                    $pengajuan->id
                ]
            );

            // Delete existing prestasi
            DB::delete('DELETE FROM m_prestasi WHERE id_pengajuan = ?', [$pengajuan->id]);

            // Insert updated prestasi if provided
            if (!empty($list_prestasi) && is_array($list_prestasi)) {
                foreach ($list_prestasi as $prest) {
                    if (!empty($prest['tahun']) && !empty($prest['kompetisi']) && !empty($prest['prestasi'])) {
                        $prestasi = new Prestasi([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $prest['tahun'],
                            'kompetisi' => $prest['kompetisi'],
                            'prestasi' => $prest['prestasi']
                        ]);
                        $prestasi->save();
                    }
                }
            }

            DB::commit();

            // Notify admin master
            $user = User::find($currentUser["user_id"]);
            $tokenAdminMaster = $this->user->getAdminMasterFCMTokens();
            if(!empty($tokenAdminMaster)){
                GlobalHelperController::sendNotif($tokenAdminMaster, "Pengajuan Anggota Diperbarui", $user->nama." memperbarui pengajuan pendaftaran sebagai ".$posisi, $pengajuan->id);
            }
            
            // Notify admin perkumpulan
            if($selectedIdPerkumpulan != ""){
                $tokenAdminPerkumpulan = $this->user->getAdminPerkumpulanFCMTokens($selectedIdPerkumpulan);
                if(!empty($tokenAdminPerkumpulan)){
                    GlobalHelperController::sendNotif($tokenAdminPerkumpulan, "Pengajuan Anggota Diperbarui", $user->nama." memperbarui pengajuan pendaftaran sebagai ".$posisi, $pengajuan->id);
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan pemain berhasil diperbarui',
                'data' => [
                    'tahun' => $tahun,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(90, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 2. Edit Pengajuan SDM (Non Anggota)
     */
    public function editPengajuanSDM(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        // Validate required fields
        $required = ['id', 'id_posisi', 'jabatan', 'tahun'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id = $request->id;
        $id_posisi = $request->id_posisi;
        $jabatan = $request->jabatan;
        $tahun = $request->tahun;
        $id_perkumpulan = $request->id_perkumpulan;
        $list_prestasi = $request->list_prestasi; // array of [{tahun, prestasi}]
        $list_training = $request->list_training; // array of [{tahun, nama, tempat}]
        $list_lisensi = $request->list_lisensi; // array of [{tahun, nama, tanggal_mulai, tanggal_expired, filename}]
        $list_event = $request->list_event; // array of [{tahun, nama, prestasi}]

        try {
            DB::beginTransaction();

            // Get existing pengajuan
            $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
            if (!$pengajuan) {
                DB::rollback();
                return ResponseController::sendError(52); // Pengajuan tidak ditemukan
            }

            // Check if user owns this pengajuan
            if ($pengajuan->id_user != $currentUser["user_id"]) {
                DB::rollback();
                return ResponseController::sendError(99); // Tidak memiliki akses
            }

            // Only allow edit if status is pending (0) or rejected (-1, -2)
            if (!in_array($pengajuan->status, [0, -1, -2])) {
                DB::rollback();
                return ResponseController::sendError(53); // Pengajuan sudah diproses, tidak bisa diedit
            }

            // Validate posisi exists
            $posisi = $this->posisi->getPosisiByEncId($id_posisi);
            if (!$posisi) {
                DB::rollback();
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }

            // Validate perkumpulan exists
            $selectedIdPerkumpulan = "";
            if($id_perkumpulan != ""){
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulan);
                if ($perkumpulan) {
                    $selectedIdPerkumpulan = $perkumpulan->id;
                }
            }

            // Update pengajuan non anggota
            DB::update(
                'UPDATE m_pengajuan_nonaggota 
                SET id_perkumpulan = ?,
                    tahun = ?,
                    id_posisi = ?,
                    jabatan = ?,
                    tgl_pengajuan = ?,
                    status = ?,
                    reason = NULL
                WHERE id = ?',
                [
                    $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan,
                    $tahun,
                    $posisi->id,
                    $jabatan,
                    date('Y-m-d H:i:s'),
                    $selectedIdPerkumpulan == "" ? 1 : 0, // Reset status to pending
                    $pengajuan->id
                ]
            );

            // Delete existing prestasi SDM
            DB::delete('DELETE FROM m_prestasi_sdm WHERE id_pengajuan = ?', [$pengajuan->id]);

            // Insert updated prestasi SDM if provided
            if (!empty($list_prestasi) && is_array($list_prestasi)) {
                foreach ($list_prestasi as $prest) {
                    if (!empty($prest['tahun']) && !empty($prest['prestasi'])) {
                        $prestasiSDM = new PrestasiSDM([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $prest['tahun'],
                            'prestasi' => $prest['prestasi']
                        ]);
                        $prestasiSDM->save();
                    }
                }
            }

            // Delete existing training
            DB::delete('DELETE FROM m_training WHERE id_pengajuan = ?', [$pengajuan->id]);

            // Insert updated training if provided
            if (!empty($list_training) && is_array($list_training)) {
                foreach ($list_training as $train) {
                    if (!empty($train['tahun']) && !empty($train['nama']) && !empty($train['tempat'])) {
                        $training = new Training([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $train['tahun'],
                            'nama' => $train['nama'],
                            'lokasi' => $train['tempat'],
                            'filename' => $train['filename'] ?? null
                        ]);
                        $training->save();
                    }
                }
            }

            // Delete existing lisensi
            DB::delete('DELETE FROM m_lisensi WHERE id_pengajuan = ?', [$pengajuan->id]);

            // Insert updated lisensi if provided
            if (!empty($list_lisensi) && is_array($list_lisensi)) {
                foreach ($list_lisensi as $lis) {
                    if (!empty($lis['tahun']) && !empty($lis['nama'])) {
                        $lisensi = new Lisensi([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $lis['tahun'],
                            'nama' => $lis['nama'],
                            'tanggal_mulai' => $lis['tanggal_mulai'] ?? null,
                            'tanggal_expired' => $lis['tanggal_expired'] ?? null,
                            'filename' => $lis['filename'] ?? null
                        ]);
                        $lisensi->save();
                    }
                }
            }

            // Delete existing event
            DB::delete('DELETE FROM m_event WHERE id_pengajuan = ?', [$pengajuan->id]);

            // Insert updated event if provided
            if (!empty($list_event) && is_array($list_event)) {
                foreach ($list_event as $evt) {
                    if (!empty($evt['tahun']) && !empty($evt['nama'])) {
                        $event = new Event([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $evt['tahun'],
                            'nama' => $evt['nama'],
                            'prestasi' => $evt['prestasi'] ?? null
                        ]);
                        $event->save();
                    }
                }
            }

            DB::commit();

            // Notify admin master
            $user = User::find($currentUser["user_id"]);
            $tokenAdminMaster = $this->user->getAdminMasterFCMTokens();
            if(!empty($tokenAdminMaster)){
                GlobalHelperController::sendNotif($tokenAdminMaster, "Pengajuan SDM Diperbarui", $user->nama." memperbarui pengajuan pendaftaran sebagai ".$posisi->nama, $pengajuan->id);
            }
            
            // Notify admin perkumpulan
            if($selectedIdPerkumpulan != ""){
                $tokenAdminPerkumpulan = $this->user->getAdminPerkumpulanFCMTokens($selectedIdPerkumpulan);
                if(!empty($tokenAdminPerkumpulan)){
                    GlobalHelperController::sendNotif($tokenAdminPerkumpulan, "Pengajuan SDM Diperbarui", $user->nama." memperbarui pengajuan pendaftaran sebagai ".$posisi->nama, $pengajuan->id);
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan SDM berhasil diperbarui',
                'data' => [
                    'tahun' => $tahun,
                    'jabatan' => $jabatan
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Pengajuan SDM (Non Anggota)
     */
    public function pengajuanSDM(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        // Validate required fields
        $required = ['id_posisi', 'jabatan', 'tahun'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id_posisi = $request->id_posisi;
        $jabatan = $request->jabatan;
        $tahun = $request->tahun;
        $id_perkumpulan = $request->id_perkumpulan;
        $list_prestasi = $request->list_prestasi; // array of [{tahun, prestasi}]
        $list_training = $request->list_training; // array of [{tahun, nama, tempat}]
        $list_lisensi = $request->list_lisensi; // array of [{tahun, nama, tanggal_mulai, tanggal_expired, filename}]
        $list_event = $request->list_event; // array of [{tahun, nama, prestasi}]

        try {
            DB::beginTransaction();

            // Validate posisi exists
            $posisi = $this->posisi->getPosisiByEncId($id_posisi);
            if (!$posisi) {
                return ResponseController::sendError(44); // Posisi tidak ditemukan
            }

            // Validate perkumpulan exists
            $selectedIdPerkumpulan = "";
            if($id_perkumpulan != ""){
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulan);
                if ($perkumpulan) {
                    $selectedIdPerkumpulan = $perkumpulan->id;
                }
            }

            // Check if user already has pending pengajuan
            $existingPengajuan = DB::select(
                'SELECT id FROM m_pengajuan_nonaggota 
                WHERE id_user = ? AND id_posisi = ? AND id_perkumpulan = ? AND status IN (0, 1) AND tahun = ?',
                [$currentUser["user_id"], $posisi->id, $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan, $tahun]
            );
            if (!empty($existingPengajuan)) {
                DB::rollback();
                return ResponseController::sendError(51); // Sudah ada pengajuan aktif
            }

            // Create pengajuan non anggota
            $pengajuan = new PengajuanNonAnggota([
                'id_user' => $currentUser["user_id"],
                'id_perkumpulan' =>  $selectedIdPerkumpulan == "" ? null : $selectedIdPerkumpulan,
                'tahun' => $tahun,
                'id_posisi' => $posisi->id,
                'jabatan' => $jabatan,
                'tgl_pengajuan' => date('Y-m-d H:i:s'),
                'status' =>  $selectedIdPerkumpulan == "" ? 1 : 0,
            ]);
            $pengajuan->save();

            // Insert prestasi SDM if provided
            if (!empty($list_prestasi) && is_array($list_prestasi)) {
                foreach ($list_prestasi as $prest) {
                    if (!empty($prest['tahun']) && !empty($prest['prestasi'])) {
                        $prestasiSDM = new PrestasiSDM([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $prest['tahun'],
                            'prestasi' => $prest['prestasi']
                        ]);
                        $prestasiSDM->save();
                    }
                }
            }

            // Insert training if provided
            if (!empty($list_training) && is_array($list_training)) {
                foreach ($list_training as $train) {
                    if (!empty($train['tahun']) && !empty($train['nama']) && !empty($train['tempat'])) {
                        $training = new Training([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $train['tahun'],
                            'nama' => $train['nama'],
                            'lokasi' => $train['tempat'],
                            'filename' => $train['filename'] ?? null
                        ]);
                        $training->save();
                    }
                }
            }

            // Insert lisensi if provided
            if (!empty($list_lisensi) && is_array($list_lisensi)) {
                foreach ($list_lisensi as $lis) {
                    if (!empty($lis['tahun']) && !empty($lis['nama'])) {
                        $lisensi = new Lisensi([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $lis['tahun'],
                            'nama' => $lis['nama'],
                            'tanggal_mulai' => $lis['tanggal_mulai'] ?? null,
                            'tanggal_expired' => $lis['tanggal_expired'] ?? null,
                            'filename' => $lis['filename'] ?? null
                        ]);
                        $lisensi->save();
                    }
                }
            }

            // Insert event if provided
            if (!empty($list_event) && is_array($list_event)) {
                foreach ($list_event as $evt) {
                    if (!empty($evt['tahun']) && !empty($evt['nama'])) {
                        $event = new Event([
                            'id_pengajuan' => $pengajuan->id,
                            'tahun' => $evt['tahun'],
                            'nama' => $evt['nama'],
                            'prestasi' => $evt['prestasi'] ?? null
                        ]);
                        $event->save();
                    }
                }
            }

            DB::commit();

            // Notify admin master
            $user = User::find($currentUser["user_id"]);
            $tokenAdminMaster = $this->user->getAdminMasterFCMTokens();
            if(!empty($tokenAdminMaster)){
                GlobalHelperController::sendNotif($tokenAdminMaster, "Pengajuan SDM", $user->nama." mengajukan pendaftaran baru sebagai ".$posisi->nama, $pengajuan->id);
            }
            
            // Notify admin perkumpulan
            if($selectedIdPerkumpulan != ""){
                $tokenAdminPerkumpulan = $this->user->getAdminPerkumpulanFCMTokens($selectedIdPerkumpulan);
                if(!empty($tokenAdminPerkumpulan)){
                    GlobalHelperController::sendNotif($tokenAdminPerkumpulan, "Pengajuan SDM", $user->nama." mengajukan pendaftaran baru sebagai ".$posisi->nama, $pengajuan->id);
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan SDM berhasil diajukan',
                'data' => [
                    // 'id' => $pengajuan->id,
                    'tahun' => $tahun,
                    'jabatan' => $jabatan
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Detail Pengajuan Pemain (Anggota)
     */
    public function detailPengajuanPemain(Request $request)
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
            // Get pengajuan detail
            $pengajuan = DB::select(
                'SELECT 
                    pp.id,
                    md5(concat(pp.id, "'.$this->pengajuanPemain->imbuhan.'")) AS encrypted_id,
                    pp.id_user,
                    md5(concat(u.id, "U53R")) AS encrypted_id_user,
                    u.nama as nama_user,
                    u.email as email_user,
                    u.no_hp as no_hp_user,
                    u.no_darurat,
                    u.hubungan_nodarurat,
                    u.pekerjaan,
                    u.nik,
                    u.no_pelajar,
                    u.no_kk,
                    u.alamat,
                    u.tempat_lahir,
                    u.tanggal_lahir,
                    u.jenis_kelamin,  
                    u.filename_foto,
                    pp.tahun,
                    md5(concat( pp.id_kota, "'.$this->kota->imbuhan.'")) AS id_kota,
                    k.name as nama_kota,
                    pr.name as nama_provinsi,
                    pp.id_perkumpulan,
                    md5(concat(pk.id,  "'.$this->perkumpulan->imbuhan.'")) AS encrypted_id_perkumpulan,
                    pk.nama as nama_perkumpulan,
                    sa.nama as nama_status_anggota,
                    pp.posisi,
                    pp.no_punggung,
                    pp.tinggi,
                    pp.berat,
                    pp.penyakit,
                    pp.operasi,
                    pp.tgl_pengajuan,
                    pp.tgl_apv_perkumpulan,
                    pp.tgl_apv_admin,
                    pp.id_apr_perkumpulan,
                    apr_pk.username as username_apr_perkumpulan,
                    pp.id_apr_admin,
                    apr_adm.username as username_apr_admin,
                    pp.reason,
                    pp.tgl_expired,
                    pp.status,
                    pp.no_anggota,
                    pp.status_aktif, 
                    pp.tgl_expired,
                    CASE 
                        WHEN pp.status = -2 THEN "Ditolak Master Admin"
                        WHEN pp.status = -1 THEN "Ditolak Perkumpulan"
                        WHEN pp.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                        WHEN pp.status = 1 THEN "Disetujui Perkumpulan, Menunggu Master Admin" 
                        WHEN pp.status = 2 THEN "Disetujui Master Admin"
                        ELSE "Status Tidak Dikenal"
                    END as status_text
                FROM m_pengajuan_pemain pp
                LEFT JOIN m_user u ON pp.id_user = u.id
                LEFT JOIN m_kota k ON pp.id_kota = k.id
                LEFT JOIN m_provinsi pr ON k.provinsi_id = pr.id
                LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
                LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                LEFT JOIN m_admin apr_pk ON pp.id_apr_perkumpulan = apr_pk.id
                LEFT JOIN m_admin apr_adm ON pp.id_apr_admin = apr_adm.id
                WHERE md5(concat(pp.id, "'.$this->pengajuanPemain->imbuhan.'")) = ?
                ', [$id]
            );

            if (empty($pengajuan)) {
                return ResponseController::sendError(52); // Pengajuan tidak ditemukan
            }

            $detail = $pengajuan[0];

            // Get prestasi list
            $prestasi = $this->prestasi->getPrestasiByPengajuan($detail->id);
            $detail->prestasi = $prestasi;

            // Get education history
            $detail->pendidikan = $this->pendidikan->getPendidikanByUser($detail->id_user);

            foreach (User::parentInfo($detail->id_user) as $k => $v) {
                $detail->$k = $v;
            }

            $detail->id = "";

            return ResponseController::sendSuccess([
                'message' => 'Detail pengajuan pemain berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Detail Pengajuan SDM (Non Anggota)
     */
    public function detailPengajuanSDM(Request $request)
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
            // Get pengajuan detail
            $pengajuan = DB::select(
                'SELECT 
                    pn.id,
                    md5(concat(pn.id, "'.$this->pengajuanNonAnggota->imbuhan.'")) AS encrypted_id,
                    pn.id_user,
                    md5(concat(u.id, "U53R")) AS encrypted_id_user,
                    u.nama as nama_user,
                    u.email as email_user,
                    u.no_hp as no_hp_user,
                    u.no_darurat,
                    u.hubungan_nodarurat,
                    u.pekerjaan,
                    u.nik,
                    u.no_pelajar,
                    u.no_kk,
                    u.alamat,
                    u.tempat_lahir,
                    u.tanggal_lahir,
                    u.jenis_kelamin,    
                    u.filename_foto,
                    pn.id_perkumpulan,
                    md5(concat(pk.id,  "'.$this->perkumpulan->imbuhan.'")) AS encrypted_id_perkumpulan,
                    pk.nama as nama_perkumpulan,
                    sa.nama as nama_status_anggota,
                    pn.tahun,
                    pn.id_posisi,
                    md5(concat(pos.id, "'.$this->posisi->imbuhan.'")) AS encrypted_id_posisi,
                    pos.nama as nama_posisi,
                    pn.jabatan,
                    pn.tgl_pengajuan,
                    pn.tgl_apv_perkumpulan,
                    pn.tgl_apv_admin,
                    pn.id_apr_perkumpulan,
                    apr_pk.username as username_apr_perkumpulan,
                    pn.id_apr_admin,
                    apr_adm.username as username_apr_admin,
                    pn.reason,
                    pn.status,
                    pn.no_anggota,
                    pn.status_aktif, 
                    pn.tgl_expired,
                    CASE 
                        WHEN pn.status = -2 THEN "Ditolak Master Admin"
                        WHEN pn.status = -1 THEN "Ditolak Perkumpulan"
                        WHEN pn.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                        WHEN pn.status = 1 THEN "Menunggu Persetujuan Master Admin" 
                        WHEN pn.status = 2 THEN "Disetujui"
                        ELSE "Status Tidak Dikenal"
                    END as status_text
                FROM m_pengajuan_nonaggota pn
                LEFT JOIN m_user u ON pn.id_user = u.id
                LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
                LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
                LEFT JOIN m_admin apr_pk ON pn.id_apr_perkumpulan = apr_pk.id
                LEFT JOIN m_admin apr_adm ON pn.id_apr_admin = apr_adm.id
                WHERE md5(concat(pn.id, "'.$this->pengajuanNonAnggota->imbuhan.'")) = ?
                ', [$id]
            );

            if (empty($pengajuan)) {
                return ResponseController::sendError(52); // Pengajuan tidak ditemukan
            }

            $detail = $pengajuan[0];
            
            // Get education history
            $detail->pendidikan = $this->pendidikan->getPendidikanByUser($detail->id_user);

            foreach (User::parentInfo($detail->id_user) as $k => $v) {
                $detail->$k = $v;
            }

            // Get prestasi SDM list
            $prestasiSDM = $this->prestasiSDM->getPrestasiSDMByPengajuan($detail->id);
            $detail->prestasi = $prestasiSDM;

            // Get training list
            $training = $this->training->getTrainingsByPengajuan($detail->id);
            $detail->training = $training;

            // Get lisensi list
            $lisensi = $this->lisensi->getLisensiByPengajuan($detail->id);
            $detail->lisensi = $lisensi;

            // Get event list
            $event = $this->event->getEventsByPengajuan($detail->id);
            $detail->event = $event;

            return ResponseController::sendSuccess([
                'message' => 'Detail pengajuan SDM berhasil diambil',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. List Pengajuan
     */
    public function listPengajuan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $tipe = $request->tipe; // 'pemain' or 'sdm'
        $tahun = $request->tahun;
        $id_userenc = $request->id_user;
        $id_perkumpulan = $request->id_perkumpulan;
        $status = $request->status;
        $non_anggota = $request->non_anggota; // "1" tampilkan non anggota, 0 tampilkan anggota
        $keyword = $request->keyword;

        try { 
            $listPengajuan = [];
 
            // Get detail user
            $id_user = null;
            if (!empty($id_userenc)) {
                $user = $this->user->getUserByEncId($id_userenc);
                if ($user) {
                    $id_user = $user->id;
                }
            }

            if (empty($tipe) || $tipe === 'pemain') { 
                // Get pengajuan pemain
                $pengajuanPemain = DB::select(
                    'SELECT 
                        md5(concat(pp.id, "'.$this->pengajuanPemain->imbuhan.'")) AS id,
                        "pemain" as tipe,
                        pk.nama as nama_perkumpulan,
                        sa.nama as status_anggota_perkumpulan,
                        pp.posisi as posisi_diajukan,
                        pp.tgl_pengajuan as tanggal_pengajuan,
                        pp.status,
                        u.nama as nama_user,
                        u.email as email_user,
                        CASE 
                            WHEN pp.status = -2 THEN "Ditolak Master Admin"
                            WHEN pp.status = -1 THEN "Ditolak Perkumpulan"
                            WHEN pp.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                            WHEN pp.status = 1 THEN "Menunggu Persetujuan Master Admin" 
                            WHEN pp.status = 2 THEN "Disetujui"
                            ELSE "Status Tidak Dikenal"
                        END as status_text
                    FROM m_pengajuan_pemain pp
                    LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
                    LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                    LEFT JOIN m_user u ON pp.id_user = u.id
                    WHERE 1=1
                    ' . (!empty($tahun) ? ' AND pp.tahun = '.$tahun : '') . '
                    ' . (!empty($id_perkumpulan) ? ' AND pk.id = '.$id_perkumpulan : '') . '
                    ' . ($status !== null && $status !== '' ? ' AND pp.status = '.$status : '') . '
                    ' . ($id_user ? ' AND pp.id_user = '.$id_user : '') . '
                    ' . ($non_anggota == 1 ? ' AND (pp.id_perkumpulan IS NULL OR pp.id_perkumpulan = "")' : '') . '
                    ' . (!empty($keyword) ? ' AND (u.nama LIKE "%'.$keyword.'%" OR u.email LIKE "%'.$keyword.'%")' : '') . '
                    ORDER BY pp.tgl_pengajuan DESC
                    '
                );
                $listPengajuan = array_merge($listPengajuan, $pengajuanPemain);

            }

            if (empty($tipe) || $tipe === 'sdm') {
                // Get pengajuan SDM
                $pengajuanSDM = DB::select(
                    'SELECT 
                        md5(concat(pn.id, "'.$this->pengajuanNonAnggota->imbuhan.'")) AS id,
                        "sdm" as tipe,
                        pk.nama as nama_perkumpulan,
                        sa.nama as status_anggota_perkumpulan,
                        pos.nama as posisi_diajukan,
                        pn.tgl_pengajuan as tanggal_pengajuan,
                        pn.status,
                        u.nama as nama_user,
                        u.email as email_user,
                        CASE 
                            WHEN pn.status = -2 THEN "Ditolak Master Admin"
                            WHEN pn.status = -1 THEN "Ditolak Perkumpulan"
                            WHEN pn.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                            WHEN pn.status = 1 THEN "Menunggu Persetujuan Master Admin" 
                            WHEN pn.status = 2 THEN "Disetujui"
                            ELSE "Status Tidak Dikenal"
                        END as status_text
                    FROM m_pengajuan_nonaggota pn
                    LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
                    LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                    LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
                    LEFT JOIN m_user u ON pn.id_user = u.id
                    WHERE 1=1
                    ' . (!empty($tahun) ? ' AND pn.tahun = '.$tahun : '') . '
                    ' . (!empty($id_perkumpulan) ? ' AND pk.id = '.$id_perkumpulan : '') . '
                    ' . ($status !== null && $status !== '' ? ' AND pn.status = '.$status : '') . '
                    ' . ($id_user ? ' AND pn.id_user = '.$id_user : '') . '
                    ' . ($non_anggota == 1 ? ' AND (pn.id_perkumpulan IS NULL OR pn.id_perkumpulan = "")' : '') . '
                    ' . (!empty($keyword) ? ' AND (u.nama LIKE "%'.$keyword.'%" OR u.email LIKE "%'.$keyword.'%")' : '') . '
                    ORDER BY pn.tgl_pengajuan DESC
                    '
                );
                $listPengajuan = array_merge($listPengajuan, $pengajuanSDM);
            }

            // Sort by status first (0, 1, 2, -1, -2), then by tanggal_pengajuan DESC
            usort($listPengajuan, function($a, $b) {
                // Define status priority
                $statusPriority = [0 => 1, 1 => 2, 2 => 3, -1 => 4, -2 => 5];
                
                $priorityA = $statusPriority[$a->status] ?? 999;
                $priorityB = $statusPriority[$b->status] ?? 999;
                
                // Sort by status priority first
                if ($priorityA != $priorityB) {
                    return $priorityA - $priorityB;
                }
                
                // If status is the same, sort by tanggal_pengajuan DESC
                return strcmp($b->tanggal_pengajuan, $a->tanggal_pengajuan);
            });

            $paged = GlobalHelperController::paginate($listPengajuan, $request);

            return ResponseController::sendSuccess([
                'message' => 'List pengajuan berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 6. Approve Pengajuan Admin Perkumpulan
     */
    public function approvePerkumpulan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        $id = $request->id;
        $tipe = $request->tipe; // 'pemain' or 'sdm'
        $id_admin = $request->id_admin;
        // $nomor_anggota = $request->nomor_anggota;
        // $status_aktif = $request->status_aktif;
        // $tanggal_expired = $request->tanggal_expired;

        if (empty($id) || empty($tipe) || empty($id_admin)) {
            return ResponseController::sendError(98);
        }

        try {
            // Validate admin exists
            $admin = $this->admin->getAdminByUserEncId($id_admin);
            if (!$admin) {
                return ResponseController::sendError(47); // Admin tidak ditemukan
            }

            if ($tipe === 'pemain') {
                $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is pending (0)
                if ($pengajuan->status != 0) {
                    return ResponseController::sendError(53); // Pengajuan sudah diproses
                }

                // Update to approved by perkumpulan
                DB::update(
                    'UPDATE m_pengajuan_pemain 
                    SET status = 1, 
                        tgl_apv_perkumpulan = ?, 
                        id_apr_perkumpulan = ?,
                        status_aktif = ?
                    WHERE id = ?',
                    [date('Y-m-d H:i:s'), $admin->id, "1", $pengajuan->id]
                );
            } elseif ($tipe === 'sdm') {
                $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is pending (0)
                if ($pengajuan->status != 0) {
                    return ResponseController::sendError(53);
                }

                // Update to approved by perkumpulan
                DB::update(
                    'UPDATE m_pengajuan_nonaggota 
                    SET status = 1, 
                        tgl_apv_perkumpulan = ?, 
                        id_apr_perkumpulan = ?,
                        status_aktif = ?
                    WHERE id = ?',
                    [date('Y-m-d H:i:s'), $admin->id, "1", $pengajuan->id]
                );
            } else {
                return ResponseController::sendError(54); // Tipe pengajuan tidak valid
            }

            // Notify user
            $user = User::find($pengajuan->id_user);
            $perkumpulan = Perkumpulan::find($pengajuan->id_perkumpulan);
            $tokens = User::notifTokens($user);
            if(!empty($tokens)){
                GlobalHelperController::sendNotif($tokens, User::notifTitle($user, "Pengajuan ".$tipe." disetujui"), $perkumpulan->nama." telah menyetujui pengajuan anda", $pengajuan->id);
            }
            

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan berhasil disetujui oleh admin perkumpulan'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 7. Reject Pengajuan Admin Perkumpulan
     */
    public function rejectPerkumpulan(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $tipe = $request->tipe; // 'pemain' or 'sdm'
        $reason = $request->reason;

        if (empty($id) || empty($tipe)) {
            return ResponseController::sendError(98);
        }

        try {
            if ($tipe === 'pemain') {
                $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is pending (0)
                if ($pengajuan->status != 0) {
                    return ResponseController::sendError(53);
                }

                // Update to rejected by perkumpulan
                DB::update(
                    'UPDATE m_pengajuan_pemain 
                    SET status = -1, 
                        reason = ?
                    WHERE id = ?',
                    [$reason, $pengajuan->id]
                );
            } elseif ($tipe === 'sdm') {
                $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is pending (0)
                if ($pengajuan->status != 0) {
                    return ResponseController::sendError(53);
                }

                // Update to rejected by perkumpulan
                DB::update(
                    'UPDATE m_pengajuan_nonaggota 
                    SET status = -1, 
                        reason = ?
                    WHERE id = ?',
                    [$reason, $pengajuan->id]
                );
            } else {
                return ResponseController::sendError(54);
            }

            // Notify user
            $user = User::find($pengajuan->id_user);
            $perkumpulan = Perkumpulan::find($pengajuan->id_perkumpulan);
            $tokens = User::notifTokens($user);
            if(!empty($tokens)){
                GlobalHelperController::sendNotif($tokens, User::notifTitle($user, "Pengajuan ".$tipe." ditolak"), $perkumpulan->nama." telah menolak pengajuan anda", $pengajuan->id);
            }
            
            return ResponseController::sendSuccess([
                'message' => 'Pengajuan berhasil ditolak oleh admin perkumpulan'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 8. Approve Pengajuan Admin Master
     */
    public function approveMaster(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $tipe = $request->tipe; // 'pemain' or 'sdm'
        $id_admin = $request->id_admin;
        // $nomor_anggota = $request->nomor_anggota;
        // $status_aktif = $request->status_aktif;
        // $tanggal_expired = $request->tanggal_expired;

        if (empty($id) || empty($tipe) || empty($id_admin)) {
            return ResponseController::sendError(98);
        }

        try {
            // Validate admin exists
            $admin = $this->admin->getAdminByUserEncId($id_admin);
            if (!$admin) {
                return ResponseController::sendError(47);
            }

            if ($tipe === 'pemain') {
                $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is approved by perkumpulan (1)
                if ($pengajuan->status != 1) {
                    return ResponseController::sendError(55); // Belum disetujui perkumpulan
                }

                // jika pengajuan pertama maka catat tgl_expired 1 tahun dari tgl_apv_admin
                $totalPengajuanDisetujui = DB::select(
                    'SELECT COUNT(*) as total FROM m_pengajuan_pemain 
                    WHERE id_user = ? AND status = 2',
                    [$pengajuan->id_user]
                );
                if($totalPengajuanDisetujui[0]->total == 0){
                    $tgl_expired = date('Y-m-d', strtotime('+1 year', strtotime(date('Y-m-d H:i:s'))));
                    DB::update(
                        'UPDATE m_user 
                        SET tgl_expired = ?
                        WHERE id = ?',
                        [$tgl_expired, $pengajuan->id_user]  
                    );
                }

                // Update to approved by admin master
                DB::update(
                    'UPDATE m_pengajuan_pemain 
                    SET status = 2, 
                        tgl_apv_admin = ?, 
                        id_apr_admin = ?,
                        status_aktif = "1"
                    WHERE id = ?',
                    [date('Y-m-d H:i:s'), $admin->id, $pengajuan->id]
                );
            } elseif ($tipe === 'sdm') {
                $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is approved by perkumpulan (1)
                if ($pengajuan->status != 1) {
                    return ResponseController::sendError(55);
                }

                // jika pengajuan pertama maka catat tgl_expired 1 tahun dari tgl_apv_admin
                $totalPengajuanDisetujui = DB::select(
                    'SELECT COUNT(*) as total FROM m_pengajuan_nonaggota 
                    WHERE id_user = ? AND status = 2',
                    [$pengajuan->id_user]
                );
                if($totalPengajuanDisetujui[0]->total == 0){
                    $tgl_expired = date('Y-m-d', strtotime('+1 year', strtotime(date('Y-m-d H:i:s'))));
                    DB::update(
                        'UPDATE m_user 
                        SET tgl_expired = ?
                        WHERE id = ?',
                        [$tgl_expired, $pengajuan->id_user]  
                    );
                }


                // Update to approved by admin master
                DB::update(
                    'UPDATE m_pengajuan_nonaggota 
                    SET status = 2, 
                        tgl_apv_admin = ?, 
                        id_apr_admin = ?,
                        status_aktif = "1"
                    WHERE id = ?',
                    [date('Y-m-d H:i:s'), $admin->id, $pengajuan->id]
                );
            } else {
                return ResponseController::sendError(54);
            }

            // Notify user
            $user = User::find($pengajuan->id_user);
            $tokens = User::notifTokens($user);
            if(!empty($tokens)){
                GlobalHelperController::sendNotif($tokens, User::notifTitle($user, "Pengajuan ".$tipe." disetujui"), "Perbasi Pemalang telah menyetujui pengajuan anda", $pengajuan->id);
            }
            

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan berhasil disetujui oleh admin master'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 9. Reject Pengajuan Admin Master
     */
    public function rejectMaster(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $tipe = $request->tipe; // 'pemain' or 'sdm'
        $reason = $request->reason;

        if (empty($id) || empty($tipe)) {
            return ResponseController::sendError(98);
        }

        try {
            if ($tipe === 'pemain') {
                $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is approved by perkumpulan (1)
                if ($pengajuan->status != 1) {
                    return ResponseController::sendError(55);
                }

                // Update to rejected by admin master
                DB::update(
                    'UPDATE m_pengajuan_pemain 
                    SET status = -2, 
                        reason = ?
                    WHERE id = ?',
                    [$reason, $pengajuan->id]
                );
            } elseif ($tipe === 'sdm') {
                $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52);
                }

                // Check if status is approved by perkumpulan (1)
                if ($pengajuan->status != 1) {
                    return ResponseController::sendError(55);
                }

                // Update to rejected by admin master
                DB::update(
                    'UPDATE m_pengajuan_nonaggota 
                    SET status = -2, 
                        reason = ?
                    WHERE id = ?',
                    [$reason, $pengajuan->id]
                );
            } else {
                return ResponseController::sendError(54);
            }

            // Notify user
            $user = User::find($pengajuan->id_user);
            $tokens = User::notifTokens($user);
            if(!empty($tokens)){
                GlobalHelperController::sendNotif($tokens, User::notifTitle($user, "Pengajuan ".$tipe." ditolak"), "Perbasi Pemalang telah menolak pengajuan anda", $pengajuan->id);
            }

            return ResponseController::sendSuccess([
                'message' => 'Pengajuan berhasil ditolak oleh admin master'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }


    public function uploadFilePengajuan(Request $request){
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
            $file->move('pengajuan', $filename); 
        }
        
        return ResponseController::sendSuccess([
            'message' => 'success', 
        ]); 
    }

    /**
     * API to send notification to admin master and admin perkumpulan
     * Based on pengajuan ID
     */
    public function notifyAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['id', 'tipe'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id = $request->id; // encrypted pengajuan ID
        $tipe = $request->tipe; // 'pemain' or 'sdm'

        try {
            $pengajuan = null;
            $id_perkumpulan = null;
            $id_user = null;
            $posisi_text = "";
            $isApprovedByPerkumpulan = false;
            $isApprovedByAdmin = false;

            // Get pengajuan data based on type
            if ($tipe === 'pemain') {
                $pengajuan = $this->pengajuanPemain->getPengajuanPemainByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52); // Pengajuan tidak ditemukan
                }
                $id_perkumpulan = $pengajuan->id_perkumpulan;
                $id_user = $pengajuan->id_user;
                $posisi_text = $pengajuan->posisi;
                $isApprovedByPerkumpulan = $pengajuan->id_apr_perkumpulan != null;
                $isApprovedByAdmin = $pengajuan->id_apr_admin != null;
            } elseif ($tipe === 'sdm') {
                $pengajuan = $this->pengajuanNonAnggota->getPengajuanNonAnggotaByEncId($id);
                if (!$pengajuan) {
                    return ResponseController::sendError(52); // Pengajuan tidak ditemukan
                }
                $id_perkumpulan = $pengajuan->id_perkumpulan;
                $id_user = $pengajuan->id_user;
                $isApprovedByPerkumpulan = $pengajuan->id_apr_perkumpulan != null;
                $isApprovedByAdmin = $pengajuan->id_apr_admin != null;
                // Get posisi name
                $posisi = $this->posisi->getPosisiById($pengajuan->id_posisi);
                $posisi_text = $posisi ? $posisi->nama : "";
            } else {
                return ResponseController::sendError(54); // Tipe pengajuan tidak valid
            }

            // Get user data
            $user = User::find($id_user);
            if (!$user) {
                return ResponseController::sendError(12); // User tidak ditemukan
            }

            // Build reminder-style title and message to prompt admins to process the submission
            $title = "";
            $message = "";

            if ($tipe === 'pemain') {
                // For pemain, posisi is stored directly
                $posisi_display = $posisi_text;
                if ($pengajuan->status == 0) {
                    $title = 'Tindakan Diperlukan: Pengajuan Anggota Baru';
                    $message = $user->nama . ' mengajukan pendaftaran sebagai ' . $posisi_display . '. Mohon segera diproses oleh admin.';
                } else {
                    $title = 'Update Pengajuan Anggota';
                    $message = 'Update untuk ' . $user->nama . ' (' . $posisi_display . '). Mohon tinjau dan proses.';
                }
            } else {
                // sdm
                $posisi_display = $posisi_text;
                if ($pengajuan->status == 0) {
                    $title = 'Tindakan Diperlukan: Pengajuan SDM Baru';
                    $message = $user->nama . ' mengajukan pendaftaran sebagai ' . $posisi_display . '. Mohon segera diproses oleh admin.';
                } else {
                    $title = 'Update Pengajuan SDM';
                    $message = 'Update untuk ' . $user->nama . ' (' . $posisi_display . '). Mohon tinjau dan proses.';
                }
            }

            $tokenAdminMaster = [];
            if($isApprovedByPerkumpulan){
                // Notify admin master
                $tokenAdminMaster = $this->user->getAdminMasterFCMTokens();
                if (!empty($tokenAdminMaster)) {
                    GlobalHelperController::sendNotif($tokenAdminMaster, $title, $message);
                }
            }else{
                // Notify admin perkumpulan if exists
                if ($id_perkumpulan) {
                    $tokenAdminPerkumpulan = $this->user->getAdminPerkumpulanFCMTokens($id_perkumpulan);
                    if (!empty($tokenAdminPerkumpulan)) {
                        GlobalHelperController::sendNotif($tokenAdminPerkumpulan, $title, $message);
                    }
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Notifikasi berhasil dikirim ke admin',
                'isApprovedByPerkumpulan' => $isApprovedByPerkumpulan,
                'isPerkumpulan' => $id_perkumpulan,
                'tokenAdminMaster' => $tokenAdminMaster,
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
