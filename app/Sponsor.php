<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sponsor extends Model
{
    protected $table = 'm_sponsor';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'id', 'nama', 'deskripsi', 'filename', 'status', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'SP0NS0R';

    function getSponsorList($keyword = null, $status = 1)
    {
        $filter = 's.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $kw = '%' . $keyword . '%';
            $filter .= ' AND (s.nama LIKE ? OR s.deskripsi LIKE ?)';
            $param[] = $kw;
            $param[] = $kw;
        }

        return DB::select(
            'SELECT 
                md5(concat(s.id, "' . $this->imbuhan . '")) AS id,
                s.nama,
                s.deskripsi,
                s.filename,
                s.created_at
            FROM m_sponsor s
            WHERE ' . $filter . '
            ORDER BY s.created_at DESC
            ',
            $param
        );
    }

    function getSponsorByEncId($encryptedid)
    {
        $data = DB::select(
            'SELECT 
                s.id,
                s.nama,
                s.deskripsi,
                s.filename,
                s.created_at
            FROM m_sponsor s
            WHERE md5(concat(s.id, "' . $this->imbuhan . '")) = ?
            ',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getSponsorDetail($encryptedid)
    {
        $data = DB::select(
            'SELECT 
                md5(concat(s.id, "' . $this->imbuhan . '")) AS id,
                s.nama,
                s.deskripsi,
                s.filename,
                s.created_at
            FROM m_sponsor s
            WHERE md5(concat(s.id, "' . $this->imbuhan . '")) = ?
            ',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
