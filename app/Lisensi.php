<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Lisensi extends Model
{
    protected $table = 'm_lisensi';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pengajuan', 'tahun', 'nama', 'tanggal_mulai', 
        'tanggal_expired', 'filename'
    ];
    public $imbuhan = 'L1S3N51';

    function getLisensi($keyword = null, $tahun = null, $id_pengajuan = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND l.nama like ?';
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND l.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_pengajuan)){
            $filter .= ' AND l.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(l.id, "'.$this->imbuhan.'")) AS id,
                l.id_pengajuan,
                l.tahun,
                l.nama,
                l.tanggal_mulai,
                l.tanggal_expired,
                l.filename
            FROM m_lisensi l
            WHERE '.$filter.'
            ORDER BY l.tanggal_expired DESC, l.tahun DESC
            ',$param);
    }

    function getLisensiByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                l.id,
                l.id_pengajuan,
                l.tahun,
                l.nama,
                l.tanggal_mulai,
                l.tanggal_expired,
                l.filename
            FROM m_lisensi l
            WHERE md5(concat(l.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getLisensiByPengajuan($id_pengajuan){
        return DB::select(
            'SELECT 
                md5(concat(l.id, "'.$this->imbuhan.'")) AS id,
                l.id_pengajuan,
                l.tahun,
                l.nama,
                l.tanggal_mulai,
                l.tanggal_expired,
                l.filename
            FROM m_lisensi l
            WHERE l.id_pengajuan = ?
            ORDER BY l.tanggal_expired DESC
            ',[$id_pengajuan]);
    }

    function getActiveLisensi($id_pengajuan = null){
        $filter = 'l.tanggal_expired >= CURDATE()';
        $param = [];

        if(!empty($id_pengajuan)){
            $filter .= ' AND l.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(l.id, "'.$this->imbuhan.'")) AS id,
                l.id_pengajuan,
                l.tahun,
                l.nama,
                l.tanggal_mulai,
                l.tanggal_expired,
                l.filename
            FROM m_lisensi l
            WHERE '.$filter.'
            ORDER BY l.tanggal_expired ASC
            ',$param);
    }

    function getExpiredLisensi($id_pengajuan = null){
        $filter = 'l.tanggal_expired < CURDATE()';
        $param = [];

        if(!empty($id_pengajuan)){
            $filter .= ' AND l.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(l.id, "'.$this->imbuhan.'")) AS id,
                l.id_pengajuan,
                l.tahun,
                l.nama,
                l.tanggal_mulai,
                l.tanggal_expired,
                l.filename
            FROM m_lisensi l
            WHERE '.$filter.'
            ORDER BY l.tanggal_expired DESC
            ',$param);
    }

    function getLisensiExpiringSoon($days = 30){
        return DB::select(
            'SELECT 
                md5(concat(l.id, "'.$this->imbuhan.'")) AS id,
                l.id_pengajuan,
                l.nama,
                l.tanggal_expired,
                DATEDIFF(l.tanggal_expired, CURDATE()) as days_remaining
            FROM m_lisensi l
            WHERE l.tanggal_expired >= CURDATE() 
            AND l.tanggal_expired <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY l.tanggal_expired ASC
            ',[$days]);
    }
}
