<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Kota extends Model
{
    protected $table = 'm_kota';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'type', 'name', 'code', 'full_code', 'provinsi_id'
    ];
    public $imbuhan = 'K0T4';

    function getKotas($keyword = null, $provinsi_id = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (k.name like ? OR k.type like ?)';
            $param = [$keyword, $keyword];
        }

        if(!empty($provinsi_id)){
            $filter .= ' AND k.provinsi_id = ?';
            $param[] = $provinsi_id;
        }

        return DB::select(
            'SELECT 
                md5(concat(k.id, "'.$this->imbuhan.'")) AS id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id,
                p.name as nama_provinsi
            FROM m_kota k
            LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
            WHERE '.$filter.'
            ORDER BY k.name ASC
            ',$param);
    }

    function getKotaByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                k.id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id,
                p.name as nama_provinsi
            FROM m_kota k
            LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
            WHERE md5(concat(k.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        
        if (sizeof($data) > 0) {
            return is_object($data[0]) ? $data[0] : (object) $data[0];
        }
        
        return null;
    }

    function getKotaById($id){
        $data = DB::select(
            'SELECT 
                md5(concat(k.id, "'.$this->imbuhan.'")) AS id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id,
                p.name as nama_provinsi
            FROM m_kota k
            LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
            WHERE k.id = ?
            ',[$id]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getKotasByProvinsi($provinsi_id){
        return DB::select(
            'SELECT 
                md5(concat(k.id, "'.$this->imbuhan.'")) AS id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id
            FROM m_kota k
            WHERE k.provinsi_id = ?
            ORDER BY k.name ASC
            ',[$provinsi_id]);
    }

    function getKotaByCode($code){
        $data = DB::select(
            'SELECT 
                md5(concat(k.id, "'.$this->imbuhan.'")) AS id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id
            FROM m_kota k
            WHERE k.code = ?
            ',[$code]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getKotaByFullCode($full_code){
        $data = DB::select(
            'SELECT 
                md5(concat(k.id, "'.$this->imbuhan.'")) AS id,
                k.type,
                k.name,
                k.code,
                k.full_code,
                k.provinsi_id
            FROM m_kota k
            WHERE k.full_code = ?
            ',[$full_code]);
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
