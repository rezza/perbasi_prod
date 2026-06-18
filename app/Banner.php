<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Banner extends Model
{
    protected $table = 'm_banner';
    protected $primaryKey = 'id';
    public $timestamps = true; // has created_at
    protected $fillable = [
        'id', 'filename', 'judul', 'tanggal', 'konten', 'status', 'created_at', 'id_admin', 'updated_at'
    ];
    public $imbuhan = 'B4NN3R';

    function getBanners($keyword = null, $status = 1){
        $filter = 'b.status = ?';
        $param = [$status];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (b.judul like ? OR b.konten like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        return DB::select(
            'SELECT 
                md5(concat(b.id, "'.$this->imbuhan.'")) AS id,
                b.judul,
                b.tanggal,
                b.konten,
                b.status,
                b.created_at,
                b.id_admin,
                a.username as admin_username
            FROM m_banner b
            LEFT JOIN m_admin a ON b.id_admin = a.id
            WHERE '.$filter.'
            ORDER BY b.tanggal DESC, b.created_at DESC
            ',$param);
    }

    function getBannerByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                b.id,
                b.judul,
                b.tanggal,
                b.konten,
                b.status,
                b.created_at,
                b.id_admin,
                a.username as admin_username
            FROM m_banner b
            LEFT JOIN m_admin a ON b.id_admin = a.id
            WHERE md5(concat(b.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getActiveBanners(){
        return DB::select(
            'SELECT 
                md5(concat(b.id, "'.$this->imbuhan.'")) AS id,
                b.judul,
                b.tanggal,
                b.konten,
                b.created_at
            FROM m_banner b
            WHERE b.status = 1
            ORDER BY b.tanggal DESC, b.created_at DESC
            ');
    }

    function getBannersByDateRange($start_date, $end_date){
        return DB::select(
            'SELECT 
                md5(concat(b.id, "'.$this->imbuhan.'")) AS id,
                b.judul,
                b.tanggal,
                b.konten,
                b.status,
                b.created_at
            FROM m_banner b
            WHERE b.tanggal BETWEEN ? AND ? AND b.status = 1
            ORDER BY b.tanggal DESC
            ',[$start_date, $end_date]);
    }

    function getBannerList($status, $keyword = null){
        $filter = 'b.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $keyword = '%' . $keyword . '%';
            $filter .= ' AND (b.judul LIKE ? OR b.konten LIKE ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        return DB::select(
            'SELECT 
                md5(concat(b.id, "'.$this->imbuhan.'")) AS id,
                b.filename,
                b.judul,
                b.tanggal,
                b.created_at,
                a.username as admin_username
            FROM m_banner b
            LEFT JOIN m_admin a ON b.id_admin = a.id
            WHERE '.$filter.'
            ORDER BY b.tanggal DESC, b.created_at DESC
            ', $param
        );
    }

    function getDetailBanner($id){
        $data = DB::select(
                'SELECT 
                    md5(concat(b.id, "'.$this->imbuhan.'")) AS id_banner,
                    b.judul,
                    b.filename,
                    b.tanggal,
                    b.konten,
                    b.status,
                    b.created_at,
                    md5(concat(a.id, "4DM1N")) AS encrypted_id_admin,
                    a.username as admin_username
                FROM m_banner b
                LEFT JOIN m_admin a ON b.id_admin = a.id
                WHERE md5(concat(b.id, "'.$this->imbuhan.'")) = ?
                ', [$id]
        );
        return count($data) > 0 ? $data[0] : null;
    }
}
