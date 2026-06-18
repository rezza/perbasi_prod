<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Entitas extends Model
{
    protected $table = 'm_entitas';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_kota', 'no_pengkot', 'no_sk', 'status'
    ];
    public $imbuhan = '3NT1T45';

    function getEntitas($keyword = null, $id_kota = null, $status = 1){
        $filter = 'e.status = ?';
        $param = [$status];

        $kota = new Kota();

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (e.no_pengkot like ? OR e.no_sk like ? OR k.name like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($id_kota)){
            $filter .= ' AND e.id_kota = ?';
            $param[] = $id_kota;
        }

        return DB::select(
            'SELECT 
                md5(concat(e.id, "'.$this->imbuhan.'")) AS id,
                md5(concat(e.id_kota, "'.$kota->imbuhan.'")) AS id_kota,
                e.no_pengkot,
                e.no_sk,
                e.status,
                k.name as nama_kota,
                k.type as tipe_kota,
                k.provinsi_id
            FROM m_entitas e
            LEFT JOIN m_kota k ON e.id_kota = k.id
            WHERE '.$filter.'
            ORDER BY k.name ASC
            ',$param);
    }

    function getEntitasByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                e.id,
                e.id_kota,
                e.no_pengkot,
                e.no_sk,
                e.status,
                k.name as nama_kota,
                k.type as tipe_kota,
                k.provinsi_id
            FROM m_entitas e
            LEFT JOIN m_kota k ON e.id_kota = k.id
            WHERE md5(concat(e.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getEntitasByKota($id_kota){
        return DB::select(
            'SELECT 
                md5(concat(e.id, "'.$this->imbuhan.'")) AS id,
                e.id_kota,
                e.no_pengkot,
                e.no_sk,
                e.status
            FROM m_entitas e
            WHERE e.id_kota = ? AND e.status = 1
            ',[$id_kota]);
    }
}
