<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MasterPersyaratanEvent extends Model
{
    protected $table = 'm_master_persyaratan_event';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'key', 'label', 'tipe_input', 'sumber', 'profil_field_map',
        'is_default', 'urutan', 'status', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'P3RSY4RAT';

    function selectFields(){
        return '
            md5(concat(id, "'.$this->imbuhan.'")) AS id,
            `key`,
            label,
            tipe_input,
            sumber,
            profil_field_map,
            is_default,
            urutan,
            status
        ';
    }

    function getList($status = null){
        $filter = '1=1';
        $param = [];
        if(!empty($status)){
            $filter .= ' AND status = ?';
            $param[] = $status;
        }
        return DB::select(
            'SELECT '.$this->selectFields().'
            FROM m_master_persyaratan_event
            WHERE '.$filter.'
            ORDER BY urutan ASC, id ASC
            ', $param
        );
    }

    /** Active rows keyed by `key`, for validating/joining event required_fields. Raw (unencoded) ids. */
    function getActiveKeyed(){
        $rows = DB::select(
            'SELECT id, `key`, label, tipe_input, sumber, profil_field_map
            FROM m_master_persyaratan_event
            WHERE status = "aktif"'
        );
        $out = [];
        foreach($rows as $r){ $out[$r->key] = $r; }
        return $out;
    }

    /** Raw row by encoded id. Internal use only (create/update/delete). */
    function getByEncId($encryptedid){
        $data = DB::select(
            'SELECT id, `key`, label, tipe_input, sumber, profil_field_map, is_default, urutan, status
            FROM m_master_persyaratan_event
            WHERE md5(concat(id, "'.$this->imbuhan.'")) = ?
            ', [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function keyExists($key, $excludeId = null){
        if($excludeId){
            $data = DB::select('SELECT id FROM m_master_persyaratan_event WHERE `key` = ? AND id != ?', [$key, $excludeId]);
        } else {
            $data = DB::select('SELECT id FROM m_master_persyaratan_event WHERE `key` = ?', [$key]);
        }
        return sizeof($data) > 0;
    }
}
