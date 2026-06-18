<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VisiMisi extends Model
{
    protected $table = 'm_visi_misi';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'id', 'tipe', 'judul', 'isi', 'status', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'V1S1M1S1';

    function getVisiMisiList($keyword = null, $tipe = null, $status = 1)
    {
        $filter = 'v.status = ?';
        $param = [$status];

        if (!empty($tipe)) {
            $filter .= ' AND v.tipe = ?';
            $param[] = $tipe;
        }

        if (!empty($keyword)) {
            $kw = '%' . $keyword . '%';
            $filter .= ' AND (v.judul LIKE ? OR v.isi LIKE ?)';
            $param[] = $kw;
            $param[] = $kw;
        }

        return DB::select(
            'SELECT 
                md5(concat(v.id, "' . $this->imbuhan . '")) AS id,
                v.tipe,
                v.judul,
                v.isi,
                v.created_at
            FROM m_visi_misi v
            WHERE ' . $filter . '
            ORDER BY v.tipe ASC, v.created_at DESC
            ',
            $param
        );
    }

    function getVisiMisiByEncId($encryptedid)
    {
        $data = DB::select(
            'SELECT 
                v.id,
                v.tipe,
                v.judul,
                v.isi,
                v.created_at
            FROM m_visi_misi v
            WHERE md5(concat(v.id, "' . $this->imbuhan . '")) = ?
            ',
            [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
