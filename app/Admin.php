<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Admin extends Model
{
    protected $table = 'm_admin';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_user', 'username', 'id_role', 'id_perkumpulan', 'status'
    ];
    public $imbuhan = '4DM1N';

    function getAdmins($keyword = null, $id_role = null, $status = 1){
        $filter = 'a.status = ?';
        $param = [$status];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (a.username like ? OR u.nama like ?)';
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if(!empty($id_role)){
            $filter .= ' AND a.id_role = ?';
            $param[] = $id_role;
        }

        return DB::select(
            'SELECT 
                md5(concat(a.id, "'.$this->imbuhan.'")) AS id,
                a.id_user,
                a.username,
                a.id_role,
                a.id_perkumpulan,
                a.status,
                u.nama as nama_user,
                u.email,
                u.no_hp
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            WHERE '.$filter.'
            ORDER BY a.username ASC
            ',$param);
    }

    function getAdminByEncId($encryptedid){
        $data = DB::select(
            'SELECT 
                a.id,
                a.id_user,
                a.username,
                a.id_role,
                a.id_perkumpulan,
                a.status,
                u.nama as nama_user,
                u.email,
                u.no_hp
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            WHERE md5(concat(a.id, "'.$this->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getAdminByUserEncId($encryptedid){
        $user = new User();
        $data = DB::select(
            'SELECT 
                a.id,
                a.id_user,
                a.username,
                a.id_role,
                a.id_perkumpulan,
                a.status,
                u.nama as nama_user,
                u.email,
                u.no_hp
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            WHERE md5(concat(u.id, "'.$user->imbuhan.'")) = ?
            ',[$encryptedid]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getAdminByUsername($username){
        $data = DB::select(
            'SELECT 
                a.id,
                a.id_user,
                a.username,
                a.id_role,
                a.id_perkumpulan,
                a.status
            FROM m_admin a
            WHERE a.username = ? AND a.status = 1
            ',[$username]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getAdminsByPerkumpulan($id_perkumpulan){
        return DB::select(
            'SELECT 
                md5(concat(a.id, "'.$this->imbuhan.'")) AS id,
                a.id_user,
                a.username,
                a.id_role,
                a.id_perkumpulan,
                a.status,
                u.nama as nama_user
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            WHERE a.id_perkumpulan = ? AND a.status = 1
            ORDER BY a.username ASC
            ',[$id_perkumpulan]);
    }


    function getListAdmin($keyword = null, $id_role = null, $status = 1){
        $user = new User(); 
        $filter = 'a.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $keyword = '%' . $keyword . '%';
            $filter .= ' AND (a.username LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)';
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if (!empty($id_role)) {
            $filter .= ' AND a.id_role = ?';
            $param[] = $id_role;
        }

        return DB::select(
            'SELECT 
                md5(concat(a.id, "'.$this->imbuhan.'")) AS id,
                md5(concat(u.id, "'.$user->imbuhan.'")) AS id_user,
                u.nama,
                u.email,
                u.no_hp,
                a.username,
                a.status,
                a.id_role,
                CASE 
                    WHEN a.id_role = 1 THEN "Admin Perbasi"
                    WHEN a.id_role = 2 THEN "Admin Perkumpulan"
                    ELSE "Unknown"
                END as nama_role,
                p.no_perkumpulan,
                p.nama as nama_perkumpulan
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            LEFT JOIN m_perkumpulan p ON a.id_perkumpulan = p.id
            WHERE '.$filter.'
            ORDER BY u.nama ASC
            ', $param
        );
    }
    


    function getListUserAdmin($keyword = null, $id_role = null, $status = 1){
        $user = new User(); 
        $filter = 'a.status = ?';
        $param = [$status];

        if (!empty($keyword)) {
            $keyword = '%' . $keyword . '%';
            $filter .= ' AND (a.username LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)';
            $param[] = $keyword;
            $param[] = $keyword;
            $param[] = $keyword;
        }

        if (!empty($id_role)) {
            $filter .= ' AND a.id_role = ?';
            $param[] = $id_role;
        }

        return DB::select(
            'SELECT 
                md5(concat(a.id, "'.$this->imbuhan.'")) AS id,
                md5(concat(u.id, "'.$user->imbuhan.'")) AS id_user,
                u.nama,
                u.email,
                u.no_hp,
                a.username,
                a.status,
                a.id_role,
                CASE 
                    WHEN a.id_role = 1 THEN "Admin Perbasi"
                    WHEN a.id_role = 2 THEN "Admin Perkumpulan"
                    ELSE "Unknown"
                END as nama_role,
                p.no_perkumpulan,
                p.nama as nama_perkumpulan
            FROM m_admin a
            LEFT JOIN m_user u ON a.id_user = u.id
            LEFT JOIN m_perkumpulan p ON a.id_perkumpulan = p.id
            WHERE '.$filter.'
            GROUP BY u.id 
            ORDER BY u.nama ASC
            ', $param
        );
    }

    function getAdminDetails($id){
        $perkumpulan = new Perkumpulan(); 
        $admin = DB::select(
                'SELECT 
                    md5(concat(a.id, "'.$this->imbuhan.'")) AS id_admin,
                    a.username,
                    CASE 
                        WHEN a.id_role = 1 THEN "Admin Perbasi"
                        WHEN a.id_role = 2 THEN "Admin Perkumpulan"
                        ELSE "Unknown"
                    END as nama_role,
                    a.status,
                    u.nama,
                    u.email,
                    u.no_hp,
                    u.posisi,
                    md5(concat(p.id, "'.$perkumpulan->imbuhan.'")) AS id_perkumpulan,
                    p.no_perkumpulan,
                    p.nama as nama_perkumpulan,
                    p.tahun_gabung,
                    sa.nama as nama_status_anggota,
                    e.no_pengkot,
                    k.name as nama_kota,
                    k.type as tipe_kota,
                    prov.name as nama_provinsi
                FROM m_admin a
                LEFT JOIN m_user u ON a.id_user = u.id
                LEFT JOIN m_perkumpulan p ON a.id_perkumpulan = p.id
                LEFT JOIN m_status_anggota sa ON p.id_status_anggota = sa.id
                LEFT JOIN m_entitas e ON p.id_entitas = e.id
                LEFT JOIN m_kota k ON e.id_kota = k.id
                LEFT JOIN m_provinsi prov ON k.provinsi_id = prov.id
                WHERE md5(concat(a.id, "'.$this->imbuhan.'")) = ?
                ', [$id]
        );
        return sizeof($admin) > 0 ? $admin[0] : null;
    }
}
