<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestasiSDM extends Model
{
    protected $table = 'm_prestasi_sdm';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pengajuan', 'tahun', 'prestasi'
    ];
    public $imbuhan = 'PR3ST4S15DM';

    function getPrestasiSDMs($keyword = null, $tahun = null, $id_pengajuan = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND ps.prestasi like ?';
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND ps.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_pengajuan)){
            $filter .= ' AND ps.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(ps.id, "'.$this->imbuhan.'")) AS id,
                ps.id_pengajuan,
                ps.tahun,
                ps.prestasi
            FROM m_prestasi_sdm ps
            WHERE '.$filter.'
            ORDER BY ps.tahun DESC, ps.prestasi ASC
            ',$param);
    }

    function getPrestasiSDMByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                ps.id,
                ps.id_pengajuan,
                ps.tahun,
                ps.prestasi
            FROM m_prestasi_sdm ps
            WHERE md5(concat(ps.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPrestasiSDMByPengajuan($id_pengajuan){
        return DB::select(
            'SELECT 
                md5(concat(ps.id, "'.$this->imbuhan.'")) AS id,
                ps.id_pengajuan,
                ps.tahun,
                ps.prestasi
            FROM m_prestasi_sdm ps
            WHERE ps.id_pengajuan = ?
            ORDER BY ps.tahun DESC
            ',[$id_pengajuan]);
    }

    function getPrestasiSDMByTahun($tahun){
        return DB::select(
            'SELECT 
                md5(concat(ps.id, "'.$this->imbuhan.'")) AS id,
                ps.id_pengajuan,
                ps.tahun,
                ps.prestasi
            FROM m_prestasi_sdm ps
            WHERE ps.tahun = ?
            ORDER BY ps.prestasi ASC
            ',[$tahun]);
    }
}
