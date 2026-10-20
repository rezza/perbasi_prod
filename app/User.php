<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    protected $table = 'm_user';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
    	'id', 'email', 'password', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'no_hp',
        'no_darurat', 'hubungan_nodarurat', 'pekerjaan', 'nik', 'no_pelajar', 'no_kk', 'filename_ktp', 'filename_ttd', 
        'filename_foto', 'posisi', 'status', 'fcm_token', 'os_type', 'os_version', 'no_anggota', 'otp_code', 'tgl_expired',
        'id_parents', 'tipe_akun'
    ];
    public $imbuhan = 'U53R';

    function getUsers($keyword, $posisi = null){
        $filter = "p.status = 1 AND p.tipe_akun <> 'ortu'";
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (p.nama like ? OR p.no_hp like ? OR p.email like ?)';
            $param = [$keyword, $keyword, $keyword];
        }

        if(!empty($posisi)){
            $filter .= ' AND p.posisi = ?';
            $param[] = $posisi;
        }

        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.email,
                p.nama,
                p.tempat_lahir,
                p.tanggal_lahir,
                p.jenis_kelamin,
                p.alamat,
                p.no_hp,
                p.no_darurat,
                p.hubungan_nodarurat,
                p.pekerjaan,
                p.nik,
                p.no_pelajar,
                p.no_kk,
                p.filename_ktp,
                p.filename_ttd,
                p.filename_foto,
                p.posisi,
                p.status
            FROM m_user p
            WHERE '.$filter.'
            ORDER BY p.nama ASC
            ',$param);
    }

    /**
     * FCM tokens that should receive a notification for $user: the user's own
     * token plus the parent's token when the user is a managed child.
     * $user is a row/model with id, id_parents and fcm_token.
     */
    public static function notifTokens($user){
        $tokens = [];
        if (!empty($user->fcm_token)) $tokens[] = $user->fcm_token;
        if (!empty($user->id_parents)) {
            $parentToken = DB::table('m_user')->where('id', $user->id_parents)->where('status', 1)->value('fcm_token');
            if (!empty($parentToken)) $tokens[] = $parentToken;
        }
        return array_values(array_unique($tokens));
    }

    /** Prefix the child's name on notifications that also go to the parent. */
    public static function notifTitle($user, $title){
        return !empty($user->id_parents) ? '['.$user->nama.'] '.$title : $title;
    }

    /**
     * Parents-mode info for admin screens: whether the account is a child managed
     * by a parent (has a parent and no credentials of its own) and who the parent is.
     */
    public static function parentInfo($userId){
        $row = DB::table('m_user as c')
            ->leftJoin('m_user as p', 'p.id', '=', 'c.id_parents')
            ->where('c.id', $userId)
            ->first(['c.id_parents', 'c.email', 'c.password', 'p.nama as nama_ortu', 'p.email as email_ortu']);
        $managed = $row && !empty($row->id_parents) && empty($row->email) && empty($row->password);
        return [
            'dikelola_ortu' => $managed ? 1 : 0,
            'nama_ortu' => $managed ? $row->nama_ortu : null,
            'email_ortu' => $managed ? $row->email_ortu : null,
        ];
    }

    function getUserWithAdminInfo($email){
        return  DB::select(
                'SELECT 
                    u.id,
                    u.nama,
                    u.email,
                    u.no_hp,
                    a.id_role,
                    a.id_perkumpulan,
                    CASE 
                        WHEN a.id_role = 1 THEN "Master Admin"
                        WHEN a.id_role = 2 THEN "Admin Perkumpulan"
                        ELSE "User"
                    END as nama_role,
                    p.no_perkumpulan,
                    p.nama as nama_perkumpulan
                FROM m_user u
                LEFT JOIN m_admin a ON u.id = a.id_user AND a.status = 1
                LEFT JOIN m_perkumpulan p ON a.id_perkumpulan = p.id
                WHERE u.email LIKE ? AND u.status = 1 AND u.tipe_akun <> "ortu"
                ORDER BY u.nama ASC
                ',['%'.$email.'%']
            );
    }


    function getUserByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                p.id,
                p.email,
                p.nama,
                p.tempat_lahir,
                p.tanggal_lahir,
                p.jenis_kelamin,
                p.alamat,
                p.no_hp,
                p.no_darurat,
                p.hubungan_nodarurat,
                p.pekerjaan,
                p.nik,
                p.no_pelajar,
                p.no_kk,
                p.filename_ktp,
                p.filename_ttd,
                p.filename_foto,
                p.posisi,
                p.status,
                p.fcm_token,
                p.os_type,
                p.os_version
            FROM m_user p
            WHERE md5(concat(p.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getLoginData($id) {
        $data = DB::select(
            'SELECT 
                md5(CONCAT(a.id, "'.$this->imbuhan.'")) as id,
                a.email,
                a.nama,
                a.tempat_lahir,
                a.tanggal_lahir,
                a.jenis_kelamin,
                a.alamat,
                a.no_hp,
                a.no_darurat,
                a.hubungan_nodarurat,
                a.pekerjaan,
                a.nik,
                a.no_pelajar,
                a.no_kk,
                a.filename_ktp,
                a.filename_ttd,
                a.filename_foto,
                a.posisi,
                a.status,
                a.no_anggota,
                a.tgl_expired,
                a.tipe_akun,
                (a.id_parents IS NOT NULL AND a.email IS NULL AND a.password IS NULL) AS dikelola_ortu
            FROM m_user a
            WHERE a.id = ?
            LIMIT 1',
            [$id]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }


    function getAdminPerkumpulanFCMTokens($id_perkumpulan){
        $tokens = DB::select(
            'SELECT 
                u.fcm_token
            FROM m_admin a
            JOIN m_user u ON a.id_user = u.id AND u.status = 1 AND a.id_role = 2
            WHERE a.id_perkumpulan = ? AND a.status = 1 AND u.fcm_token IS NOT NULL AND u.fcm_token <> ""
            ',[$id_perkumpulan]
        );  

        $data = [];
        foreach($tokens as $fcm){
            $data[] = $fcm->fcm_token;
        }

        return $data;
    }

    function getAdminMasterFCMTokens(){
        $tokens = DB::select(
            'SELECT 
                u.fcm_token
            FROM m_admin a
            JOIN m_user u ON a.id_user = u.id AND u.status = 1 AND a.id_role = 1
            WHERE a.status = 1 AND u.fcm_token IS NOT NULL AND u.fcm_token <> ""
            ',[]
        );  

        $data = [];
        foreach($tokens as $fcm){
            $data[] = $fcm->fcm_token;
        }
        return $data;
    }

    /**
     * Generate No Anggota
     * Format: <full_code kota/kabupaten> <tahun create data> <nomor urut dengan length 4>
     * Nomor urut akan restart dari 0001 kalau system date nya awal tahun
     * 
     * @param int $id_kota ID kota/kabupaten
     * @return string Nomor anggota yang sudah digenerate
     */
    function generateNoAnggota($id_kota){
        // Get kota full_code
        $kota = DB::table('m_kota')->where('id', $id_kota)->first();
        if (!$kota) {
            throw new \Exception('Kota tidak ditemukan');
        }
        
        $full_code = $kota->full_code;
        $tahun = date('Y');
        
        // Get last no_anggota for this kota and year
        $lastUser = DB::table('m_user')
            ->where('no_anggota', 'LIKE', $full_code . $tahun . '%')
            ->orderBy('no_anggota', 'DESC')
            ->first();
        
        if ($lastUser && !empty($lastUser->no_anggota)) {
            // Extract the sequence number from the last no_anggota
            // Format: FULLCODE + YEAR + SEQUENCE (4 digits)
            $lastNoAnggota = $lastUser->no_anggota;
            $prefixLength = strlen($full_code . $tahun);
            $lastSequence = (int) substr($lastNoAnggota, $prefixLength);
            $newSequence = $lastSequence + 1;
        } else {
            // First user for this kota and year
            $newSequence = 1;
        }
        
        // Format sequence to 4 digits with leading zeros
        $sequenceFormatted = str_pad($newSequence, 4, '0', STR_PAD_LEFT);
        
        // Generate final no_anggota
        $no_anggota = $full_code . $tahun . $sequenceFormatted;
        
        return $no_anggota;
    }
}
