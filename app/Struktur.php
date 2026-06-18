<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Struktur extends Model
{
    protected $table = 'm_struktur';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'id', 'nama', 'jabatan', 'filename', 'urutan', 'status', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'STRUKTUR';

    function getStrukturList($keyword = null, $status = 1)
    {
        $filter = 's.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $kw = '%' . $keyword . '%';
            $filter .= ' AND (s.nama LIKE ? OR s.jabatan LIKE ?)';
            $param[] = $kw;
            $param[] = $kw;
        }

        return DB::select(
            'SELECT 
                md5(concat(s.id, "' . $this->imbuhan . '")) AS id,
                s.nama,
                s.jabatan,
                s.filename,
                s.urutan,
                s.created_at
            FROM m_struktur s
            WHERE ' . $filter . '
            ORDER BY s.urutan ASC, s.created_at ASC
            ',
            $param
        );
    }

    function getStrukturByEncId($encryptedid)
    {
        $data = DB::select(
            'SELECT 
                s.id,
                s.nama,
                s.jabatan,
                s.filename,
                s.urutan,
                s.created_at
            FROM m_struktur s
            WHERE md5(concat(s.id, "' . $this->imbuhan . '")) = ?
            ',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getStrukturDetail($encryptedid)
    {
        $data = DB::select(
            'SELECT 
                md5(concat(s.id, "' . $this->imbuhan . '")) AS id,
                s.nama,
                s.jabatan,
                s.filename,
                s.urutan,
                s.created_at
            FROM m_struktur s
            WHERE md5(concat(s.id, "' . $this->imbuhan . '")) = ?
            ',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
