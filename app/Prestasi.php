<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Prestasi extends Model
{
    protected $table = 'm_prestasi';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pengajuan', 'tahun', 'kompetisi', 'prestasi'
    ];
    public $imbuhan = 'PR3ST4SI';

    function getPrestasis($keyword = null, $tahun = null, $id_pengajuan = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (p.kompetisi like ? OR p.prestasi like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND p.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_pengajuan)){
            $filter .= ' AND p.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_pengajuan,
                p.tahun,
                p.kompetisi,
                p.prestasi
            FROM m_prestasi p
            WHERE '.$filter.'
            ORDER BY p.tahun DESC, p.kompetisi ASC
            ',$param);
    }

    function getPrestasiByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                p.id,
                p.id_pengajuan,
                p.tahun,
                p.kompetisi,
                p.prestasi
            FROM m_prestasi p
            WHERE md5(concat(p.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPrestasiByPengajuan($id_pengajuan){
        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_pengajuan,
                p.tahun,
                p.kompetisi,
                p.prestasi
            FROM m_prestasi p
            WHERE p.id_pengajuan = ?
            ORDER BY p.tahun DESC
            ',[$id_pengajuan]);
    }

    function getPrestasiByTahun($tahun){
        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_pengajuan,
                p.tahun,
                p.kompetisi,
                p.prestasi
            FROM m_prestasi p
            WHERE p.tahun = ?
            ORDER BY p.kompetisi ASC
            ',[$tahun]);
    }

    function getPrestasiByKompetisi($kompetisi){
        $kompetisi = '%'. $kompetisi . '%';
        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_pengajuan,
                p.tahun,
                p.kompetisi,
                p.prestasi
            FROM m_prestasi p
            WHERE p.kompetisi like ?
            ORDER BY p.tahun DESC
            ',[$kompetisi]);
    }
}
