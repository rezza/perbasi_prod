<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EventPendaftaran extends Model
{
    protected $table = 'm_event_pendaftaran';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_news', 'tipe_penempatan', 'nama_event', 'tanggal_mulai', 'tanggal_selesai', 'jam_mulai', 'lokasi',
        'biaya_pendaftaran', 'skema_pendaftaran', 'required_fields', 'status',
        'id_admin', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'EV3NTP3ND';

    // Client-facing rows: id / id_news are encoded. Used for list endpoints.
    function selectFieldsList(){
        return '
            md5(concat(ep.id, "'.$this->imbuhan.'")) AS id,
            CASE WHEN n.id IS NULL THEN NULL ELSE md5(concat(n.id, "N3W5")) END AS id_news,
            ep.tipe_penempatan,
            n.judul as judul_news,
            n.filename as filename_banner,
            ep.nama_event,
            ep.tanggal_mulai,
            ep.tanggal_selesai,
            TIME_FORMAT(ep.jam_mulai, "%H:%i") AS jam_mulai,
            TIME_FORMAT(pt.jam_checkin, "%H:%i") AS jam_checkin,
            COALESCE(pt.checkin_enabled, 0) AS checkin_enabled,
            ep.lokasi,
            ep.biaya_pendaftaran,
            ep.skema_pendaftaran,
            ep.required_fields,
            ep.status,
            ep.created_at
        ';
    }

    // Internal-lookup rows: id / id_news are the raw numeric PKs, for use by
    // the controller (joins, updates, permission checks). Never return this
    // row shape directly to the client — re-encode id fields first.
    function selectFieldsInternal(){
        return '
            ep.id,
            ep.id_news,
            ep.tipe_penempatan,
            n.judul as judul_news,
            n.filename as filename_banner,
            ep.nama_event,
            ep.tanggal_mulai,
            ep.tanggal_selesai,
            TIME_FORMAT(ep.jam_mulai, "%H:%i") AS jam_mulai,
            TIME_FORMAT(pt.jam_checkin, "%H:%i") AS jam_checkin,
            COALESCE(pt.checkin_enabled, 0) AS checkin_enabled,
            ep.lokasi,
            ep.biaya_pendaftaran,
            ep.skema_pendaftaran,
            ep.required_fields,
            ep.status,
            ep.created_at
        ';
    }

    function getEventList($keyword = null, $status = null){
        $filter = '1=1';
        $param = [];

        if(!empty($keyword)){
            $keyword = '%'. $keyword . '%';
            $filter .= ' AND ep.nama_event like ?';
            $param[] = $keyword;
        }

        if(!empty($status)){
            $filter .= ' AND ep.status = ?';
            $param[] = $status;
        }

        return DB::select(
            'SELECT '.$this->selectFieldsList().'
            FROM m_event_pendaftaran ep
            LEFT JOIN m_news n ON ep.id_news = n.id
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE '.$filter.'
            ORDER BY ep.tanggal_mulai DESC
            ', $param
        );
    }

    /**
     * Decode an encoded event id into its full row, with RAW ids
     * (id, id_news). Intended for internal use only.
     */
    function getEventByEncId($encryptedid){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_event_pendaftaran ep
            LEFT JOIN m_news n ON ep.id_news = n.id
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE md5(concat(ep.id, "'.$this->imbuhan.'")) = ?
            ', [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /** Same row shape as getEventByEncId(), looked up by RAW id. */
    function getEventById($id){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_event_pendaftaran ep
            LEFT JOIN m_news n ON ep.id_news = n.id
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE ep.id = ?
            ', [$id]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /**
     * Mutates $row (as returned by getEventByEncId) in place, encoding its
     * raw id / id_news so it is safe to return to the client.
     */
    function encodeForClient($row){
        $row->id = md5($row->id . $this->imbuhan);
        $row->id_news = !empty($row->id_news) ? md5($row->id_news . 'N3W5') : null;
        $row->checkin_enabled = (bool) ($row->checkin_enabled ?? false);
        return $row;
    }

    /**
     * ORDER BY fragment ranking a news's events: still-running/upcoming ones
     * first (soonest first), then finished ones (most recent first). The
     * first row is the "primary" event — what legacy single-event clients
     * (which only understand one event per news) get to see.
     */
    private function primaryOrder(){
        return 'ORDER BY (ep.tanggal_selesai >= CURDATE()) DESC,
                IF(ep.tanggal_selesai >= CURDATE(), ep.tanggal_mulai, NULL) ASC,
                ep.tanggal_mulai DESC, ep.id DESC';
    }

    /**
     * Encoded id of the primary active event of a (raw) news id, or null.
     * Legacy: one news can now carry many events, see getMemberSchedulesByNewsId().
     */
    function getActiveEventEncIdByNewsId($id_news){
        $raw = $this->getActiveEventIdByNewsId($id_news);
        return $raw === null ? null : md5($raw . $this->imbuhan);
    }

    /** Raw id of the primary active event of a (raw) news id, or null. */
    function getActiveEventIdByNewsId($id_news){
        if(empty($id_news)) return null;
        $data = DB::select(
            'SELECT ep.id
            FROM m_event_pendaftaran ep
            WHERE ep.id_news = ? AND ep.status = "aktif"
            '.$this->primaryOrder().'
            LIMIT 1
            ', [$id_news]
        );
        return sizeof($data) > 0 ? $data[0]->id : null;
    }

    /** Raw ids of every active event of a (raw) news id. */
    function getActiveEventIdsByNewsId($id_news){
        if(empty($id_news)) return [];
        $rows = DB::select(
            'SELECT ep.id FROM m_event_pendaftaran ep
            WHERE ep.id_news = ? AND ep.status = "aktif"
            '.$this->primaryOrder(), [$id_news]
        );
        return array_map(function($r){ return $r->id; }, $rows);
    }

    /** perkumpulan chips for many events at once, keyed by raw event id. */
    private function getAllowedPerkumpulanBatch(array $ids){
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::select(
            'SELECT epp.id_event_pendaftaran,
                md5(concat(p.id, "P3RKUMP")) AS id,
                p.nama
            FROM m_event_pendaftaran_perkumpulan epp
            JOIN m_perkumpulan p ON epp.id_perkumpulan = p.id
            WHERE epp.id_event_pendaftaran IN ('.$ph.')
            ORDER BY p.nama ASC', $ids
        );
        $keyed = [];
        foreach ($rows as $r) {
            $keyed[$r->id_event_pendaftaran][] = ['id' => $r->id, 'nama' => $r->nama];
        }
        return $keyed;
    }

    /**
     * Admin: every non-deleted event of a (raw) news id, oldest date first,
     * with participant / attendance counts and perkumpulan chips. Encoded ids.
     */
    function getEventsByNewsId($id_news){
        $rows = DB::select(
            'SELECT
                ep.id AS raw_id,
                md5(concat(ep.id, "'.$this->imbuhan.'")) AS id,
                ep.tipe_penempatan,
                ep.nama_event,
                ep.tanggal_mulai,
                ep.tanggal_selesai,
                TIME_FORMAT(ep.jam_mulai, "%H:%i") AS jam_mulai,
                TIME_FORMAT(pt.jam_checkin, "%H:%i") AS jam_checkin,
                COALESCE(pt.checkin_enabled, 0) AS checkin_enabled,
                ep.lokasi,
                ep.biaya_pendaftaran,
                ep.skema_pendaftaran,
                ep.status,
                (SELECT COUNT(*) FROM m_event_pendaftaran_peserta pe
                    WHERE pe.id_event_pendaftaran = ep.id) AS jumlah_terdaftar,
                (SELECT COUNT(*) FROM m_event_pendaftaran_peserta pe
                    WHERE pe.id_event_pendaftaran = ep.id AND pe.status = 1) AS jumlah_diterima,
                (SELECT COUNT(*) FROM m_pertandingan_checkin c
                    WHERE c.id_pertandingan = pt.id AND c.status_kehadiran = 1) AS jumlah_hadir,
                (SELECT COUNT(*) FROM m_pertandingan_checkin c
                    WHERE c.id_pertandingan = pt.id AND c.status_kehadiran = 0) AS jumlah_tidak_hadir
            FROM m_event_pendaftaran ep
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE ep.id_news = ? AND ep.status <> "nonaktif"
            ORDER BY ep.tanggal_mulai ASC, ep.jam_mulai ASC, ep.id ASC
            ', [$id_news]
        );

        $perk = $this->getAllowedPerkumpulanBatch(array_map(function($r){ return $r->raw_id; }, $rows));
        foreach ($rows as $r) {
            $r->checkin_enabled = (bool) $r->checkin_enabled;
            foreach (['jumlah_terdaftar', 'jumlah_diterima', 'jumlah_hadir', 'jumlah_tidak_hadir'] as $k) {
                $r->$k = (int) $r->$k;
            }
            $r->jumlah_belum_checkin = max(0, $r->jumlah_diterima - $r->jumlah_hadir - $r->jumlah_tidak_hadir);
            $r->perkumpulan = $perk[$r->raw_id] ?? [];
            unset($r->raw_id);
        }
        return $rows;
    }

    /**
     * Member view: every `aktif`/`selesai` event of a (raw) news id, oldest
     * first, each with the caller's own registration + check-in state.
     * 3 fixed queries regardless of how many events the news carries.
     */
    function getMemberSchedulesByNewsId($id_news, $id_user){
        $rows = DB::select(
            'SELECT
                ep.id AS raw_id,
                md5(concat(ep.id, "'.$this->imbuhan.'")) AS id,
                ep.nama_event,
                ep.tanggal_mulai,
                ep.tanggal_selesai,
                TIME_FORMAT(ep.jam_mulai, "%H:%i") AS jam_mulai,
                TIME_FORMAT(pt.jam_checkin, "%H:%i") AS jam_checkin,
                COALESCE(pt.checkin_enabled, 0) AS checkin_enabled,
                pt.id AS raw_pertandingan_id,
                ep.lokasi,
                ep.biaya_pendaftaran,
                ep.skema_pendaftaran,
                ep.status
            FROM m_event_pendaftaran ep
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE ep.id_news = ? AND ep.status IN ("aktif", "selesai")
            ORDER BY ep.tanggal_mulai ASC, ep.jam_mulai ASC, ep.id ASC
            ', [$id_news]
        );
        if (empty($rows)) return [];

        $eventIds = array_map(function($r){ return $r->raw_id; }, $rows);
        $ph = implode(',', array_fill(0, count($eventIds), '?'));

        $regs = [];
        foreach (DB::select(
            'SELECT id_event_pendaftaran, status, catatan FROM m_event_pendaftaran_peserta
            WHERE id_user = ? AND id_event_pendaftaran IN ('.$ph.')',
            array_merge([$id_user], $eventIds)
        ) as $r) {
            $regs[$r->id_event_pendaftaran] = ['status' => (int) $r->status, 'catatan' => $r->catatan];
        }

        $pertIds = array_values(array_filter(array_map(function($r){ return $r->raw_pertandingan_id; }, $rows)));
        $checkins = [];
        if (!empty($pertIds)) {
            $ph2 = implode(',', array_fill(0, count($pertIds), '?'));
            foreach (DB::select(
                'SELECT id_pertandingan, status_kehadiran, checkin_at FROM m_pertandingan_checkin
                WHERE id_user = ? AND id_pertandingan IN ('.$ph2.')',
                array_merge([$id_user], $pertIds)
            ) as $r) {
                $checkins[$r->id_pertandingan] = [
                    'status_kehadiran' => (int) $r->status_kehadiran,
                    'checkin_at' => $r->checkin_at,
                ];
            }
        }

        $perk = $this->getAllowedPerkumpulanBatch($eventIds);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => $r->id,
                'nama_event' => $r->nama_event,
                'tanggal_mulai' => $r->tanggal_mulai,
                'tanggal_selesai' => $r->tanggal_selesai,
                'jam_mulai' => $r->jam_mulai,
                'jam_checkin' => $r->jam_checkin,
                'lokasi' => $r->lokasi,
                'biaya_pendaftaran' => $r->biaya_pendaftaran,
                'skema_pendaftaran' => $r->skema_pendaftaran,
                'status' => $r->status,
                'checkin_enabled' => (bool) $r->checkin_enabled,
                'perkumpulan' => $perk[$r->raw_id] ?? [],
                'my_registration' => $regs[$r->raw_id] ?? null,
                'my_checkin' => $r->raw_pertandingan_id ? ($checkins[$r->raw_pertandingan_id] ?? null) : null,
            ];
        }
        return $out;
    }

    function getAllowedPerkumpulanIds($id_event){
        $rows = DB::select(
            'SELECT id_perkumpulan FROM m_event_pendaftaran_perkumpulan WHERE id_event_pendaftaran = ?',
            [$id_event]
        );
        return array_map(function($r){ return (int) $r->id_perkumpulan; }, $rows);
    }

    function getAllowedPerkumpulan($id_event){
        return DB::select(
            'SELECT
                md5(concat(p.id, "P3RKUMP")) AS id,
                p.no_perkumpulan,
                p.nama
            FROM m_event_pendaftaran_perkumpulan epp
            LEFT JOIN m_perkumpulan p ON epp.id_perkumpulan = p.id
            WHERE epp.id_event_pendaftaran = ?
            ORDER BY p.nama ASC
            ', [$id_event]
        );
    }

    function isPerkumpulanAllowed($id_event, $id_perkumpulan){
        $data = DB::select(
            'SELECT id FROM m_event_pendaftaran_perkumpulan WHERE id_event_pendaftaran = ? AND id_perkumpulan = ?',
            [$id_event, $id_perkumpulan]
        );
        return sizeof($data) > 0;
    }

    function setAllowedPerkumpulan($id_event, array $id_perkumpulan_list){
        DB::delete('DELETE FROM m_event_pendaftaran_perkumpulan WHERE id_event_pendaftaran = ?', [$id_event]);
        foreach($id_perkumpulan_list as $id_perkumpulan){
            if(empty($id_perkumpulan)) continue;
            DB::insert(
                'INSERT INTO m_event_pendaftaran_perkumpulan (id_event_pendaftaran, id_perkumpulan) VALUES (?, ?)',
                [$id_event, $id_perkumpulan]
            );
        }
    }
}
