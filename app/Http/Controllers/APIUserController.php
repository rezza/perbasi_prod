<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use DateTime;
use App\User;
use App\Pendidikan;
use App\Kota;
use App\Http\Controllers\GlobalHelperController;

class APIUserController extends Controller{

    protected $user;
    protected $kota;

    public function __construct()
    {
        $this->user = new User();
        $this->kota = new Kota();   
    }

    //Create
    public function registerUser(Request $request){
        $nama       = $request->nama;
        $email      = $request->email;
        $password   = $request->password;
        $id_kotaenc = $request->id_kota;

        $email = trim((string) $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseController::sendError(63); // Format email tidak valid
        }

        // Check if user already exists
        $res = User::where('email', $email)->first();
        if($res){
            return ResponseController::sendError(3);  
        }

        // Parents mode: akun orang tua tidak punya kota maupun no_anggota
        $isOrtu = $request->tipe_akun === 'ortu';
        if ($isOrtu && empty($password)) {
            return ResponseController::sendError(98);
        }

        $resKota = null;
        if (!$isOrtu) {
            $resKota = $this->kota->getKotaByEncId($id_kotaenc);
            if(!$resKota){
                return ResponseController::sendError(36);  
            }
        }

        try {
            // Generate no_anggota berdasarkan id_kota
            // Format: <full_code kota> <tahun> <nomor urut 4 digit>
            // Nomor urut restart dari 0001 setiap awal tahun
            $no_anggota = $isOrtu ? null : $this->user->generateNoAnggota($resKota->id);

            $new_pass = Hash::make($password);
            $user = new User([
                'nama' => $nama,
                'email' => $email,
                'password' => $new_pass,
                'no_anggota' => $no_anggota,
                'tipe_akun' => $isOrtu ? 'ortu' : 'member',
                'status' => 1,
            ]);
            $user->save();

            return ResponseController::sendSuccess([
                'message' => 'New User has been added successfully',
                'no_anggota' => $no_anggota
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Login
    public function submitLogin(Request $request)
    {
        $username = $request->username; // bisa email atau no_hp
        $password = $request->password;

        // Find user by email
        $user = User::where('email', $username)
            ->where('status', '<>', '-1')
            ->first();
        if(!$user){
            return ResponseController::sendError(11);  
        }
        
        if($user->status != 1){
            return ResponseController::sendError(20);  
        }

        if ($user && !empty($user->password) && Hash::check($password, $user->password)) {
            // Akun orang tua hanya bisa dipakai di app yang mendukung parents mode
            if ($user->tipe_akun === 'ortu' && $request->client_caps !== 'parents') {
                return ResponseController::sendError(100);
            }

            // Generate JWT Token
            $token = GlobalHelperController::generateToken(
                $user->id, 
                $user->email, 
                $user->nama, 
            );

            return ResponseController::sendSuccess([
                'message' => 'login berhasil',
                'token' => $token,
                'data' => $this->user->getLoginData($user->id),
            ]);
        }

        return ResponseController::sendError(11);
    }

    /**
     * Exchange a one-time SSO code (issued by ComproController::ssoMemberRedirect
     * when a logged-in compro visitor clicks "Dashboard") for a fresh JWT --
     * lets the separate member web app sign the visitor in without asking
     * them to re-enter credentials. The code is single-use (Cache::pull
     * deletes it on read) and short-lived.
     */
    public function ssoExchange(Request $request)
    {
        $code = $request->code;
        if (empty($code)) {
            return ResponseController::sendError(98);
        }

        $userId = Cache::pull('sso_member_' . $code);
        if (!$userId) {
            return ResponseController::sendError(11);
        }

        $user = User::where('id', $userId)->where('status', '<>', '-1')->first();
        if (!$user || $user->status != 1) {
            return ResponseController::sendError(11);
        }

        $token = GlobalHelperController::generateToken($user->id, $user->email, $user->nama);

        return ResponseController::sendSuccess([
            'message' => 'login berhasil',
            'token' => $token,
            'data' => $this->user->getLoginData($user->id),
        ]);
    }


    public function submitLoginAdmin(Request $request)
    {
        $username = $request->username; // bisa email atau no_hp
        $password = $request->password;

        // Find user by email
        $user = User::where('email', $username)->where('status', '!=', '-1')->first();
        if(!$user){
            return ResponseController::sendError(11);  
        }
        
        if($user->status != 1){
            return ResponseController::sendError(20);  
        }

        if ($user && !empty($user->password) && Hash::check($password, $user->password)) {
            // Generate JWT Token
            $token = GlobalHelperController::generateToken(
                $user->id, 
                $user->email, 
                $user->nama, 
            );

            // pengecekan posisi admin pada tabel m_admin
            $admin = DB::table('m_admin')->where([['id_user', $user->id], ['status', 1]])->first();
            if(!$admin){
                return ResponseController::sendError(21);  
            }

            $perkumpulan = null;
            if($admin->id_role == 2){ // jika admin perkumpulan
                $perkumpulan = DB::table('m_perkumpulan')->where([['id', $admin->id_perkumpulan], ['status', 1]])->first();
            }   

            return ResponseController::sendSuccess([
                'message' => 'login berhasil',
                'token' => $token,
                'data' => $this->user->getLoginData($user->id),
                "id_role" => $admin->id_role,
                "id_perkumpulan" => $perkumpulan ? $perkumpulan->id : null,
                "nama_perkumpulan" => $perkumpulan ? $perkumpulan->nama : null
            ]);
        }

        return ResponseController::sendError(11);  
    }

    public function getUserList(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        } 

        $keyword = $request->keyword;
        $posisi = $request->posisi;

        return ResponseController::sendSuccess([
            "message" => "Data user berhasil diambil",
            "data" => $this->user->getUsers($keyword, $posisi)
        ]);
    }

    public function resetPasswordUserByAdmin(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $encryptedId = $request->iduser;
        $password    = $request->password;

        $user = $this->user->getUserByEncId($encryptedId);
        if(!$user){
            return ResponseController::sendError(12);  
        }

        $updateData = User::find($user->id);
        if(!$updateData){
            return ResponseController::sendError(12);  
        }
        
        $new_pass = Hash::make($password);
        $updateData->password = $new_pass;
        $updateData->save();

        return ResponseController::sendSuccess(['message' => "Password user berhasil diubah"]); 
    }

    public function ubahPassword(Request $request){
        $encryptedId       = $request->iduser;
        $passwordLama      = $request->passwordLama;
        $passwordBaru      = $request->passwordBaru;

        $user = $this->user->getUserByEncId($encryptedId);
        if(!$user){
            return ResponseController::sendError(13);  
        }

        $user = User::find($user->id);
        if(!$user){
            return ResponseController::sendError(13);  
        }

        if(!Hash::check($passwordLama, $user->password)){
            return ResponseController::sendError(14);   
        }

        $new_pass = Hash::make($passwordBaru);
        $user->password = $new_pass; 
        $user->save();

        return ResponseController::sendSuccess(['message' => "Password has been changed successfully"]); 
    }

    public function ubahPosisiTerkini(Request $request){
        $encryptedId       = $request->iduser;
        $position      = $request->position;

        $user = $this->user->getUserByEncId($encryptedId);
        if(!$user){
            return ResponseController::sendError(13);  
        }

        $user = User::find($user->id);
        $user->posisi = $position; 
        $user->save();

        return ResponseController::sendSuccess([
            'message' => "User position has been changed successfully"
        ]); 
    }

    //Delete
    public function deleteUserByAdmin(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $encryptedId = $request->id;

        $user = $this->user->getUserByEncId($encryptedId);
        if(!$user){
            return ResponseController::sendError(12);  
        }

        $hapusData = User::find($user->id);
        if(!$hapusData){
            return ResponseController::sendError(12);
        }

        $hasAdmin = DB::select('SELECT COUNT(*) as total FROM m_admin WHERE id_user = ? AND status = 1', [$user->id]);
        if ($hasAdmin[0]->total > 0) {
            return ResponseController::sendError(71);
        }

        $hasPengajuanPemain = DB::select('SELECT COUNT(*) as total FROM m_pengajuan_pemain WHERE id_user = ? AND status >= 0', [$user->id]);
        if ($hasPengajuanPemain[0]->total > 0) {
            return ResponseController::sendError(71);
        }

        $hasPengajuanSDM = DB::select('SELECT COUNT(*) as total FROM m_pengajuan_nonaggota WHERE id_user = ? AND status >= 0', [$user->id]);
        if ($hasPengajuanSDM[0]->total > 0) {
            return ResponseController::sendError(71);
        }

        $hapusData->status = -1;
        $hapusData->save();

        return ResponseController::sendSuccess(['message' => "Data user berhasil dihapus"]);
    }

    // Get Profile
    public function getProfile(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];
        
        try {
            // Get user data
            $userData = $this->user->getLoginData($currentUser["user_id"]);
            if (!$userData) {
                return ResponseController::sendError(12);
            }

            // Get education history
            $pendidikan = new Pendidikan();
            $educationHistory = $pendidikan->getPendidikanByUser($currentUser["user_id"]);
            
            $userData->pendidikan = $educationHistory;

            return ResponseController::sendSuccess([
                'message' => 'Data profil berhasil diambil',
                'data' => $userData
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // List of perkumpulan the logged-in user is an approved (status = 2)
    // member of, via m_pengajuan_pemain / m_pengajuan_nonaggota. Used by the
    // event registration flow so a member can pick which perkumpulan they
    // are registering under.
    public function myPerkumpulan(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];

        try {
            $data = DB::select(
                'SELECT DISTINCT
                    md5(concat(p.id, "P3RKUMP")) AS id,
                    p.no_perkumpulan,
                    p.nama
                FROM m_perkumpulan p
                WHERE p.id IN (
                    SELECT id_perkumpulan FROM m_pengajuan_pemain WHERE id_user = ? AND status = 2 AND id_perkumpulan IS NOT NULL
                    UNION
                    SELECT id_perkumpulan FROM m_pengajuan_nonaggota WHERE id_user = ? AND status = 2 AND id_perkumpulan IS NOT NULL
                )
                ORDER BY p.nama ASC
                ', [$currentUser["user_id"], $currentUser["user_id"]]
            );

            return ResponseController::sendSuccess([
                'message' => 'Daftar perkumpulan berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Update Profile
    public function updateProfile(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];
        
        // Get request data
        $nama = $request->nama;
        $tempat_lahir = $request->tempat_lahir;
        $tanggal_lahir = $request->tanggal_lahir;
        $jenis_kelamin = $request->jenis_kelamin;
        $alamat = $request->alamat;
        $no_hp = $request->no_hp;
        $email = $request->email;
        $no_darurat = $request->no_darurat;
        $hubungan_nodarurat = $request->hubungan_nodarurat;
        $pekerjaan = $request->pekerjaan;
        $nik = $request->nik;
        $no_pelajar = $request->no_pelajar;
        $no_kk = $request->no_kk;
        $filename_ktp = $request->filename_ktp;
        $filename_ttd = $request->filename_ttd;
        $filename_foto = $request->filename_foto;
        $list_pendidikan = $request->list_pendidikan; // array of [{tahun, nama, jenjang}]

        try {
            DB::beginTransaction();

            // Find user
            $user = User::find($currentUser["user_id"]);
            if (!$user) {
                return ResponseController::sendError(12);
            }

            // Validate email uniqueness if email is being changed
            if (!empty($email) && $email !== $user->email) {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    DB::rollback();
                    return ResponseController::sendError(63); // Format email tidak valid
                }
                $existingUser = User::where('email', $email)->where('id', '!=', $currentUser["user_id"])->first();
                if ($existingUser) {
                    return ResponseController::sendError(56); // Email sudah digunakan user lain
                }
                $user->email = $email;
            }

            // Parents mode: akun anak yang belum punya kredensial diaktifkan dengan
            // mengisi email + password sekaligus. Akun lain tidak terpengaruh.
            if (!empty($user->id_parents) && empty($user->password)) {
                $passwordBaru = $request->password;
                if (!empty($passwordBaru) || !empty($email)) {
                    if (empty($passwordBaru) || empty($user->email)) {
                        DB::rollback();
                        return ResponseController::sendError(102);
                    }
                    if (strlen($passwordBaru) < 6) {
                        DB::rollback();
                        return ResponseController::sendError(66);
                    }
                    $user->password = Hash::make($passwordBaru);
                }
            }

            // Validate phone uniqueness if phone is being changed
            if (!empty($no_hp) && $no_hp !== $user->no_hp) {
                $existingUser = User::where('no_hp', $no_hp)->where('id', '!=', $currentUser["user_id"])->first();
                if ($existingUser) {
                    return ResponseController::sendError(57); // Nomor HP sudah digunakan user lain
                }
                $user->no_hp = $no_hp;
            }

            // Validate NIK uniqueness if NIK is being changed
            if (!empty($nik) && $nik !== $user->nik) {
                $existingUser = User::where('nik', $nik)->where('id', '!=', $currentUser["user_id"])->first();
                if ($existingUser) {
                    return ResponseController::sendError(58); // NIK sudah digunakan user lain
                }
                $user->nik = $nik;
            }

            // Validate tanggal_lahir format
            if (!empty($tanggal_lahir)) {
                $date = DateTime::createFromFormat('Y-m-d', $tanggal_lahir);
                if (!$date || $date->format('Y-m-d') !== $tanggal_lahir) {
                    return ResponseController::sendError(59); // Format tanggal lahir tidak valid
                }
                $user->tanggal_lahir = $tanggal_lahir;
            }

            // Validate jenis_kelamin
            if (!empty($jenis_kelamin)) {
                if (!in_array($jenis_kelamin, ['1', '0'])) {
                    return ResponseController::sendError(60); // Jenis kelamin tidak valid
                }
                $user->jenis_kelamin = $jenis_kelamin;
            }

            // Update user data
            if (!empty($nama)) $user->nama = $nama;
            if (!empty($tempat_lahir)) $user->tempat_lahir = $tempat_lahir;
            if (!empty($alamat)) $user->alamat = $alamat;
            if (!empty($no_darurat)) $user->no_darurat = $no_darurat;
            if (!empty($hubungan_nodarurat)) $user->hubungan_nodarurat = $hubungan_nodarurat;
            if (!empty($pekerjaan)) $user->pekerjaan = $pekerjaan;
            if (!empty($no_pelajar)) $user->no_pelajar = $no_pelajar;
            if (!empty($no_kk)) $user->no_kk = $no_kk;
            if (!empty($filename_ktp)) $user->filename_ktp = $filename_ktp;
            if (!empty($filename_ttd)) $user->filename_ttd = $filename_ttd;
            if (!empty($filename_foto)) $user->filename_foto = $filename_foto;

            $user->save();

            // Handle pendidikan data
            if (!empty($list_pendidikan) && is_array($list_pendidikan)) {
                // Delete existing pendidikan records for this user
                DB::table('m_pendidikan')->where('id_user', $currentUser["user_id"])->delete();
                
                // Insert new pendidikan records
                foreach ($list_pendidikan as $pendidikan) {
                    if (!empty($pendidikan['tahun']) && !empty($pendidikan['nama']) && !empty($pendidikan['jenjang'])) {
                        // Validate jenjang
                        $validJenjang = ['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];
                        if (!in_array($pendidikan['jenjang'], $validJenjang)) {
                            DB::rollback();
                            return ResponseController::sendError(61); // Jenjang pendidikan tidak valid
                        }

                        DB::table('m_pendidikan')->insert([
                            'id_user' => $currentUser["user_id"],
                            'tahun' => $pendidikan['tahun'],
                            'nama' => $pendidikan['nama'],
                            'jenjang' => $pendidikan['jenjang']
                        ]);
                    }
                }
            }

            DB::commit();

            return ResponseController::sendSuccess([
                'message' => 'Profil berhasil diperbarui',
                'data' => $this->user->getLoginData($currentUser["user_id"])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function updateProfileImage(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $currentUser = $validation['user'];
        $filename_foto = $request->filename_foto;

        try {
            // Find user
            $user = User::find($currentUser["user_id"]);
            if (!$user) {
                return ResponseController::sendError(12);
            }

            // Update profile image
            $user->filename_foto = $filename_foto;
            $user->save();

            return ResponseController::sendSuccess([
                'message' => 'Foto profil berhasil diperbarui',
                'data' => $this->user->getLoginData($currentUser["user_id"])
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Search Member Detail by No Anggota
    public function getMemberDetail(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $no_anggota = $request->no_anggota;

        if (empty($no_anggota)) {
            return ResponseController::sendError(98); // Parameter tidak lengkap
        }

        try {
            // Search user by no_anggota
            $userData = DB::select(
                'SELECT 
                    u.id,
                    md5(CONCAT(u.id, "'.$this->user->imbuhan.'")) as encrypted_id,
                    u.email,
                    u.nama,
                    u.tempat_lahir,
                    u.tanggal_lahir,
                    u.jenis_kelamin,
                    u.alamat,
                    u.no_hp,
                    u.no_darurat,
                    u.hubungan_nodarurat,
                    u.pekerjaan,
                    u.nik,
                    u.no_pelajar,
                    u.no_kk,
                    u.filename_ktp,
                    u.filename_ttd,
                    u.filename_foto,
                    u.posisi,
                    u.status,
                    u.no_anggota,
                    u.fcm_token,
                    u.os_type,
                    u.os_version
                FROM m_user u
                WHERE u.no_anggota = ? AND u.status = 1 AND u.tipe_akun <> "ortu"
                LIMIT 1',
                [$no_anggota]
            );

            if (empty($userData)) {
                return ResponseController::sendError(62); // Member dengan no anggota tidak ditemukan
            }

            $member = $userData[0];
            foreach (User::parentInfo($member->id) as $k => $v) {
                $member->$k = $v;
            }

            // Get education history
            $pendidikan = new Pendidikan();
            $educationHistory = $pendidikan->getPendidikanByUser($member->id);
            $member->pendidikan = $educationHistory;

            // Get pengajuan pemain (player applications)
            $pengajuanPemain = DB::select(
                'SELECT 
                    md5(concat(pp.id, "P3NG4JU4NPM4IN")) AS encrypted_id,
                    pp.tahun,
                    pp.id_kota,
                    k.name as nama_kota,
                    prov.name as nama_provinsi,
                    pp.id_perkumpulan,
                    pk.nama as nama_perkumpulan,
                    sa.nama as status_anggota,
                    pp.posisi,
                    pp.no_anggota,
                    pp.no_punggung,
                    pp.tinggi,
                    pp.berat,
                    pp.penyakit,
                    pp.operasi,
                    pp.tgl_pengajuan,
                    pp.tgl_apv_perkumpulan,
                    pp.tgl_apv_admin,
                    pp.reason,
                    pp.tgl_expired,
                    pp.status,
                    CASE 
                        WHEN pp.status = -2 THEN "Ditolak Master Admin"
                        WHEN pp.status = -1 THEN "Ditolak Perkumpulan"
                        WHEN pp.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                        WHEN pp.status = 1 THEN "Menunggu Persetujuan Master Admin" 
                        WHEN pp.status = 2 THEN "Disetujui"
                        ELSE "Status Tidak Dikenal"
                    END as status_text,
                    apr_pk.username as username_apr_perkumpulan,
                    apr_adm.username as username_apr_admin
                FROM m_pengajuan_pemain pp
                LEFT JOIN m_kota k ON pp.id_kota = k.id
                LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
                LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
                LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                LEFT JOIN m_admin apr_pk ON pp.id_apr_perkumpulan = apr_pk.id
                LEFT JOIN m_admin apr_adm ON pp.id_apr_admin = apr_adm.id
                WHERE pp.id_user = ?
                ORDER BY pp.tgl_pengajuan DESC',
                [$member->id]
            );

            // Get prestasi for each pengajuan pemain
            foreach ($pengajuanPemain as $pengajuan) {
                $prestasi = DB::select(
                    'SELECT 
                        p.tahun,
                        p.kompetisi,
                        p.prestasi
                    FROM m_prestasi p
                    WHERE p.id_pengajuan = (
                        SELECT pp.id FROM m_pengajuan_pemain pp 
                        WHERE md5(concat(pp.id, "P3NG4JU4NPM4IN")) = ?
                    )
                    ORDER BY p.tahun DESC',
                    [$pengajuan->encrypted_id]
                );
                $pengajuan->prestasi = $prestasi;
            }

            // Get pengajuan non anggota (SDM applications)
            $pengajuanSDM = DB::select(
                'SELECT 
                    md5(concat(pn.id, "P3NG4JU4NN0N4NGG")) AS encrypted_id,
                    pn.id_perkumpulan,
                    pk.nama as nama_perkumpulan,
                    pn.tahun,
                    pn.id_posisi,
                    pos.nama as nama_posisi,
                    pn.jabatan,
                    pn.tgl_pengajuan,
                    pn.tgl_apv_perkumpulan,
                    pn.tgl_apv_admin,
                    pn.reason,
                    pn.status,
                    CASE 
                        WHEN pn.status = -2 THEN "Ditolak Master Admin"
                        WHEN pn.status = -1 THEN "Ditolak Perkumpulan"
                        WHEN pn.status = 0 THEN "Menunggu Persetujuan Perkumpulan"
                        WHEN pn.status = 1 THEN "Menunggu Persetujuan Master Admin" 
                        WHEN pn.status = 2 THEN "Disetujui"
                        ELSE "Status Tidak Dikenal"
                    END as status_text,
                    apr_pk.username as username_apr_perkumpulan,
                    apr_adm.username as username_apr_admin
                FROM m_pengajuan_nonaggota pn
                LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
                LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
                LEFT JOIN m_admin apr_pk ON pn.id_apr_perkumpulan = apr_pk.id
                LEFT JOIN m_admin apr_adm ON pn.id_apr_admin = apr_adm.id
                WHERE pn.id_user = ?
                ORDER BY pn.tgl_pengajuan DESC',
                [$member->id]
            );

            // Get prestasi SDM, training, lisensi, event for each pengajuan SDM
            foreach ($pengajuanSDM as $pengajuan) {
                // Get prestasi SDM
                $prestasiSDM = DB::select(
                    'SELECT 
                        ps.tahun,
                        ps.prestasi
                    FROM m_prestasi_sdm ps
                    WHERE ps.id_pengajuan = (
                        SELECT pn.id FROM m_pengajuan_nonaggota pn 
                        WHERE md5(concat(pn.id, "P3NG4JU4NN0N4NGG")) = ?
                    )
                    ORDER BY ps.tahun DESC',
                    [$pengajuan->encrypted_id]
                );
                $pengajuan->prestasi_sdm = $prestasiSDM;

                // Get training
                $training = DB::select(
                    'SELECT 
                        t.tahun,
                        t.nama,
                        t.lokasi,
                        t.filename
                    FROM m_training t
                    WHERE t.id_pengajuan = (
                        SELECT pn.id FROM m_pengajuan_nonaggota pn 
                        WHERE md5(concat(pn.id, "P3NG4JU4NN0N4NGG")) = ?
                    )
                    ORDER BY t.tahun DESC',
                    [$pengajuan->encrypted_id]
                );
                $pengajuan->training = $training;

                // Get lisensi
                $lisensi = DB::select(
                    'SELECT 
                        l.tahun,
                        l.nama,
                        l.tanggal_mulai,
                        l.tanggal_expired,
                        l.filename
                    FROM m_lisensi l
                    WHERE l.id_pengajuan = (
                        SELECT pn.id FROM m_pengajuan_nonaggota pn 
                        WHERE md5(concat(pn.id, "P3NG4JU4NN0N4NGG")) = ?
                    )
                    ORDER BY l.tahun DESC',
                    [$pengajuan->encrypted_id]
                );
                $pengajuan->lisensi = $lisensi;

                // Get event
                $event = DB::select(
                    'SELECT 
                        e.tahun,
                        e.nama,
                        e.prestasi
                    FROM m_event e
                    WHERE e.id_pengajuan = (
                        SELECT pn.id FROM m_pengajuan_nonaggota pn 
                        WHERE md5(concat(pn.id, "P3NG4JU4NN0N4NGG")) = ?
                    )
                    ORDER BY e.tahun DESC',
                    [$pengajuan->encrypted_id]
                );
                $pengajuan->event = $event;
            }

            // Prepare data administrasi (administrative data)
            $dataAdministrasi = [
                'nik' => $member->nik,
                'no_pelajar' => $member->no_pelajar,
                'no_kk' => $member->no_kk,
                'filename_ktp' => $member->filename_ktp,
                'filename_ttd' => $member->filename_ttd,
                'filename_foto' => $member->filename_foto,
                'no_anggota' => $member->no_anggota,
                'status_anggota' => $member->status == 1 ? 'Aktif' : 'Tidak Aktif'
            ];

            // Prepare data kesehatan (health data) from latest pengajuan pemain
            $dataKesehatan = [];
            if (!empty($pengajuanPemain)) {
                $latestPengajuan = $pengajuanPemain[0]; // Get the latest pengajuan
                $dataKesehatan = [
                    'tinggi_badan' => $latestPengajuan->tinggi ?? null,
                    'berat_badan' => $latestPengajuan->berat ?? null,
                    'riwayat_penyakit' => $latestPengajuan->penyakit ?? null,
                    'riwayat_operasi' => $latestPengajuan->operasi ?? null,
                    'tanggal_data' => $latestPengajuan->tgl_pengajuan ?? null
                ];
            }

            // Prepare final response
            $memberDetail = [
                'data_pribadi' => [
                    'encrypted_id' => $member->encrypted_id,
                    'no_anggota' => $member->no_anggota,
                    'nama' => $member->nama,
                    'email' => $member->email,
                    'tempat_lahir' => $member->tempat_lahir,
                    'tanggal_lahir' => $member->tanggal_lahir,
                    'jenis_kelamin' => $member->jenis_kelamin == '1' ? 'Laki-laki' : 'Perempuan',
                    'alamat' => $member->alamat,
                    'no_hp' => $member->no_hp,
                    'no_darurat' => $member->no_darurat,
                    'hubungan_nodarurat' => $member->hubungan_nodarurat,
                    'pekerjaan' => $member->pekerjaan,
                    'posisi' => $member->posisi
                ],
                'data_administrasi' => $dataAdministrasi,
                'data_kesehatan' => $dataKesehatan,
                'riwayat_pendidikan' => $educationHistory,
                'riwayat_pengajuan_pemain' => $pengajuanPemain,
                'riwayat_pengajuan_sdm' => $pengajuanSDM
            ];

            return ResponseController::sendSuccess([
                'message' => 'Detail member berhasil diambil',
                'data' => $memberDetail
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Request Reset Password
    public function requestResetPassword(Request $request){
        $email = $request->email;

        if (empty($email)) {
            return ResponseController::sendError(98); // Parameter tidak lengkap
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ResponseController::sendError(63); // Format email tidak valid
        }

        try {
            // Find user by email
            $user = User::where('email', $email)->where('status', 1)->first();
            if (!$user) {
                return ResponseController::sendError(64); // Email tidak terdaftar
            }

            // Generate 6 digit OTP
            $otp = sprintf('%06d', rand(0, 999999));
            
            // Update user with OTP code
            $user->otp_code = $otp;
            $user->save();

            // Send email with OTP
            GlobalHelperController::sendMailVerification( $email, $user->nama, $otp);

            return ResponseController::sendSuccess([
                'message' => 'Kode OTP telah dikirim ke email Anda'
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Verify OTP Code
    public function verifyOTP(Request $request){
        $email = $request->email;
        $otp_code = $request->otp_code;

        if (empty($email) || empty($otp_code)) {
            return ResponseController::sendError(98); // Parameter tidak lengkap
        }

        try {
            // Find user by email and OTP
            $user = User::where('email', $email)
                       ->where('otp_code', $otp_code)
                       ->where('status', 1)
                       ->first();

            if (!$user) {
                return ResponseController::sendError(65); // Kode OTP tidak valid atau sudah expired
            }

            return ResponseController::sendSuccess([
                'message' => 'Kode OTP valid',
                'data' => [
                    'email' => $email,
                    'verified' => true
                ]
            ]);

        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Reset Password with OTP
    public function resetPasswordWithOTP(Request $request){
        $email = $request->email;
        $otp_code = $request->otp_code;
        $new_password = $request->new_password;

        if (empty($email) || empty($otp_code) || empty($new_password)) {
            return ResponseController::sendError(98); // Parameter tidak lengkap
        }

        // Validate password minimum length
        if (strlen($new_password) < 6) {
            return ResponseController::sendError(66); // Password minimal 6 karakter
        }

        try {
            DB::beginTransaction();

            // Find user by email and OTP
            $user = User::where('email', $email)
                       ->where('otp_code', $otp_code)
                       ->where('status', 1)
                       ->first();

            if (!$user) {
                DB::rollback();
                return ResponseController::sendError(65); // Kode OTP tidak valid atau sudah expired
            }

            // Update password and clear OTP
            $user->password = Hash::make($new_password);
            $user->otp_code = null; // Clear OTP after successful reset
            $user->save();

            DB::commit();

            // Send confirmation email
            // $this->sendPasswordResetConfirmationEmail($email, $user->nama);

            return ResponseController::sendSuccess([
                'message' => 'Password berhasil direset'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // Send OTP Email
    private function sendOTPEmail($email, $nama, $otp){
        try {
            $subject = "Kode OTP Reset Password - PERBASI";
            $message = "
                <html>
                <head>
                    <title>Reset Password OTP</title>
                </head>
                <body>
                    <h2>Reset Password - PERBASI</h2>
                    <p>Halo $nama,</p>
                    <p>Kami menerima permintaan untuk reset password akun Anda.</p>
                    <p>Gunakan kode OTP berikut untuk melanjutkan proses reset password:</p>
                    <h3 style='color: #007bff; font-size: 24px; letter-spacing: 2px;'>$otp</h3>
                    <p><strong>Kode OTP ini berlaku selama 15 menit.</strong></p>
                    <p>Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.</p>
                    <br>
                    <p>Terima kasih,</p>
                    <p><strong>Tim PERBASI</strong></p>
                </body>
                </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@perbasi.com" . "\r\n";

            mail($email, $subject, $message, $headers);

        } catch (\Exception $e) {
            // Log error but don't fail the main process
            error_log("Failed to send OTP email: " . $e->getMessage());
        }
    }

    // Send Password Reset Confirmation Email
    private function sendPasswordResetConfirmationEmail($email, $nama){
        try {
            $subject = "Password Berhasil Direset - PERBASI";
            $message = "
                <html>
                <head>
                    <title>Password Reset Confirmation</title>
                </head>
                <body>
                    <h2>Password Berhasil Direset - PERBASI</h2>
                    <p>Halo $nama,</p>
                    <p>Password akun Anda telah berhasil direset pada " . date('d/m/Y H:i:s') . ".</p>
                    <p>Jika Anda tidak melakukan perubahan ini, segera hubungi administrator.</p>
                    <p>Untuk keamanan akun Anda, pastikan untuk:</p>
                    <ul>
                        <li>Menggunakan password yang kuat</li>
                        <li>Tidak membagikan password kepada siapapun</li>
                        <li>Logout dari perangkat yang tidak dikenal</li>
                    </ul>
                    <br>
                    <p>Terima kasih,</p>
                    <p><strong>Tim PERBASI</strong></p>
                </body>
                </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@perbasi.com" . "\r\n";

            mail($email, $subject, $message, $headers);

        } catch (\Exception $e) {
            // Log error but don't fail the main process
            error_log("Failed to send confirmation email: " . $e->getMessage());
        }
    }

    public function uploadImageProfil(Request $request){
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $request->validate([
            'uploaded_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $filename = 'noname';

        if($request->filename){
            $filename = $request->filename;
        }

        $file = $request->file('uploaded_file');
        $file->move('profil', $filename);

        return ResponseController::sendSuccess([
            'message' => 'success',
        ]); 
    }

    public function testNotificationSingle(Request $request){
        $fcm_token        = $request->fcm_token;

        GlobalHelperController::sendNotif([$fcm_token], "Perbasi", "test notifikasi");
        
        return ResponseController::sendSuccess([
            'message' => 'Your request has been sent, please check your history tab'
        ]); 
    }

    public function testSendOTPMail(Request $request){
        
        $otp_code = "12345";

        GlobalHelperController::sendMailVerification( $request->email, $request->nama, $otp_code);
        
        return ResponseController::sendSuccess([
            'message' => 'OTP Code has been sent, please check your email box'
        ]); 
    }

}
