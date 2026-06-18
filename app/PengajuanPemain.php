<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PengajuanPemain extends Model
{
    protected $table = 'm_pengajuan_pemain';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'tahun', 'id_kota', 'id_perkumpulan', 'id_posisi', 'posisi', 'no_anggota', 
        'no_punggung', 'tinggi', 'berat', 'penyakit', 'operasi', 
        'tgl_pengajuan', 'tgl_apv_perkumpulan', 'tgl_apv_admin', 
        'id_apr_perkumpulan', 'id_apr_admin', 'reason', 'tgl_expired', 'status',
        'status_aktif'
    ];
    public $imbuhan = 'P3NG4JU4NPM4IN';

    function getPengajuanPemains($keyword = null, $tahun = null, $id_perkumpulan = null, $status = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (pp.no_anggota like ? OR pp.posisi like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND pp.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_perkumpulan)){
            $filter .= ' AND pp.id_perkumpulan = ?';
            $param[] = $id_perkumpulan;
        }

        if($status !== null && $status !== ''){
            $filter .= ' AND pp.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT 
                md5(concat(pp.id, "'.$this->imbuhan.'")) AS id,
                pp.tahun,
                pp.id_kota,
                pp.id_perkumpulan,
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
                pp.id_apr_perkumpulan,
                pp.id_apr_admin,
                pp.reason,
                pp.tgl_expired,
                pp.status,
                k.name as nama_kota,
                pk.nama as nama_perkumpulan,
                apr_pk.username as username_apr_perkumpulan,
                apr_adm.username as username_apr_admin,
                pp.status_aktif
            FROM m_pengajuan_pemain pp
            LEFT JOIN m_kota k ON pp.id_kota = k.id
            LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
            LEFT JOIN m_admin apr_pk ON pp.id_apr_perkumpulan = apr_pk.id
            LEFT JOIN m_admin apr_adm ON pp.id_apr_admin = apr_adm.id
            WHERE '.$filter.'
            ORDER BY pp.tgl_pengajuan DESC
            ',$param);
    }

    function getPengajuanPemainByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                pp.id,
                pp.id_user,
                pp.tahun,
                pp.id_kota,
                pp.id_perkumpulan,
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
                pp.id_apr_perkumpulan,
                pp.id_apr_admin,
                pp.reason,
                pp.tgl_expired,
                pp.status,
                k.name as nama_kota,
                pk.nama as nama_perkumpulan,
                apr_pk.username as username_apr_perkumpulan,
                apr_adm.username as username_apr_admin,
                pp.status_aktif
            FROM m_pengajuan_pemain pp
            LEFT JOIN m_kota k ON pp.id_kota = k.id
            LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
            LEFT JOIN m_admin apr_pk ON pp.id_apr_perkumpulan = apr_pk.id
            LEFT JOIN m_admin apr_adm ON pp.id_apr_admin = apr_adm.id
            WHERE md5(concat(pp.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPengajuanPemainsByPerkumpulan($id_perkumpulan, $status = null){
        $filter = 'pp.id_perkumpulan = ?';
        $param = [$id_perkumpulan];

        if($status !== null && $status !== ''){
            $filter .= ' AND pp.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT 
                md5(concat(pp.id, "'.$this->imbuhan.'")) AS id,
                pp.tahun,
                pp.posisi,
                pp.no_anggota,
                pp.no_punggung,
                pp.tgl_pengajuan,
                pp.tgl_apv_perkumpulan,
                pp.tgl_apv_admin,
                pp.tgl_expired,
                pp.status,
                pp.status_aktif
            FROM m_pengajuan_pemain pp
            WHERE '.$filter.'
            ORDER BY pp.tgl_pengajuan DESC
            ',$param);
    }

    function getPengajuanPemainsWaiting(){
        return DB::select(
            'SELECT 
                md5(concat(pp.id, "'.$this->imbuhan.'")) AS id,
                pp.tahun,
                pp.posisi,
                pp.no_anggota,
                pp.tgl_pengajuan,
                pk.nama as nama_perkumpulan,
                k.name as nama_kota,
                pp.status_aktif
            FROM m_pengajuan_pemain pp
            LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
            LEFT JOIN m_kota k ON pp.id_kota = k.id
            WHERE pp.status = 0
            ORDER BY pp.tgl_pengajuan ASC
            ');
    }

    private function buildDaftarPemainWhere($keyword, $jenis_kelamin, $posisi, $id_perkumpulan): array
    {
        $where = 'u.status = 1';
        $param = [];

        if (!empty($keyword)) {
            $kw = '%' . $keyword . '%';
            $where .= ' AND (u.nama LIKE ? OR pp.posisi LIKE ? OR pk.nama LIKE ?)';
            $param[] = $kw; $param[] = $kw; $param[] = $kw;
        }
        if ($jenis_kelamin !== null && $jenis_kelamin !== '') {
            $where .= ' AND u.jenis_kelamin = ?';
            $param[] = $jenis_kelamin;
        }
        if ($posisi !== null && $posisi !== '') {
            $where .= ' AND pp.posisi = ?';
            $param[] = $posisi;
        }
        if ($id_perkumpulan !== null && $id_perkumpulan !== '') {
            $where .= ' AND pp.id_perkumpulan = ?';
            $param[] = $id_perkumpulan;
        }

        return [$where, $param];
    }

    function countDaftarPemainPublik($keyword = null, $jenis_kelamin = null, $posisi = null, $id_perkumpulan = null): int
    {
        [$where, $param] = $this->buildDaftarPemainWhere($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);

        $result = DB::select(
            'SELECT COUNT(*) AS total
            FROM m_pengajuan_pemain pp
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_pemain GROUP BY id_user
            ) latest ON pp.id_user = latest.id_user
                    AND pp.tahun   = latest.last_tahun
                    AND pp.id      = latest.last_id
            LEFT JOIN m_user u         ON pp.id_user       = u.id
            LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
            WHERE ' . $where, $param);

        return (int)($result[0]->total ?? 0);
    }

    function getDaftarPemainPublik($keyword = null, $jenis_kelamin = null, $posisi = null, $id_perkumpulan = null, $page = null, $perPage = 12)
    {
        [$where, $param] = $this->buildDaftarPemainWhere($keyword, $jenis_kelamin, $posisi, $id_perkumpulan);

        $sql =
            'SELECT
                u.nama,
                u.jenis_kelamin,
                u.filename_foto,
                pp.posisi,
                pp.no_anggota,
                pp.tahun,
                pp.status,
                pp.id_perkumpulan,
                pk.nama AS nama_perkumpulan,
                CASE pp.status
                    WHEN  2 THEN "Disetujui"
                    WHEN  1 THEN "Disetujui Perkumpulan"
                    WHEN  0 THEN "Menunggu Persetujuan"
                    WHEN -1 THEN "Ditolak Perkumpulan"
                    WHEN -2 THEN "Ditolak Master Admin"
                    ELSE "Tidak Diketahui"
                END AS status_label
            FROM m_pengajuan_pemain pp
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_pemain GROUP BY id_user
            ) latest ON pp.id_user = latest.id_user
                    AND pp.tahun   = latest.last_tahun
                    AND pp.id      = latest.last_id
            LEFT JOIN m_user u         ON pp.id_user       = u.id
            LEFT JOIN m_perkumpulan pk ON pp.id_perkumpulan = pk.id
            WHERE ' . $where . '
            ORDER BY u.nama ASC';

        if ($page !== null) {
            $sql .= ' LIMIT ? OFFSET ?';
            $param[] = (int)$perPage;
            $param[] = ((int)$page - 1) * (int)$perPage;
        }

        return DB::select($sql, $param);
    }

    function getApprovedPemain($id_perkumpulan){
        return DB::select(
            'SELECT
                md5(concat(u.id, "U53R")) AS encrypted_id_user,
                u.nama,
                u.email,
                u.no_hp,
                u.filename_foto,
                pp.posisi,
                u.no_anggota,
                pp.tahun,
                pp.status,
                pp.tgl_pengajuan,
                pp.status_aktif,
                CASE pp.status
                    WHEN  2 THEN "Disetujui"
                    WHEN  1 THEN "Disetujui Perkumpulan"
                    WHEN  0 THEN "Menunggu Persetujuan"
                    WHEN -1 THEN "Ditolak Perkumpulan"
                    WHEN -2 THEN "Ditolak Master Admin"
                    ELSE "Tidak Diketahui"
                END AS status_label
            FROM m_pengajuan_pemain pp
            INNER JOIN (
                SELECT id_user, MAX(tahun) AS last_tahun, MAX(id) AS last_id
                FROM m_pengajuan_pemain where status = 2 GROUP BY id_user
            ) latest ON pp.id_user = latest.id_user
                    AND pp.tahun   = latest.last_tahun
                    AND pp.id      = latest.last_id
            LEFT JOIN m_user u ON pp.id_user = u.id
            WHERE pp.id_perkumpulan = ? AND u.status = 1
            ORDER BY u.nama ASC
            ', [$id_perkumpulan]
        );
    }
}
