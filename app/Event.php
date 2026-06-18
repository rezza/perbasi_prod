<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Event extends Model
{
    protected $table = 'm_event';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pengajuan', 'tahun', 'nama', 'prestasi'
    ];
    public $imbuhan = '3V3NT';

    function getEvents($keyword = null, $tahun = null, $id_pengajuan = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (e.nama like ? OR e.prestasi like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($tahun)){
            $filter .= ' AND e.tahun = ?';
            $param[] = $tahun;
        }

        if(!empty($id_pengajuan)){
            $filter .= ' AND e.id_pengajuan = ?';
            $param[] = $id_pengajuan;
        }

        return DB::select(
            'SELECT 
                md5(concat(e.id, "'.$this->imbuhan.'")) AS id,
                e.id_pengajuan,
                e.tahun,
                e.nama,
                e.prestasi
            FROM m_event e
            WHERE '.$filter.'
            ORDER BY e.tahun DESC, e.nama ASC
            ',$param);
    }

    function getEventByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                e.id,
                e.id_pengajuan,
                e.tahun,
                e.nama,
                e.prestasi
            FROM m_event e
            WHERE md5(concat(e.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getEventsByPengajuan($id_pengajuan){
        return DB::select(
            'SELECT 
                md5(concat(e.id, "'.$this->imbuhan.'")) AS id,
                e.id_pengajuan,
                e.tahun,
                e.nama,
                e.prestasi
            FROM m_event e
            WHERE e.id_pengajuan = ?
            ORDER BY e.tahun DESC
            ',[$id_pengajuan]);
    }

    function getEventsByTahun($tahun){
        return DB::select(
            'SELECT 
                md5(concat(e.id, "'.$this->imbuhan.'")) AS id,
                e.id_pengajuan,
                e.tahun,
                e.nama,
                e.prestasi
            FROM m_event e
            WHERE e.tahun = ?
            ORDER BY e.nama ASC
            ',[$tahun]);
    }
}
