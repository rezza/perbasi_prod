<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Provinsi extends Model
{
    protected $table = 'm_provinsi';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'name', 'code'
    ];
    public $imbuhan = 'PR0V1N51';

    function getProvinsis($keyword = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND p.name like ?';
            $param = [$keyword];
        }

        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.name,
                p.code
            FROM m_provinsi p
            WHERE '.$filter.'
            ORDER BY p.name ASC
            ',$param);
    }

    function getProvinsiByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                p.id,
                p.name,
                p.code
            FROM m_provinsi p
            WHERE md5(concat(p.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getProvinsiById($id){
        $data = DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.name,
                p.code
            FROM m_provinsi p
            WHERE p.id = ?
            ',[$id]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getProvinsiByCode($code){
        $data = DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.name,
                p.code
            FROM m_provinsi p
            WHERE p.code = ?
            ',[$code]);
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
