<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Onboarding extends Model
{
    protected $table = 'm_onboarding';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'filename', 'konten', 'judul', 'urutan'
    ];
    public $imbuhan = '0NB04RD1NG';

    function getOnboardings(){
        return DB::select(
            'SELECT 
                md5(concat(o.id, "'.$this->imbuhan.'")) AS id,
                o.filename,
                o.konten,
                o.judul,
                o.urutan
            FROM m_onboarding o
            ORDER BY o.urutan ASC
            ');
    }

    function getOnboardingByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                o.id,
                o.filename,
                o.konten,
                o.judul,
                o.urutan
            FROM m_onboarding o
            WHERE md5(concat(o.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getOnboardingById($id){
        $data = DB::select(
            'SELECT 
                md5(concat(o.id, "'.$this->imbuhan.'")) AS id,
                o.filename,
                o.konten,
                o.judul,
                o.urutan
            FROM m_onboarding o
            WHERE o.id = ?
            ',[$id]);
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
