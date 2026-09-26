<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pertandingan extends Model
{
    protected $table = 'm_pertandingan';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_news', 'id_event_pendaftaran', 'judul',
        'jam_mulai', 'jam_checkin', 'checkin_enabled', 'id_admin', 'created_at', 'updated_at'
    ];
    public $imbuhan = 'P3RT4ND1NG4N';
    private $summaryCache = null;

    // Client-facing rows: all ids encoded.
    function selectFieldsClient(){
        return '
            md5(concat(pt.id, "'.$this->imbuhan.'")) AS id,
            CASE WHEN ep.id_news IS NULL THEN NULL ELSE md5(concat(ep.id_news, "N3W5")) END AS id_news,
            md5(concat(pt.id_event_pendaftaran, "EV3NTP3ND")) AS id_event_pendaftaran,
            ep.nama_event,
            ep.lokasi,
            ep.status AS status_event,
            pt.judul,
            pt.jam_mulai,
            pt.jam_checkin,
            pt.checkin_enabled,
            pt.created_at
        ';
    }

    // Internal-lookup rows: raw numeric ids, for use by the controller only.
    function selectFieldsInternal(){
        return '
            pt.id,
            ep.id_news,
            pt.id_event_pendaftaran,
            ep.nama_event,
            ep.lokasi,
            ep.status AS status_event,
            pt.judul,
            pt.jam_mulai,
            pt.jam_checkin,
            pt.checkin_enabled,
            pt.created_at
        ';
    }

    private function joins(){
        return '
            LEFT JOIN m_event_pendaftaran ep ON pt.id_event_pendaftaran = ep.id
        ';
    }

    /** jam_checkin must be <= jam_mulai - 2 jam (admin may widen the gap, never shrink it). */
    const MIN_CHECKIN_GAP_SECONDS = 2 * 60 * 60;

    function validateCheckinGap($jam_mulai, $jam_checkin){
        $mulaiTs = strtotime($jam_mulai);
        $checkinTs = strtotime($jam_checkin);
        if ($mulaiTs === false || $checkinTs === false) return false;
        return $checkinTs <= ($mulaiTs - self::MIN_CHECKIN_GAP_SECONDS);
    }

    /** "14:00" / "14:00:00" -> "14:00:00". Returns null on anything unparsable. */
    function normalizeTime($time){
        if (empty($time)) return null;
        if (preg_match('/^\d{2}:\d{2}$/', $time)) return $time . ':00';
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) return $time;
        return null;
    }

    /**
     * Per encoded news id: the news's PRIMARY active event (upcoming first,
     * see EventPendaftaran::primaryOrder) plus how many active events it has.
     * A news can carry many events; cover cards only need a summary.
     */
    function getSummaryKeyedByNewsEncId(){
        $rows = DB::select(
            'SELECT
                md5(concat(ep.id_news, "N3W5")) AS id_news_enc,
                ep.nama_event,
                ep.tanggal_mulai,
                TIME_FORMAT(ep.jam_mulai, "%H:%i") AS event_jam_mulai,
                ep.lokasi,
                pt.id AS pt_id,
                pt.judul,
                pt.jam_mulai,
                pt.jam_checkin,
                pt.checkin_enabled
            FROM m_event_pendaftaran ep
            LEFT JOIN m_pertandingan pt ON pt.id_event_pendaftaran = ep.id
            WHERE ep.id_news IS NOT NULL AND ep.status = "aktif"
            ORDER BY (ep.tanggal_selesai >= CURDATE()) DESC,
                IF(ep.tanggal_selesai >= CURDATE(), ep.tanggal_mulai, NULL) ASC,
                ep.tanggal_mulai DESC, ep.id DESC
            '
        );

        $keyed = [];
        foreach ($rows as $row) {
            if (!isset($keyed[$row->id_news_enc])) {
                $keyed[$row->id_news_enc] = ['primary' => $row, 'count' => 0];
            }
            $keyed[$row->id_news_enc]['count']++;
        }
        return $keyed;
    }

    /**
     * Session summary the member app renders on news/banner covers. Accepts a
     * row from getSummaryKeyedByNewsEncId() or getByEventId().
     */
    function summaryPayload($row){
        return [
            'judul' => $row->judul,
            'jam_mulai' => $row->jam_mulai,
            'jam_checkin' => $row->jam_checkin,
            'checkin_enabled' => (bool) $row->checkin_enabled,
            'lokasi' => $row->lokasi,
        ];
    }

    /**
     * Sets on each news row (whose `id` is the encoded news id):
     *  - `pertandingan`: check-in summary of its primary event, or null
     *  - `jumlah_jadwal`: number of active events under the news
     *  - `jadwal_terdekat`: name/date/time/location of the primary event, or null
     * One query for the whole list.
     */
    function attachSummaryToNewsRows($rows){
        $this->summaryCache ??= $this->getSummaryKeyedByNewsEncId();
        $keyed = $this->summaryCache;
        foreach ($rows as $row) {
            $hit = $keyed[$row->id] ?? null;
            if (!$hit) {
                $row->pertandingan = null;
                $row->jumlah_jadwal = 0;
                $row->jadwal_terdekat = null;
                continue;
            }
            $p = $hit['primary'];
            $row->pertandingan = $p->pt_id ? $this->summaryPayload($p) : null;
            $row->jumlah_jadwal = $hit['count'];
            $row->jadwal_terdekat = [
                'nama_event' => $p->nama_event,
                'tanggal_mulai' => $p->tanggal_mulai,
                'jam_mulai' => $p->event_jam_mulai,
                'lokasi' => $p->lokasi,
            ];
        }
        return $rows;
    }

    /** Upserts by id_event_pendaftaran: one session per event. Returns the raw numeric id. */
    function createOrUpdatePertandingan(array $data){
        $existing = $this->getRawByEventId($data['id_event_pendaftaran']);

        if ($existing) {
            DB::table('m_pertandingan')->where('id', $existing->id)->update([
                'judul' => $data['judul'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_checkin' => $data['jam_checkin'],
                'checkin_enabled' => $data['checkin_enabled'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return $existing->id;
        }

        return DB::table('m_pertandingan')->insertGetId([
            'id_event_pendaftaran' => $data['id_event_pendaftaran'],
            'judul' => $data['judul'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_checkin' => $data['jam_checkin'],
            'checkin_enabled' => $data['checkin_enabled'],
            'id_admin' => $data['id_admin'] ?? null,
        ]);
    }

    /**
     * Shared by pertandingan/save and event create/update/bulk-create: validates
     * the 2h gap and upserts the event's session. $eventRow needs id,
     * tanggal_mulai. Returns null on success, or the ResponseController error
     * code (93) when the gap is too small.
     */
    function saveForEvent($eventRow, $judul, $jam_mulai_time, $jam_checkin_time, $enabled, $id_admin = null){
        $jam_mulai = $eventRow->tanggal_mulai . ' ' . $jam_mulai_time;
        $jam_checkin = $eventRow->tanggal_mulai . ' ' . $jam_checkin_time;
        if (!$this->validateCheckinGap($jam_mulai, $jam_checkin)) return 93;

        $this->createOrUpdatePertandingan([
            'id_event_pendaftaran' => $eventRow->id,
            'judul' => $judul,
            'jam_mulai' => $jam_mulai,
            'jam_checkin' => $jam_checkin,
            'checkin_enabled' => $enabled,
            'id_admin' => $id_admin,
        ]);
        return null;
    }

    /**
     * After an event's date/start time changed, re-anchors its existing
     * session (if any) to the event's new tanggal_mulai + jam_mulai while
     * keeping the check-in clock time. Returns 93 if the new start time would
     * leave less than the 2h gap, else null. No-op when there is no session.
     */
    function resyncWithEvent($id_event, $tanggal_mulai, $jam_mulai_time){
        $existing = DB::select('SELECT id, jam_checkin FROM m_pertandingan WHERE id_event_pendaftaran = ? LIMIT 1', [$id_event]);
        if (empty($existing)) return null;

        $checkinTime = date('H:i:s', strtotime($existing[0]->jam_checkin));
        $jam_mulai = $tanggal_mulai . ' ' . $jam_mulai_time;
        $jam_checkin = $tanggal_mulai . ' ' . $checkinTime;
        if (!$this->validateCheckinGap($jam_mulai, $jam_checkin)) return 93;

        DB::table('m_pertandingan')->where('id', $existing[0]->id)->update([
            'jam_mulai' => $jam_mulai,
            'jam_checkin' => $jam_checkin,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return null;
    }

    private function getRawByEventId($id_event){
        $data = DB::select('SELECT id FROM m_pertandingan WHERE id_event_pendaftaran = ? LIMIT 1', [$id_event]);
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /** Internal-shaped row (raw ids) by RAW event id. */
    function getByEventId($id_event){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_pertandingan pt
            '.$this->joins().'
            WHERE pt.id_event_pendaftaran = ?
            LIMIT 1
            ', [$id_event]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /** Internal-shaped row (raw ids) by encoded pertandingan id. */
    function getByEncId($encryptedid){
        $data = DB::select(
            'SELECT '.$this->selectFieldsInternal().'
            FROM m_pertandingan pt
            '.$this->joins().'
            WHERE md5(concat(pt.id, "'.$this->imbuhan.'")) = ?
            ', [$encryptedid]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    function getListByEvent($id_event_pendaftaran = null, $keyword = null, $status = null){
        $filter = '1=1';
        $param = [];

        if (!empty($id_event_pendaftaran)) {
            $filter .= ' AND pt.id_event_pendaftaran = ?';
            $param[] = $id_event_pendaftaran;
        }

        if (!empty($keyword)) {
            $filter .= ' AND pt.judul LIKE ?';
            $param[] = '%'.$keyword.'%';
        }

        if ($status === 'aktif') {
            $filter .= ' AND pt.checkin_enabled = 1';
        }

        $rows = DB::select(
            'SELECT '.$this->selectFieldsClient().'
            FROM m_pertandingan pt
            '.$this->joins().'
            WHERE '.$filter.'
            ORDER BY pt.jam_mulai DESC
            ', $param
        );
        foreach ($rows as $row) {
            $row->checkin_enabled = (bool) $row->checkin_enabled;
        }
        return $rows;
    }

    /**
     * Mutates $row (as returned by getByNewsId/getByEncId/getByNewsEncId) in
     * place, encoding its raw ids so it is safe to return to the client.
     * Also normalizes checkin_enabled to a real bool — MySQL's TINYINT
     * comes back from raw DB::select() as a PHP int/string, and json_encode
     * would otherwise send 1/0 instead of true/false, which every Dart
     * client compares with `== true` (always false for a JSON number).
     */
    function encodeForClient($row){
        if ($row === null) return null;
        $row->id = md5($row->id . $this->imbuhan);
        $row->id_news = !empty($row->id_news) ? md5($row->id_news . 'N3W5') : null;
        $row->id_event_pendaftaran = md5($row->id_event_pendaftaran . 'EV3NTP3ND');
        $row->checkin_enabled = (bool) $row->checkin_enabled;
        return $row;
    }
}
