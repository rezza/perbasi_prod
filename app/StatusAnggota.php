<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StatusAnggota extends Model
{
    protected $table = 'm_status_anggota';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'nama'
    ];
    public $imbuhan = 'ST4TU54NGG0T4';

    function getStatusAnggotas(){
        return DB::select(
            'SELECT 
                md5(concat(s.id, "'.$this->imbuhan.'")) AS id,
                s.nama
            FROM m_status_anggota s
            ORDER BY s.nama ASC
            ');
    }

    function getStatusAnggotaByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                s.id,
                s.nama
            FROM m_status_anggota s
            WHERE md5(concat(s.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getStatusAnggotaById($id){
        $data = DB::select(
            'SELECT 
                md5(concat(s.id, "'.$this->imbuhan.'")) AS id,
                s.nama
            FROM m_status_anggota s
            WHERE s.id = ?
            ',[$id]);
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
