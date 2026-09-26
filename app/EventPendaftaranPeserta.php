<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventPendaftaranPeserta extends Model
{
    protected $table = 'm_event_pendaftaran_peserta';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_event_pendaftaran', 'id_user', 'id_perkumpulan', 'no_punggung', 'posisi_event',
        'no_hp', 'data_persyaratan', 'status', 'sumber', 'catatan', 'jumlah_pengajuan', 'riwayat_penolakan',
        'id_admin_approval', 'id_admin_input', 'tgl_daftar', 'tgl_approval', 'updated_at'
    ];
    public $imbuhan = 'P3S3RTAEV'; 

    // Posisi peserta: posisi yang diisi admin untuk event ini (import/tambah
    // manual) menang. Selain itu posisi di perkumpulan yang dipilih saat
    // mendaftar: dari pengajuan pemain (m_pengajuan_pemain.posisi) dan/atau SDM
    // (m_posisi.nama). Fallback ke posisi terkini di profil user.
    private function selectPosisi(){
        return ' 
            COALESCE(
                NULLIF(pe.posisi_event, ""),
                NULLIF(CONCAT_WS(", ",
                    (SELECT GROUP_CONCAT(DISTINCT pp.posisi SEPARATOR ", ")
                        FROM m_pengajuan_pemain pp
                        WHERE pp.id_user = pe.id_user
                            AND pp.id_perkumpulan <=> pe.id_perkumpulan
                            AND pp.posisi <> ""),
                    (SELECT GROUP_CONCAT(DISTINCT pos.nama SEPARATOR ", ")
                        FROM m_pengajuan_nonaggota pn
                        JOIN m_posisi pos ON pn.id_posisi = pos.id
                        WHERE pn.id_user = pe.id_user
                            AND pn.id_perkumpulan <=> pe.id_perkumpulan)
                ), ""),
                NULLIF(u.posisi, "")
            ) AS posisi,';
    }

    // Client-facing rows: all ids encoded. Used for list/detail responses.
    function selectFieldsClient(){
        return '
            md5(concat(pe.id, "'.$this->imbuhan.'")) AS id,
            md5(concat(ep.id, "EV3NTP3ND")) AS id_event_pendaftaran,
            ep.nama_event,
            ep.required_fields,
            md5(concat(u.id, "U53R")) AS id_user,
            u.nama as nama_user,
            u.email as email_user,
            u.no_anggota,
            u.jenis_kelamin,
            CASE WHEN p.id IS NULL THEN NULL ELSE md5(concat(p.id, "P3RKUMP")) END AS id_perkumpulan,
            p.nama as nama_perkumpulan,
            '.$this->selectPosisi().'
            pe.no_punggung,
            pe.posisi_event,
            COALESCE(NULLIF(pe.no_hp, ""), u.no_hp) AS no_hp,
            pe.sumber,
            pe.data_persyaratan,
            pe.status,
            pe.catatan,
            pe.jumlah_pengajuan,
            pe.riwayat_penolakan,
            pe.tgl_daftar,
            pe.tgl_approval,
            CASE
                WHEN pe.status = 1 THEN "Diterima"
                WHEN pe.status = -1 THEN "Ditolak"
                ELSE "Menunggu"
            END as status_text
        ';
    }

    // Internal-lookup rows: id / id_event_pendaftaran / id_user / id_perkumpulan
    // are raw numeric PKs, for use by the controller. Never return this row
    // shape directly to the client.
    function selectFieldsInternal(){
        return '
            pe.id,
            pe.id_event_pendaftaran,
            ep.nama_event,
            ep.required_fields,
            pe.id_user,
            u.nama as nama_user,
            u.email as email_user,
            u.no_anggota,
            u.jenis_kelamin,
            pe.id_perkumpulan,
            p.nama as nama_perkumpulan,
            '.$this->selectPosisi().'
            pe.no_punggung,
            pe.posisi_event,
            COALESCE(NULLIF(pe.no_hp, ""), u.no_hp) AS no_hp,
            pe.sumber,
            pe.data_persyaratan,
            pe.status,
            pe.catatan,
            pe.jumlah_pengajuan,
            pe.riwayat_penolakan,
            pe.id_admin_approval,
            pe.tgl_daftar,
            pe.tgl_approval,
            CASE
                WHEN pe.status = 1 THEN "Diterima"
                WHEN pe.status = -1 THEN "Ditolak"
                ELSE "Menunggu"
            END as status_text
        ';
    }

    function getListPeserta($id_event = null, $status = null, $keyword = null){
        $filter = '1=1';
        $param = [];

        if(!empty($id_event)){
            $filter .= ' AND pe.id_event_pendaftaran = ?';
            $param[] = $id_event;
        }

        if($status !== null && $status !== ''){
            $filter .= ' AND pe.status = ?';
            $param[] = $status;
        }

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND (u.nama LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(pe.data_persyaratan, "$.nik")) LIKE ? OR p.nama LIKE ? OR u.no_anggota LIKE ? OR u.email LIKE ?)';
            array_push($param, $keyword, $keyword, $keyword, $keyword, $keyword);
        }

        return DB::select(
            'SELECT '.$this->selectFieldsClient().'
            FROM m_event_pendaftaran_peserta pe
            LEFT JOIN m_event_pendaftaran ep ON pe.id_event_pendaftaran = ep.id
            LEFT JOIN m_user u ON pe.id_user = u.id
            LEFT JOIN m_perkumpulan p ON pe.id_perkumpulan = p.id
            WHERE '.$filter.'
            ORDER BY pe.tgl_daftar DESC
            ', $param
        );
    }

    /** Decode an encoded peserta id into its full row, with RAW ids. Internal use only. */
    function getPesertaByEncId($encryptedid){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_event_pendaftaran_peserta pe
            LEFT JOIN m_event_pendaftaran ep ON pe.id_event_pendaftaran = ep.id
            LEFT JOIN m_user u ON pe.id_user = u.id
            LEFT JOIN m_perkumpulan p ON pe.id_perkumpulan = p.id
            WHERE md5(concat(pe.id, "'.$this->imbuhan.'")) = ?
            ', [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /** Events a member has registered for, newest registration first. Client-facing (encoded ids). */
    function getMyEventList($id_user, $keyword = null){
        $filter = '1=1';
        $param = [$id_user];

        if(!empty($keyword)){
            $filter .= ' AND ep.nama_event LIKE ?';
            $param[] = '%'. $keyword . '%';
        }

        return DB::select(
            'SELECT
                md5(concat(pe.id, "'.$this->imbuhan.'")) AS id,
                md5(concat(ep.id, "EV3NTP3ND")) AS id_event,
                ep.nama_event,
                ep.tanggal_mulai,
                ep.tanggal_selesai,
                ep.lokasi,
                ep.biaya_pendaftaran,
                n.filename AS filename_banner,
                pe.status,
                pe.catatan,
                pe.tgl_daftar,
                pe.tgl_approval,
                CASE
                    WHEN pe.status = 1 THEN "Diterima"
                    WHEN pe.status = -1 THEN "Ditolak"
                    ELSE "Menunggu"
                END as status_text
            FROM m_event_pendaftaran_peserta pe
            LEFT JOIN m_event_pendaftaran ep ON pe.id_event_pendaftaran = ep.id
            LEFT JOIN m_news n ON ep.id_news = n.id
            WHERE pe.id_user = ? AND '.$filter.'
            ORDER BY pe.tgl_daftar DESC
            ', $param
        );
    }

    /** RAW ids (id_user, id_event = both raw). Internal use only. */
    function getByUserAndEvent($id_user, $id_event){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_event_pendaftaran_peserta pe
            LEFT JOIN m_event_pendaftaran ep ON pe.id_event_pendaftaran = ep.id
            LEFT JOIN m_user u ON pe.id_user = u.id
            LEFT JOIN m_perkumpulan p ON pe.id_perkumpulan = p.id
            WHERE pe.id_user = ? AND pe.id_event_pendaftaran = ?
            ', [$id_user, $id_event]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /**
     * Mutates $row (as returned by getPesertaByEncId / getByUserAndEvent) in
     * place, encoding its raw ids so it is safe to return to the client.
     */
    function encodeForClient($row){
        if ($row === null) return null;
        $row->id = md5($row->id . $this->imbuhan);
        $row->id_event_pendaftaran = md5($row->id_event_pendaftaran . 'EV3NTP3ND');
        $row->id_user = md5($row->id_user . 'U53R');
        $row->id_perkumpulan = !empty($row->id_perkumpulan) ? md5($row->id_perkumpulan . 'P3RKUMP') : null;
        return $row;
    }

    function getReportByPerkumpulan($id_event){
        return DB::select(
            'SELECT
                p.nama as nama_perkumpulan,
                COUNT(*) as total,
                SUM(CASE WHEN pe.status = 0 THEN 1 ELSE 0 END) as menunggu,
                SUM(CASE WHEN pe.status = 1 THEN 1 ELSE 0 END) as diterima,
                SUM(CASE WHEN pe.status = -1 THEN 1 ELSE 0 END) as ditolak
            FROM m_event_pendaftaran_peserta pe
            LEFT JOIN m_perkumpulan p ON pe.id_perkumpulan = p.id
            WHERE pe.id_event_pendaftaran = ?
            GROUP BY pe.id_perkumpulan, p.nama
            ORDER BY p.nama ASC
            ', [$id_event]
        );
    }
}
