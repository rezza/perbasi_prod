<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pendidikan extends Model
{
    protected $table = 'm_pendidikan';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'tahun', 'nama', 'jenjang'
    ];
    public $imbuhan = 'P3ND1D1K4N';

    function getPendidikans($keyword = null, $id_user = null, $jenjang = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (p.nama like ? OR p.jenjang like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($id_user)){
            $filter .= ' AND p.id_user = ?';
            $param[] = $id_user;
        }

        if(!empty($jenjang)){
            $filter .= ' AND p.jenjang = ?';
            $param[] = $jenjang;
        }

        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_user,
                p.tahun,
                p.nama,
                p.jenjang,
                u.nama as nama_user,
                u.email
            FROM m_pendidikan p
            LEFT JOIN m_user u ON p.id_user = u.id
            WHERE '.$filter.'
            ORDER BY p.tahun ASC
            ',$param);
    }

    function getPendidikanByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                p.id,
                p.id_user,
                p.tahun,
                p.nama,
                p.jenjang,
                u.nama as nama_user,
                u.email
            FROM m_pendidikan p
            LEFT JOIN m_user u ON p.id_user = u.id
            WHERE md5(concat(p.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPendidikanByUser($id_user){
        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_user,
                p.tahun,
                p.nama,
                p.jenjang
            FROM m_pendidikan p
            WHERE p.id_user = ?
            ORDER BY p.tahun ASC
            ',[$id_user]);
    }

    function getPendidikanByJenjang($jenjang){
        return DB::select(
            'SELECT 
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.id_user,
                p.tahun,
                p.nama,
                p.jenjang,
                u.nama as nama_user
            FROM m_pendidikan p
            LEFT JOIN m_user u ON p.id_user = u.id
            WHERE p.jenjang = ?
            ORDER BY p.tahun ASC
            ',[$jenjang]);
    }
}
