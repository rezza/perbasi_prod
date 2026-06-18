<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PengajuanNonAnggota extends Model
{
    protected $table = 'm_pengajuan_nonaggota';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'id_perkumpulan', 'tahun', 'id_posisi', 'jabatan', 
        'tgl_pengajuan', 'tgl_apv_perkumpulan', 'tgl_apv_admin', 
        'id_apr_perkumpulan', 'id_apr_admin', 'reason', 'status',
        'status_aktif', 'tgl_expired', 'no_anggota'
    ];
    public $imbuhan = 'P3NG4JU4NN0N4NGG';

    function getPengajuanNonAnggotas($keyword = null, $tahun = null, $id_perkumpulan = null, $status = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (pn.jabatan like ? OR pos.nama like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND pn.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_perkumpulan)){
            $filter .= ' AND pn.id_perkumpulan = ?';
            $param[] = $id_perkumpulan;
        }

        if($status !== null && $status !== ''){
            $filter .= ' AND pn.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT 
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.id_perkumpulan,
                pn.tahun,
                pn.id_posisi,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.tgl_apv_perkumpulan,
                pn.tgl_apv_admin,
                pn.id_apr_perkumpulan,
                pn.id_apr_admin,
                pn.reason,
                pn.status,
                pk.nama as nama_perkumpulan,
                pos.nama as nama_posisi,
                apr_pk.username as username_apr_perkumpulan,
                apr_adm.username as username_apr_admin,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
            LEFT JOIN m_admin apr_pk ON pn.id_apr_perkumpulan = apr_pk.id
            LEFT JOIN m_admin apr_adm ON pn.id_apr_admin = apr_adm.id
            WHERE '.$filter.'
            ORDER BY pn.tgl_pengajuan DESC
            ',$param);
    }

    function getPengajuanNonAnggotaByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                pn.id,
                pn.id_user,
                pn.id_perkumpulan,
                pn.tahun,
                pn.id_posisi,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.tgl_apv_perkumpulan,
                pn.tgl_apv_admin,
                pn.id_apr_perkumpulan,
                pn.id_apr_admin,
                pn.reason,
                pn.status,
                pk.nama as nama_perkumpulan,
                pos.nama as nama_posisi,
                apr_pk.username as username_apr_perkumpulan,
                apr_adm.username as username_apr_admin,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
            LEFT JOIN m_admin apr_pk ON pn.id_apr_perkumpulan = apr_pk.id
            LEFT JOIN m_admin apr_adm ON pn.id_apr_admin = apr_adm.id
            WHERE md5(concat(pn.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPengajuanNonAnggotasByPerkumpulan($id_perkumpulan, $status = null){
        $filter = 'pn.id_perkumpulan = ?';
        $param = [$id_perkumpulan];

        if($status !== null && $status !== ''){
            $filter .= ' AND pn.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT 
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.tahun,
                pn.id_posisi,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.tgl_apv_perkumpulan,
                pn.tgl_apv_admin,
                pn.status,
                pos.nama as nama_posisi,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
            WHERE '.$filter.'
            ORDER BY pn.tgl_pengajuan DESC
            ',$param);
    }

    function getPengajuanNonAnggotasWaiting(){
        return DB::select(
            'SELECT 
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.tahun,
                pn.jabatan,
                pn.tgl_pengajuan,
                pk.nama as nama_perkumpulan,
                pos.nama as nama_posisi,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            LEFT JOIN m_posisi pos ON pn.id_posisi = pos.id
            WHERE pn.status = 0
            ORDER BY pn.tgl_pengajuan ASC
            ');
    }

    function getPengajuanNonAnggotasByPosisi($id_posisi, $status = null){
        $filter = 'pn.id_posisi = ?';
        $param = [$id_posisi];

        if($status !== null && $status !== ''){
            $filter .= ' AND pn.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT 
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.id_perkumpulan,
                pn.tahun,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.status,
                pk.nama as nama_perkumpulan,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            WHERE '.$filter.'
            ORDER BY pn.tgl_pengajuan DESC
            ',$param);
    }

    function getApprovedNonAnggota($id_perkumpulan){
        return DB::select(
            'SELECT
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.tahun,
                pn.id_posisi,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired,
                pn.status,
                u.nama,
                u.filename_foto,
                pos.nama AS nama_posisi,
                CASE pn.status
                    WHEN  2 THEN "Disetujui"
                    WHEN  1 THEN "Disetujui Perkumpulan"
                    WHEN  0 THEN "Menunggu Persetujuan"
                    WHEN -1 THEN "Ditolak Perkumpulan"
                    WHEN -2 THEN "Ditolak Master Admin"
                    ELSE "Tidak Diketahui"
                END AS status_label
            FROM m_pengajuan_nonaggota pn
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_nonaggota WHERE status = 2 GROUP BY id_user
            ) latest ON pn.id_user = latest.id_user
                    AND pn.tahun   = latest.last_tahun
                    AND pn.id      = latest.last_id
            LEFT JOIN m_user u     ON pn.id_user   = u.id
            LEFT JOIN m_posisi pos ON pn.id_posisi  = pos.id
            WHERE pn.id_perkumpulan = ? AND u.status = 1
            ORDER BY u.nama ASC
            ',[$id_perkumpulan]);
    }



    private function buildDaftarSdmWhere($keyword = null, $jenis_kelamin = null, $posisi = null, $id_perkumpulan = null): array
    {
        $where  = 'u.status = 1';
        $param  = [];

        if (!empty($keyword)) {
            $kw     = '%' . $keyword . '%';
            $where .= ' AND (u.nama LIKE ? OR pos.nama LIKE ? OR pk.nama LIKE ?)';
            $param[] = $kw;
            $param[] = $kw;
            $param[] = $kw;
        }

        if ($jenis_kelamin !== null && $jenis_kelamin !== '') {
            $where .= ' AND u.jenis_kelamin = ?';
            $param[] = $jenis_kelamin;
        }

        if (!empty($posisi)) {
            $where .= ' AND pos.nama = ?';
            $param[] = $posisi;
        }

        if (!empty($id_perkumpulan)) {
            $where .= ' AND pn.id_perkumpulan = ?';
            $param[] = $id_perkumpulan;
        }

        return [$where, $param];
    }

    function countDaftarSdmPublik($keyword = null, $jenis_kelamin = null, $posisi = null, $id_perkumpulan = null): int
    {
        [$where, $param] = $this->buildDaftarSdmWhere($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);

        $result = DB::select(
            'SELECT COUNT(*) AS total
            FROM m_pengajuan_nonaggota pn
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_nonaggota WHERE status NOT IN (-1, -2) GROUP BY id_user
            ) latest ON pn.id_user = latest.id_user
                    AND pn.tahun   = latest.last_tahun
                    AND pn.id      = latest.last_id
            LEFT JOIN m_user u         ON pn.id_user        = u.id
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            LEFT JOIN m_posisi pos     ON pn.id_posisi       = pos.id
            WHERE ' . $where, $param);

        return (int)($result[0]->total ?? 0);
    }

    function getDaftarSdmPublik($keyword = null, $jenis_kelamin = null, $posisi = null, $id_perkumpulan = null, $page = null, $perPage = 12)
    {
        [$where, $param] = $this->buildDaftarSdmWhere($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);

        $sql =
            'SELECT
                u.nama,
                u.jenis_kelamin,
                u.filename_foto,
                pos.nama AS posisi,
                pn.no_anggota,
                pn.tahun,
                pn.status,
                pn.id_perkumpulan,
                pk.nama AS nama_perkumpulan,
                CASE pn.status
                    WHEN  2 THEN "Disetujui"
                    WHEN  1 THEN "Disetujui Perkumpulan"
                    WHEN  0 THEN "Menunggu Persetujuan"
                    WHEN -1 THEN "Ditolak Perkumpulan"
                    WHEN -2 THEN "Ditolak Master Admin"
                    ELSE "Tidak Diketahui"
                END AS status_label
            FROM m_pengajuan_nonaggota pn
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_nonaggota WHERE status NOT IN (-1, -2) GROUP BY id_user
            ) latest ON pn.id_user = latest.id_user
                    AND pn.tahun   = latest.last_tahun
                    AND pn.id      = latest.last_id
            LEFT JOIN m_user u         ON pn.id_user        = u.id
            LEFT JOIN m_perkumpulan pk ON pn.id_perkumpulan = pk.id
            LEFT JOIN m_posisi pos     ON pn.id_posisi       = pos.id
            WHERE ' . $where . '
            ORDER BY u.nama ASC';

        if ($page !== null) {
            $sql    .= ' LIMIT ? OFFSET ?';
            $param[] = (int)$perPage;
            $param[] = ((int)$page - 1) * (int)$perPage;
        }

        return DB::select($sql, $param);
    }

    function getApprovedNonAnggotaByIdPosisi($id_posisi){
        return DB::select(
            'SELECT 
                md5(concat(pn.id, "'.$this->imbuhan.'")) AS id,
                pn.tahun,
                pn.id_posisi,
                pn.jabatan,
                pn.tgl_pengajuan,
                pn.status_aktif,
                pn.no_anggota,
                pn.tgl_expired
            FROM m_pengajuan_nonaggota pn
            WHERE pn.id_posisi = ? AND pn.status = 1
            ORDER BY pn.tgl_pengajuan DESC
            ',[$id_posisi]);
    }
}
