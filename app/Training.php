<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Training extends Model
{
    protected $table = 'm_training';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pengajuan', 'tahun', 'nama', 'lokasi', 'filename'
    ];
    public $imbuhan = 'TR41N1NG';

    function getTrainings($keyword = null, $tahun = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (t.nama like ? OR t.lokasi like ?)';
            $param = [$keyword, $keyword];
        }

        if(!empty($tahun)){
            $filter .= ' AND t.tahun = ?';
            $param[] = $tahun;
        }

        return DB::select(
            'SELECT 
                md5(concat(t.id, "'.$this->imbuhan.'")) AS id,
                t.id_pengajuan,
                t.tahun,
                t.nama,
                t.lokasi,
                t.filename
            FROM m_training t
            WHERE '.$filter.'
            ORDER BY t.tahun DESC, t.nama ASC
            ',$param);
    }

    function getTrainingByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                t.id,
                t.id_pengajuan,
                t.tahun,
                t.nama,
                t.lokasi,
                t.filename
            FROM m_training t
            WHERE md5(concat(t.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getTrainingsByPengajuan($id_pengajuan){
        return DB::select(
            'SELECT 
                md5(concat(t.id, "'.$this->imbuhan.'")) AS id,
                t.id_pengajuan,
                t.tahun,
                t.nama,
                t.lokasi,
                t.filename
            FROM m_training t
            WHERE t.id_pengajuan = ?
            ORDER BY t.tahun DESC
            ',[$id_pengajuan]);
    }
}
