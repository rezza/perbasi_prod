<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class News extends Model
{
    protected $table = 'm_news';
    protected $primaryKey = 'id';
    public $timestamps = true; // has created_at
    protected $fillable = [
        'id', 'judul', 'filename', 'kategori', 'tanggal', 'konten', 'status', 'created_at', 'id_admin', 'updated_at', 'is_banner'
    ];
    public $imbuhan = 'N3W5';

    function getNews($keyword = null, $kategori = null, $status = 1){
        $filter = 'n.status = ?';
        $param = [$status];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (n.judul like ? OR n.konten like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($kategori)){
            $filter .= ' AND n.kategori = ?';
            $param[] = $kategori;
        }

        return DB::select(
            'SELECT 
                md5(concat(n.id, "'.$this->imbuhan.'")) AS id,
                n.judul,
                n.kategori,
                n.tanggal,
                n.filename,
                n.konten,
                n.status,
                n.created_at,
                n.id_admin,
                a.username as admin_username
            FROM m_news n
            LEFT JOIN m_admin a ON n.id_admin = a.id
            WHERE '.$filter.'
            ORDER BY n.tanggal DESC, n.created_at DESC
            ',$param);
    }

    function getNewsByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                n.id,
                n.judul,
                n.kategori,
                n.filename,
                n.tanggal,
                n.konten,
                n.status,
                n.created_at,
                n.id_admin,
                a.username as admin_username
            FROM m_news n
            LEFT JOIN m_admin a ON n.id_admin = a.id
            WHERE md5(concat(n.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getActiveNews($kategori = null){
        $filter = 'n.status = 1';
        $param = [];

        if(!empty($kategori)){
            $filter .= ' AND n.kategori = ?';
            $param[] = $kategori;
        }

        return DB::select(
            'SELECT 
                md5(concat(n.id, "'.$this->imbuhan.'")) AS id,
                n.judul,
                n.kategori,
                n.tanggal,
                n.konten,
                n.filename,
                n.created_at
            FROM m_news n
            WHERE '.$filter.'
            ORDER BY n.tanggal DESC, n.created_at DESC
            ',$param);
    }


    function getActiveBanner($kategori = null){
        $filter = 'n.status = 1 and n.is_banner = 1';
        $param = [];

        return DB::select(
            'SELECT 
                md5(concat(n.id, "'.$this->imbuhan.'")) AS id,
                n.judul,
                n.kategori,
                n.tanggal,
                n.konten,
                n.filename,
                n.created_at
            FROM m_news n
            WHERE '.$filter.'
            ORDER BY n.tanggal DESC, n.created_at DESC
            ',$param);
    }

    function getNewsByDateRange($start_date, $end_date, $kategori = null){
        $filter = 'n.tanggal BETWEEN ? AND ? AND n.status = 1';
        $param = [$start_date, $end_date];

        if(!empty($kategori)){
            $filter .= ' AND n.kategori = ?';
            $param[] = $kategori;
        }

        return DB::select(
            'SELECT 
                md5(concat(n.id, "'.$this->imbuhan.'")) AS id,
                n.judul,
                n.kategori,
                n.tanggal,
                n.konten,
                n.created_at
            FROM m_news n
            WHERE '.$filter.'
            ORDER BY n.tanggal DESC
            ',$param);
    }

    function getNewsDetails($id){
        $data = DB::select(
                'SELECT 
                    md5(concat(n.id, "'.$this->imbuhan.'")) AS id_news,
                    n.judul,
                    n.filename,
                    n.kategori,
                    n.tanggal,
                    n.konten,
                    n.status,
                    n.created_at,
                    md5(concat(a.id, "4DM1N")) AS id_admin,
                    a.username as admin_username
                FROM m_news n
                LEFT JOIN m_admin a ON n.id_admin = a.id
                WHERE md5(concat(n.id, "'.$this->imbuhan.'")) = ?
                ', [$id]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
