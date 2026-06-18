<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Posisi extends Model
{
    protected $table = 'm_posisi';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'nama', 'kategori'
    ];
    public $imbuhan = 'P051SI';

    function getPosisis($kategori = null){
        $query = 'SELECT
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.nama,
                p.kategori';

        if ($kategori && !empty($kategori)) {
            $query .= ' FROM m_posisi p WHERE p.kategori = ?';
        } else {
            $query .= ' FROM m_posisi p';
        }

        $query .= ' ORDER BY p.kategori ASC, p.nama ASC';

        if (!empty($kategori)) {
            return DB::select($query, [$kategori]);
        }

        return DB::select($query);
    }

    function getPosisiByEncId($encryptedid){
        $data = DB::select(
            'SELECT
                p.id,
                p.nama,
                p.kategori
            FROM m_posisi p
            WHERE md5(concat(p.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPosisiById($id){
        $data = DB::select(
            'SELECT
                md5(concat(p.id, "'.$this->imbuhan.'")) AS id,
                p.nama,
                p.kategori
            FROM m_posisi p
            WHERE p.id = ?
            ',[$id]);
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
