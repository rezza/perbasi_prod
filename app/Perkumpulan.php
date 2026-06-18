<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Perkumpulan extends Model
{
    protected $table = 'm_perkumpulan';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_entitas', 'no_perkumpulan', 'nama', 'tahun_gabung',
        'id_status_anggota', 'no_sk', 'keterangan', 'logo', 'status'
    ];
    public $imbuhan = 'P3RKUMP';

    function getPerkumpulans($keyword = null, $id_entitas = null, $id_status_anggota = null, $status = 1){
        $filter = 'pk.status = ?';
        $param = [$status];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (pk.nama like ? OR pk.no_perkumpulan like ? OR pk.no_sk like ? OR pk.keterangan like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($id_entitas)){
            $filter .= ' AND pk.id_entitas = ?';
            $param[] = $id_entitas;
        }

        if(!empty($id_status_anggota)){
            $filter .= ' AND pk.id_status_anggota = ?';
            $param[] = $id_status_anggota;
        }

        return DB::select(
            'SELECT
                md5(concat(pk.id, "'.$this->imbuhan.'")) AS id,
                pk.id_entitas,
                pk.no_perkumpulan,
                pk.nama,
                pk.tahun_gabung,
                pk.id_status_anggota,
                pk.no_sk,
                pk.keterangan,
                pk.logo,
                pk.status,
                e.no_pengkot,
                k.name as nama_kota,
                sa.nama as nama_status_anggota
            FROM m_perkumpulan pk
            LEFT JOIN m_entitas e ON pk.id_entitas = e.id
            LEFT JOIN m_kota k ON e.id_kota = k.id
            LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
            WHERE '.$filter.'
            ORDER BY pk.nama ASC
            ',$param);
    }

    function getPerkumpulanByEncId($encryptedid){
        $data = DB::select(
            'SELECT
                pk.id,
                pk.id_entitas,
                pk.no_perkumpulan,
                pk.nama,
                pk.tahun_gabung,
                pk.id_status_anggota,
                pk.no_sk,
                pk.logo,
                pk.status,
                e.no_pengkot,
                e.id_kota,
                k.name as nama_kota,
                k.provinsi_id,
                p.name as nama_provinsi,
                sa.nama as nama_status_anggota
            FROM m_perkumpulan pk
            LEFT JOIN m_entitas e ON pk.id_entitas = e.id
            LEFT JOIN m_kota k ON e.id_kota = k.id
            LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
            LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
            WHERE md5(concat(pk.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getPerkumpulansByEntitas($id_entitas){
        return DB::select(
            'SELECT
                md5(concat(pk.id, "'.$this->imbuhan.'")) AS id,
                pk.id_entitas,
                pk.no_perkumpulan,
                pk.nama,
                pk.tahun_gabung,
                pk.id_status_anggota,
                pk.no_sk,
                pk.logo,
                pk.status,
                sa.nama as nama_status_anggota
            FROM m_perkumpulan pk
            LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
            WHERE pk.id_entitas = ? AND pk.status = 1
            ORDER BY pk.nama ASC
            ',[$id_entitas]);
    }

    function getPerkumpulansByKota($id_kota){
        return DB::select(
            'SELECT 
                md5(concat(pk.id, "'.$this->imbuhan.'")) AS id,
                pk.id_entitas,
                pk.no_perkumpulan,
                pk.nama,
                pk.tahun_gabung,
                pk.id_status_anggota,
                pk.no_sk,
                pk.status
            FROM m_perkumpulan pk
            LEFT JOIN m_entitas e ON pk.id_entitas = e.id
            WHERE e.id_kota = ? AND pk.status = 1
            ORDER BY pk.nama ASC
            ',[$id_kota]);
    }

    function getPerkumpulanList($status, $keyword = null, $entitas_id = null, $status_anggota_id = null, $id_kota = null){
        $filter = 'pk.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $keyword = '%' . $keyword . '%';
            $filter .= ' AND (pk.nama LIKE ? OR pk.no_perkumpulan LIKE ? OR e.no_pengkot LIKE ?)';
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if (!empty($entitas_id)) {
            $filter .= ' AND pk.id_entitas = ?';
            $param[] = $entitas_id;
        }

        if (!empty($status_anggota_id)) {
            $filter .= ' AND pk.id_status_anggota = ?';
            $param[] = $status_anggota_id;
        }

        if (!empty($id_kota)) {
            $filter .= ' AND e.id_kota = ?';
            $param[] = $id_kota;
        }

        $entitas = new Entitas();
        $statusAnggota = new StatusAnggota();

        return DB::select(
                'SELECT
                    md5(concat(pk.id, "'.$this->imbuhan.'")) AS id,
                    md5(concat(e.id, "'.$entitas->imbuhan.'")) AS id_entitas,
                    md5(concat(sa.id, "'.$statusAnggota->imbuhan.'")) AS id_status_anggota,
                    pk.nama,
                    pk.no_perkumpulan,
                    pk.tahun_gabung,
                    e.no_pengkot,
                    pk.no_sk,
                    pk.keterangan,
                    pk.logo,
                    k.name as nama_kota,
                    sa.nama as nama_status_anggota,
                    (SELECT COUNT(*) FROM m_user u
                     LEFT JOIN m_pengajuan_pemain pp ON u.id = pp.id_user
                     WHERE pp.id_perkumpulan = pk.id AND u.status = 1) as jumlah_anggota,
                    (SELECT COUNT(*) FROM m_pengajuan_nonaggota pna
                     WHERE pna.id_perkumpulan = pk.id) as jumlah_non_anggota
                FROM m_perkumpulan pk
                LEFT JOIN m_entitas e ON pk.id_entitas = e.id
                LEFT JOIN m_kota k ON e.id_kota = k.id
                LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                WHERE '.$filter.'
                ORDER BY pk.nama ASC
                ', $param
        );
    }

    function getPerkumpulanDetails($id){
        $entitas = new Entitas();
        $statusAnggota = new StatusAnggota();
        $data = DB::select(
                'SELECT
                    pk.id,
                    md5(concat(pk.id, "'.$this->imbuhan.'")) AS encrypted_id,
                    md5(concat(e.id, "'.$entitas->imbuhan.'")) AS id_entitas,
                    pk.no_perkumpulan,
                    pk.nama,
                    pk.tahun_gabung,
                    md5(concat(sa.id, "'.$statusAnggota->imbuhan.'")) AS id_status_anggota,
                    pk.no_sk,
                    pk.keterangan,
                    pk.logo,
                    pk.status,
                    e.no_pengkot,
                    k.name as nama_kota,
                    k.type as tipe_kota,
                    p.name as nama_provinsi,
                    p.code as kode_provinsi,
                    sa.nama as nama_status_anggota
                FROM m_perkumpulan pk
                LEFT JOIN m_entitas e ON pk.id_entitas = e.id
                LEFT JOIN m_kota k ON e.id_kota = k.id
                LEFT JOIN m_provinsi p ON k.provinsi_id = p.id
                LEFT JOIN m_status_anggota sa ON pk.id_status_anggota = sa.id
                WHERE md5(concat(pk.id, "'.$this->imbuhan.'")) = ?
                ', [$id]
            );
        return sizeof($data) > 0 ? $data[0] : null;
    }
}
