<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\EventPendaftaran;
use App\EventPendaftaranPeserta;
use App\Pertandingan;
use App\MasterPersyaratanEvent;
use App\News;
use App\Perkumpulan;
use App\Admin;
use App\User;

class APIEventPendaftaranController extends Controller
{
    protected $event;
    protected $peserta;
    protected $pertandingan;
    protected $masterPersyaratan;
    protected $news;
    protected $perkumpulan;
    protected $admin;
    protected $user;

    const SKEMA_OPTIONS = ['mitra_dan_anggota', 'anggota'];
    const TIPE_PENEMPATAN_OPTIONS = ['banner', 'berita'];
    const STATUS_OPTIONS = ['draft', 'aktif', 'selesai', 'nonaktif'];
    const BULK_MAX_ROWS = 50;
    const BULK_PESERTA_MAX_ROWS = 200;

    public function __construct()
    {
        $this->event = new EventPendaftaran();
        $this->peserta = new EventPendaftaranPeserta();
        $this->pertandingan = new Pertandingan();
        $this->masterPersyaratan = new MasterPersyaratanEvent();
        $this->news = new News();
        $this->perkumpulan = new Perkumpulan();
        $this->admin = new Admin();
        $this->user = new User();
    }

    /** Only keys that exist as an active row in m_master_persyaratan_event are valid. */
    private function decodeRequiredFields($raw){
        $validKeys = array_keys($this->masterPersyaratan->getActiveKeyed());
        if(is_array($raw)) return array_values(array_intersect($raw, $validKeys));
        $decoded = json_decode($raw, true);
        if(!is_array($decoded)) return [];
        return array_values(array_intersect($decoded, $validKeys));
    }

    /** Decode required_fields JSON on a list row (list rows already have encoded ids). */
    private function formatListRow($row){
        $row->required_fields = json_decode($row->required_fields, true) ?? [];
        $row->checkin_enabled = (bool) ($row->checkin_enabled ?? false);
        return $row;
    }

    /**
     * Attaches `persyaratan_definitions` (label/tipe_input/sumber/profil_field_map
     * per key in required_fields) to an event/peserta row that already has a
     * decoded (array) `required_fields`, so clients can render a generic
     * checklist/detail without a second round-trip.
     */
    private function attachPersyaratanDefinitions($row){
        $keyed = $this->masterPersyaratan->getActiveKeyed();
        $row->persyaratan_definitions = array_values(array_map(function($key) use ($keyed){
            $def = $keyed[$key] ?? null;
            return [
                'key' => $key,
                'label' => $def->label ?? $key,
                'tipe_input' => $def->tipe_input ?? 'text',
                'sumber' => $def->sumber ?? 'custom',
                'profil_field_map' => $def->profil_field_map ?? null,
            ];
        }, $row->required_fields));
        return $row;
    }

    /** Decode data_persyaratan JSON on a peserta row (client- or internal-shaped). */
    private function decodePesertaJson($row){
        if ($row === null) return null;
        $row->required_fields = json_decode($row->required_fields, true) ?? [];
        $row->data_persyaratan = json_decode($row->data_persyaratan, true) ?? [];
        $row->riwayat_penolakan = json_decode($row->riwayat_penolakan, true) ?? [];
        return $row;
    }

    private function myPerkumpulanRows($id_user){
        return DB::select(
            'SELECT DISTINCT
                p.id,
                md5(concat(p.id, "P3RKUMP")) AS id_enc,
                p.no_perkumpulan,
                p.nama
            FROM m_perkumpulan p
            WHERE p.id IN (
                SELECT id_perkumpulan FROM m_pengajuan_pemain WHERE id_user = ? AND status = 2 AND id_perkumpulan IS NOT NULL
                UNION
                SELECT id_perkumpulan FROM m_pengajuan_nonaggota WHERE id_user = ? AND status = 2 AND id_perkumpulan IS NOT NULL
            )
            ORDER BY p.nama ASC
            ', [$id_user, $id_user]
        );
    }

    // ==================== ADMIN: EVENT CONFIG ====================

    private function isValidDate($d){
        $dt = \DateTime::createFromFormat('Y-m-d', (string) $d);
        return $dt && $dt->format('Y-m-d') === $d;
    }

    /**
     * Validates + normalizes ONE event payload (shared by create and
     * bulk-create). Returns [fields, errors]: `errors` is a list of
     * ['field', 'message', 'code']; `fields` is only meaningful when empty.
     * $src['checkin'] = ['enabled' => bool, 'jam_checkin' => 'HH:mm'] is optional.
     */
    private function normalizeEventInput(array $src, $requireJamMulai = false){
        $errors = [];
        $f = [
            'nama_event' => trim((string) ($src['nama_event'] ?? '')),
            'tanggal_mulai' => (string) ($src['tanggal_mulai'] ?? ''),
            'tanggal_selesai' => (string) ($src['tanggal_selesai'] ?? ''),
            'jam_mulai' => null,
            'skema_pendaftaran' => (string) ($src['skema_pendaftaran'] ?? ''),
            'required_fields' => $this->decodeRequiredFields($src['required_fields'] ?? []),
            'lokasi' => $src['lokasi'] ?? null,
            'biaya_pendaftaran' => !empty($src['biaya_pendaftaran']) ? $src['biaya_pendaftaran'] : null,
            'status' => !empty($src['status']) ? $src['status'] : 'draft',
            'id_perkumpulan_list' => is_array($src['id_perkumpulan_list'] ?? null) ? $src['id_perkumpulan_list'] : [],
            'checkin' => null,
        ];

        if ($f['nama_event'] === '') $errors[] = ['field' => 'nama_event', 'message' => 'Nama event harus diisi', 'code' => 98];
        if (!$this->isValidDate($f['tanggal_mulai'])) {
            $errors[] = ['field' => 'tanggal_mulai', 'message' => 'Tanggal mulai tidak valid', 'code' => 98];
        }
        if (!$this->isValidDate($f['tanggal_selesai'])) {
            $errors[] = ['field' => 'tanggal_selesai', 'message' => 'Tanggal selesai tidak valid', 'code' => 98];
        } elseif ($this->isValidDate($f['tanggal_mulai']) && $f['tanggal_selesai'] < $f['tanggal_mulai']) {
            $errors[] = ['field' => 'tanggal_selesai', 'message' => 'Tanggal selesai tidak boleh sebelum tanggal mulai', 'code' => 98];
        }
        if (!in_array($f['skema_pendaftaran'], self::SKEMA_OPTIONS)) {
            $errors[] = ['field' => 'skema_pendaftaran', 'message' => 'Skema pendaftaran tidak valid', 'code' => 98];
        }
        if (!in_array($f['status'], self::STATUS_OPTIONS)) {
            $errors[] = ['field' => 'status', 'message' => 'Status tidak valid', 'code' => 98];
        }

        if (!empty($src['jam_mulai'])) {
            $f['jam_mulai'] = $this->pertandingan->normalizeTime($src['jam_mulai']);
            if ($f['jam_mulai'] === null) $errors[] = ['field' => 'jam_mulai', 'message' => 'Format jam mulai tidak valid (HH:mm)', 'code' => 98];
        } elseif ($requireJamMulai) {
            $errors[] = ['field' => 'jam_mulai', 'message' => 'Jam mulai harus diisi', 'code' => 98];
        }

        $checkin = $src['checkin'] ?? null;
        if (is_array($checkin) && (!empty($checkin['jam_checkin']) || !empty($checkin['enabled']))) {
            $enabled = filter_var($checkin['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $jamCheckin = $this->pertandingan->normalizeTime($checkin['jam_checkin'] ?? null);
            if ($f['jam_mulai'] === null || $jamCheckin === null) {
                $errors[] = ['field' => 'jam_checkin', 'message' => 'Jam mulai dan jam check-in harus diisi', 'code' => 98];
            } elseif ($this->isValidDate($f['tanggal_mulai'])
                && !$this->pertandingan->validateCheckinGap($f['tanggal_mulai'].' '.$f['jam_mulai'], $f['tanggal_mulai'].' '.$jamCheckin)) {
                $errors[] = ['field' => 'jam_checkin', 'message' => ResponseController::getErrorMessage(93), 'code' => 93];
            } else {
                $f['checkin'] = ['enabled' => $enabled, 'jam_checkin' => $jamCheckin];
            }
        }

        return [$f, $errors];
    }

    /** Resolves + validates the banner/berita a batch or single event hangs off. Returns [rawNewsId|null, errorCode|null]. */
    private function resolveNewsForPlacement($id_news_enc, $tipe_penempatan){
        if (empty($id_news_enc)) return [null, null];
        $newsRow = $this->news->getNewsByEncId($id_news_enc);
        // Placement "banner" must resolve to a news row flagged as banner;
        // "berita" accepts any news row (validated CTA behaviour is identical either way).
        if (!$newsRow || ($tipe_penempatan === 'banner' && (int) $newsRow->is_banner !== 1)) {
            return [null, 88];
        }
        return [$newsRow->id, null];
    }

    /** Encoded perkumpulan ids -> raw ids (unknown ids dropped). $cache is reused across bulk rows. */
    private function resolvePerkumpulanIds(array $encIds, array &$cache){
        $raw = [];
        foreach ($encIds as $encId) {
            if (!array_key_exists($encId, $cache)) {
                $p = $this->perkumpulan->getPerkumpulanByEncId($encId);
                $cache[$encId] = $p ? $p->id : null;
            }
            if ($cache[$encId] !== null) $raw[] = $cache[$encId];
        }
        return $raw;
    }

    /** Inserts one validated event (+ allowed perkumpulan + check-in session). Caller owns the transaction. Returns the raw id. */
    private function insertEvent(array $f, $id_news, $tipe_penempatan, $id_admin, array $rawPerkumpulanIds){
        $id = DB::table('m_event_pendaftaran')->insertGetId([
            'id_news' => $id_news,
            'tipe_penempatan' => $tipe_penempatan,
            'nama_event' => $f['nama_event'],
            'tanggal_mulai' => $f['tanggal_mulai'],
            'tanggal_selesai' => $f['tanggal_selesai'],
            'jam_mulai' => $f['jam_mulai'],
            'lokasi' => $f['lokasi'],
            'biaya_pendaftaran' => $f['biaya_pendaftaran'],
            'skema_pendaftaran' => $f['skema_pendaftaran'],
            'required_fields' => json_encode($f['required_fields']),
            'status' => $f['status'],
            'id_admin' => $id_admin,
        ]);

        if ($f['skema_pendaftaran'] === 'mitra_dan_anggota') {
            $this->event->setAllowedPerkumpulan($id, $rawPerkumpulanIds);
        }

        if ($f['checkin'] !== null) {
            $this->pertandingan->saveForEvent(
                (object) ['id' => $id, 'tanggal_mulai' => $f['tanggal_mulai']],
                $f['nama_event'], $f['jam_mulai'], $f['checkin']['jam_checkin'], $f['checkin']['enabled'], $id_admin
            );
        }
        return $id;
    }

    public function createEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $tipe_penempatan = $request->tipe_penempatan ?: 'banner';
        if (!in_array($tipe_penempatan, self::TIPE_PENEMPATAN_OPTIONS)) {
            return ResponseController::sendError(98);
        }

        [$f, $errors] = $this->normalizeEventInput($request->all());
        if (!empty($errors)) {
            return ResponseController::sendError($errors[0]['code']);
        }

        try {
            $adminRow = !empty($request->id_admin) ? $this->admin->getAdminByUserEncId($request->id_admin) : null;
            $id_admin = $adminRow ? $adminRow->id : null;

            [$id_news, $newsErr] = $this->resolveNewsForPlacement($request->id_news, $tipe_penempatan);
            if ($newsErr) return ResponseController::sendError($newsErr);

            $cache = [];
            $rawPerk = $this->resolvePerkumpulanIds($f['id_perkumpulan_list'], $cache);

            DB::beginTransaction();
            $id = $this->insertEvent($f, $id_news, $tipe_penempatan, $id_admin, $rawPerk);
            DB::commit();

            return ResponseController::sendSuccess([
                'message' => 'Event berhasil dibuat',
                'data' => ['id' => md5($id . $this->event->imbuhan)]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk insert: ONE banner/berita (`id_news`, validated once) + many events.
     * Each `events[]` item is fully materialized by the client (no server-side
     * defaults). All-or-nothing: any invalid row rejects the whole batch with
     * status 96 and data.errors = [{index, field, message}], nothing written.
     */
    public function bulkCreateEvents(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $tipe_penempatan = $request->tipe_penempatan ?: 'banner';
        $events = $request->events;
        if (!in_array($tipe_penempatan, self::TIPE_PENEMPATAN_OPTIONS) || empty($request->id_news)
            || !is_array($events) || count($events) === 0) {
            return ResponseController::sendError(98);
        }
        if (count($events) > self::BULK_MAX_ROWS) {
            return ResponseController::sendErrorData(98, ['field' => 'Maksimal '.self::BULK_MAX_ROWS.' jadwal per sekali simpan']);
        }

        try {
            [$id_news, $newsErr] = $this->resolveNewsForPlacement($request->id_news, $tipe_penempatan);
            if ($newsErr) return ResponseController::sendError($newsErr);

            $adminRow = !empty($request->id_admin) ? $this->admin->getAdminByUserEncId($request->id_admin) : null;
            $id_admin = $adminRow ? $adminRow->id : null;

            $rows = [];
            $allErrors = [];
            $cache = [];
            foreach (array_values($events) as $i => $ev) {
                [$f, $errors] = $this->normalizeEventInput(is_array($ev) ? $ev : [], true);
                foreach ($errors as $e) {
                    $allErrors[] = ['index' => $i, 'field' => $e['field'], 'message' => $e['message']];
                }

                // Restricted-to-chosen-perkumpulan events need at least one real
                // perkumpulan; otherwise nobody could ever register. Unknown ids
                // are reported instead of being silently dropped.
                if ($f['skema_pendaftaran'] === 'mitra_dan_anggota') {
                    $encIds = array_values(array_unique($f['id_perkumpulan_list']));
                    if (empty($encIds)) {
                        $allErrors[] = ['index' => $i, 'field' => 'id_perkumpulan_list', 'message' => 'Pilih minimal satu perkumpulan'];
                    } elseif (count($this->resolvePerkumpulanIds($encIds, $cache)) !== count($encIds)) {
                        $allErrors[] = ['index' => $i, 'field' => 'id_perkumpulan_list', 'message' => 'Ada perkumpulan yang tidak ditemukan'];
                    }
                }
                $rows[$i] = $f;
            }
            if (!empty($allErrors)) {
                return ResponseController::sendErrorData(96, ['errors' => $allErrors]);
            }

            DB::beginTransaction();
            $ids = [];
            foreach ($rows as $f) {
                $rawPerk = $this->resolvePerkumpulanIds($f['id_perkumpulan_list'], $cache);
                $ids[] = md5($this->insertEvent($f, $id_news, $tipe_penempatan, $id_admin, $rawPerk) . $this->event->imbuhan);
            }
            DB::commit();

            return ResponseController::sendSuccess([
                'message' => count($ids).' jadwal berhasil dibuat',
                'data' => ['ids' => $ids]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /** Applies one validated+materialized row (from normalizeEventInput) onto an EXISTING event. Caller owns the transaction. */
    private function applyEventUpdate(array $f, $row, $tipe_penempatan, array $rawPerkumpulanIds){
        DB::table('m_event_pendaftaran')->where('id', $row->id)->update([
            'nama_event' => $f['nama_event'],
            'tanggal_mulai' => $f['tanggal_mulai'],
            'tanggal_selesai' => $f['tanggal_selesai'],
            'jam_mulai' => $f['jam_mulai'],
            'lokasi' => $f['lokasi'],
            'biaya_pendaftaran' => $f['biaya_pendaftaran'],
            'skema_pendaftaran' => $f['skema_pendaftaran'],
            'tipe_penempatan' => $tipe_penempatan,
            'required_fields' => json_encode($f['required_fields']),
            'status' => $f['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($f['skema_pendaftaran'] === 'mitra_dan_anggota') {
            $this->event->setAllowedPerkumpulan($row->id, $rawPerkumpulanIds);
        } else {
            $this->event->setAllowedPerkumpulan($row->id, []);
        }

        if ($f['checkin'] !== null) {
            // Payload explicitly carries a check-in config: (re)create the session.
            $this->pertandingan->saveForEvent(
                (object) ['id' => $row->id, 'tanggal_mulai' => $f['tanggal_mulai']],
                $f['nama_event'], $f['jam_mulai'], $f['checkin']['jam_checkin'], $f['checkin']['enabled']
            );
        } elseif ($f['jam_mulai'] !== null) {
            // No check-in in this bulk-edit payload: PRESERVE whatever session
            // already exists (re-anchored to the possibly-changed date/start
            // time) instead of disabling it — same behaviour as the single
            // updateEvent. Bulk-edit never silently kills a running check-in.
            $this->pertandingan->resyncWithEvent($row->id, $f['tanggal_mulai'], $f['jam_mulai']);
        }
    }

    /**
     * Bulk update: ONE banner/berita (`id_news`) + many EXISTING events that
     * already belong to it. Mirrors bulkCreateEvents (client sends fully
     * materialized rows, all-or-nothing) except each `events[]` item must
     * carry an `id` (encoded) resolving to an event of this same `id_news` —
     * editing an event that belongs to a different banner/berita this way is
     * rejected per-row instead of silently reparenting it.
     */
    public function bulkUpdateEvents(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $tipe_penempatan = $request->tipe_penempatan ?: 'banner';
        $events = $request->events;
        if (!in_array($tipe_penempatan, self::TIPE_PENEMPATAN_OPTIONS) || empty($request->id_news)
            || !is_array($events) || count($events) === 0) {
            return ResponseController::sendError(98);
        }
        if (count($events) > self::BULK_MAX_ROWS) {
            return ResponseController::sendErrorData(98, ['field' => 'Maksimal '.self::BULK_MAX_ROWS.' jadwal per sekali simpan']);
        }

        try {
            [$id_news, $newsErr] = $this->resolveNewsForPlacement($request->id_news, $tipe_penempatan);
            if ($newsErr) return ResponseController::sendError($newsErr);

            $rows = [];
            $allErrors = [];
            $cache = [];
            foreach (array_values($events) as $i => $ev) {
                $ev = is_array($ev) ? $ev : [];

                $row = !empty($ev['id']) ? $this->event->getEventByEncId($ev['id']) : null;
                if (!$row) {
                    $allErrors[] = ['index' => $i, 'field' => 'id', 'message' => 'Event tidak ditemukan'];
                    continue;
                }
                if ((int) $row->id_news !== (int) $id_news) {
                    $allErrors[] = ['index' => $i, 'field' => 'id', 'message' => 'Event ini bukan bagian dari banner/berita yang dipilih'];
                    continue;
                }

                [$f, $errors] = $this->normalizeEventInput($ev, true);
                foreach ($errors as $e) {
                    $allErrors[] = ['index' => $i, 'field' => $e['field'], 'message' => $e['message']];
                }

                if ($f['skema_pendaftaran'] === 'mitra_dan_anggota') {
                    $encIds = array_values(array_unique($f['id_perkumpulan_list']));
                    if (empty($encIds)) {
                        $allErrors[] = ['index' => $i, 'field' => 'id_perkumpulan_list', 'message' => 'Pilih minimal satu perkumpulan'];
                    } elseif (count($this->resolvePerkumpulanIds($encIds, $cache)) !== count($encIds)) {
                        $allErrors[] = ['index' => $i, 'field' => 'id_perkumpulan_list', 'message' => 'Ada perkumpulan yang tidak ditemukan'];
                    }
                }
                $rows[$i] = ['f' => $f, 'row' => $row];
            }
            if (!empty($allErrors)) {
                return ResponseController::sendErrorData(96, ['errors' => $allErrors]);
            }

            DB::beginTransaction();
            $ids = [];
            foreach ($rows as $r) {
                $rawPerk = $this->resolvePerkumpulanIds($r['f']['id_perkumpulan_list'], $cache);
                $this->applyEventUpdate($r['f'], $r['row'], $tipe_penempatan, $rawPerk);
                $ids[] = md5($r['row']->id . $this->event->imbuhan);
            }
            DB::commit();

            return ResponseController::sendSuccess([
                'message' => count($ids).' jadwal berhasil diperbarui',
                'data' => ['ids' => $ids]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /** Admin: every non-deleted event of one banner/berita with participant + attendance counts. */
    public function getListByNews(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        if (empty($request->id_news)) return ResponseController::sendError(98);

        try {
            $newsRow = $this->news->getNewsByEncId($request->id_news);
            if (!$newsRow) return ResponseController::sendError(48);

            return ResponseController::sendSuccess([
                'message' => 'List jadwal berhasil diambil',
                'data' => $this->event->getEventsByNewsId($newsRow->id)
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function getListEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            $rows = $this->event->getEventList($request->keyword, $request->status);
            $rows = array_map([$this, 'formatListRow'], $rows);
            $paged = GlobalHelperController::paginate($rows, $request);

            return ResponseController::sendSuccess([
                'message' => 'List event berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function getDetailEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->event->getEventByEncId($id); // raw ids
            if (!$row) return ResponseController::sendError(80);

            $rawId = $row->id;
            $row->required_fields = json_decode($row->required_fields, true) ?? [];
            $row->allowed_perkumpulan = $this->event->getAllowedPerkumpulan($rawId);
            $this->attachPersyaratanDefinitions($row);
            $this->event->encodeForClient($row);

            return ResponseController::sendSuccess([
                'message' => 'Detail event berhasil diambil',
                'data' => $row
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function updateEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->event->getEventByEncId($id); // raw ids
            if (!$row) return ResponseController::sendError(80);

            $skema_pendaftaran = $request->skema_pendaftaran ?: $row->skema_pendaftaran;
            if (!in_array($skema_pendaftaran, self::SKEMA_OPTIONS)) {
                return ResponseController::sendError(98);
            }
            $tipe_penempatan = $request->tipe_penempatan ?: $row->tipe_penempatan;
            if (!in_array($tipe_penempatan, self::TIPE_PENEMPATAN_OPTIONS)) {
                return ResponseController::sendError(98);
            }

            $newTanggalMulai = $request->tanggal_mulai ?: $row->tanggal_mulai;
            $newTanggalSelesai = $request->tanggal_selesai ?: $row->tanggal_selesai;
            if (!$this->isValidDate($newTanggalMulai) || !$this->isValidDate($newTanggalSelesai)
                || $newTanggalSelesai < $newTanggalMulai) {
                return ResponseController::sendError(98);
            }

            // jam_mulai: keep the stored value unless the client sends one.
            $newJamMulai = $row->jam_mulai ? $this->pertandingan->normalizeTime($row->jam_mulai) : null;
            if (!empty($request->jam_mulai)) {
                $newJamMulai = $this->pertandingan->normalizeTime($request->jam_mulai);
                if ($newJamMulai === null) return ResponseController::sendError(98);
            }

            // Optional atomic check-in config: {enabled, jam_checkin}.
            $checkin = null;
            $reqCheckin = $request->checkin;
            if (is_array($reqCheckin) && (!empty($reqCheckin['jam_checkin']) || !empty($reqCheckin['enabled']))) {
                $checkinTime = $this->pertandingan->normalizeTime($reqCheckin['jam_checkin'] ?? null);
                if ($newJamMulai === null || $checkinTime === null) return ResponseController::sendError(98);
                if (!$this->pertandingan->validateCheckinGap($newTanggalMulai.' '.$newJamMulai, $newTanggalMulai.' '.$checkinTime)) {
                    return ResponseController::sendError(93);
                }
                $checkin = [
                    'enabled' => filter_var($reqCheckin['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'jam_checkin' => $checkinTime,
                ];
            }

            $update = [
                'nama_event' => $request->nama_event ?: $row->nama_event,
                'tanggal_mulai' => $newTanggalMulai,
                'tanggal_selesai' => $newTanggalSelesai,
                'jam_mulai' => $newJamMulai,
                'lokasi' => $request->has('lokasi') ? $request->lokasi : $row->lokasi,
                'biaya_pendaftaran' => $request->has('biaya_pendaftaran') ? $request->biaya_pendaftaran : $row->biaya_pendaftaran,
                'skema_pendaftaran' => $skema_pendaftaran,
                'tipe_penempatan' => $tipe_penempatan,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if (!empty($request->id_news)) {
                $newsRow = $this->news->getNewsByEncId($request->id_news);
                if (!$newsRow || ($tipe_penempatan === 'banner' && (int) $newsRow->is_banner !== 1)) {
                    return ResponseController::sendError(88);
                }
                $update['id_news'] = $newsRow->id;
            }
            if ($request->has('required_fields')) {
                $update['required_fields'] = json_encode($this->decodeRequiredFields($request->required_fields));
            }
            if ($request->has('status') && !empty($request->status)) {
                $update['status'] = $request->status;
            }

            DB::beginTransaction();
            DB::table('m_event_pendaftaran')->where('id', $row->id)->update($update);

            if ($checkin !== null) {
                $code = $this->pertandingan->saveForEvent(
                    (object) ['id' => $row->id, 'tanggal_mulai' => $newTanggalMulai],
                    $update['nama_event'], $newJamMulai, $checkin['jam_checkin'], $checkin['enabled']
                );
                if ($code !== null) { DB::rollBack(); return ResponseController::sendError($code); }
            } elseif ($newJamMulai !== null) {
                // No new check-in config sent: just keep the existing session
                // anchored to the (possibly moved) date / start time.
                $code = $this->pertandingan->resyncWithEvent($row->id, $newTanggalMulai, $newJamMulai);
                if ($code !== null) { DB::rollBack(); return ResponseController::sendError($code); }
            }

            if ($skema_pendaftaran === 'mitra_dan_anggota' && is_array($request->id_perkumpulan_list)) {
                $rawIds = [];
                foreach ($request->id_perkumpulan_list as $encId) {
                    $p = $this->perkumpulan->getPerkumpulanByEncId($encId);
                    if ($p) $rawIds[] = $p->id;
                }
                $this->event->setAllowedPerkumpulan($row->id, $rawIds);
            } elseif ($skema_pendaftaran === 'anggota') {
                $this->event->setAllowedPerkumpulan($row->id, []);
            }

            DB::commit();

            return ResponseController::sendSuccess(['message' => 'Event berhasil diperbarui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function updateStatusEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $status = $request->status;
        if (empty($id) || empty($status)) return ResponseController::sendError(98);

        try {
            $row = $this->event->getEventByEncId($id);
            if (!$row) return ResponseController::sendError(80);

            DB::table('m_event_pendaftaran')->where('id', $row->id)->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ResponseController::sendSuccess(['message' => 'Status event berhasil diperbarui']);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function deleteEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->event->getEventByEncId($id);
            if (!$row) return ResponseController::sendError(80);

            DB::table('m_event_pendaftaran')->where('id', $row->id)->update([
                'status' => 'nonaktif',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ResponseController::sendSuccess(['message' => 'Event berhasil dihapus']);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // ==================== MEMBER: BROWSE & REGISTER ====================

    public function detailEventForMember(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->event->getEventByEncId($id); // raw ids
            if (!$row) return ResponseController::sendError(80);
            $rawId = $row->id;
            $row->required_fields = json_decode($row->required_fields, true) ?? [];
            $this->attachPersyaratanDefinitions($row);

            // Perkumpulan the member is an approved member of.
            $myPerkumpulan = $this->myPerkumpulanRows($currentUser["user_id"]);

            if ($row->skema_pendaftaran === 'mitra_dan_anggota') {
                $allowedIds = $this->event->getAllowedPerkumpulanIds($rawId);
                $myPerkumpulan = array_values(array_filter($myPerkumpulan, function ($p) use ($allowedIds) {
                    return in_array((int) $p->id, $allowedIds);
                }));
            }

            $myPerkumpulan = array_map(function ($p) {
                return [
                    'id' => $p->id_enc,
                    'no_perkumpulan' => $p->no_perkumpulan,
                    'nama' => $p->nama,
                ];
            }, $myPerkumpulan);

            $existing = $this->peserta->getByUserAndEvent($currentUser["user_id"], $rawId);
            $this->decodePesertaJson($existing);
            if ($existing) unset($existing->id_admin_approval);
            $this->peserta->encodeForClient($existing);

            $this->event->encodeForClient($row);

            return ResponseController::sendSuccess([
                'message' => 'Detail event berhasil diambil',
                'data' => [
                    'event' => $row,
                    'my_perkumpulan' => $myPerkumpulan,
                    'registration' => $existing,
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function myEventList(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        try {
            $rows = $this->peserta->getMyEventList($currentUser['user_id'], $request->keyword);
            $paged = GlobalHelperController::paginate($rows, $request);

            return ResponseController::sendSuccess([
                'message' => 'List event saya berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function uploadFilePeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $request->validate([
            'uploaded_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $filename = $request->filename ?: 'noname';
        $file = $request->file('uploaded_file');
        $file->move('event-peserta', $filename);

        return ResponseController::sendSuccess(['message' => 'success']);
    }

    public function daftarPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $id_event_enc = $request->id_event;
        $id_perkumpulan_enc = $request->id_perkumpulan;
        if (empty($id_event_enc)) return ResponseController::sendError(98);

        try {
            $event = $this->event->getEventByEncId($id_event_enc); // raw ids
            if (!$event) return ResponseController::sendError(80);
            if ($event->status !== 'aktif') return ResponseController::sendError(81);

            $existing = $this->peserta->getByUserAndEvent($currentUser["user_id"], $event->id);
            // Only a rejected registration can be resubmitted; anything menunggu/diterima blocks a second submit.
            if ($existing && (int) $existing->status !== -1) return ResponseController::sendError(84);

            $id_perkumpulan = null;
            if (!empty($id_perkumpulan_enc)) {
                $p = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulan_enc);
                if (!$p) return ResponseController::sendError(4);
                $id_perkumpulan = $p->id;

                if ($event->skema_pendaftaran === 'mitra_dan_anggota') {
                    if (!$this->event->isPerkumpulanAllowed($event->id, $id_perkumpulan)) {
                        return ResponseController::sendError(83);
                    }
                }
            } elseif ($event->skema_pendaftaran === 'mitra_dan_anggota') {
                return ResponseController::sendError(82);
            }

            $required = json_decode($event->required_fields, true) ?? [];
            $data_persyaratan = is_array($request->data_persyaratan) ? $request->data_persyaratan : [];

            $missing = [];
            foreach ($required as $key) {
                if ($key === 'mitra_perkumpulan') {
                    if (empty($id_perkumpulan)) $missing[] = $key;
                    continue;
                }
                $value = $data_persyaratan[$key] ?? null;
                if ($value === null || $value === '') $missing[] = $key;
            }

            if (!empty($missing)) {
                return ResponseController::sendErrorData(85, ['missing_fields' => $missing]);
            }

            // Keep only values for currently-required keys, so a since-removed
            // requirement doesn't linger in stored data indefinitely.
            $data_persyaratan = array_intersect_key($data_persyaratan, array_flip($required));

            if ($existing) {
                // Resubmission after rejection: reuse the same row so "have I registered
                // for this event" stays a single lookup, and keep the old verdict as history.
                $riwayat = json_decode($existing->riwayat_penolakan, true) ?? [];
                $riwayat[] = [
                    'catatan' => $existing->catatan,
                    'id_admin_approval' => $existing->id_admin_approval ?? null,
                    'tgl_approval' => $existing->tgl_approval,
                ];

                DB::table('m_event_pendaftaran_peserta')->where('id', $existing->id)->update([
                    'id_perkumpulan' => $id_perkumpulan,
                    'data_persyaratan' => json_encode($data_persyaratan),
                    'status' => 0,
                    'catatan' => null,
                    'jumlah_pengajuan' => (int) $existing->jumlah_pengajuan + 1,
                    'riwayat_penolakan' => json_encode($riwayat),
                    'id_admin_approval' => null,
                    'tgl_daftar' => date('Y-m-d H:i:s'),
                    'tgl_approval' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                DB::table('m_event_pendaftaran_peserta')->insert([
                    'id_event_pendaftaran' => $event->id,
                    'id_user' => $currentUser["user_id"],
                    'id_perkumpulan' => $id_perkumpulan,
                    'data_persyaratan' => json_encode($data_persyaratan),
                    'status' => 0,
                    'jumlah_pengajuan' => 1,
                ]);
            }

            return ResponseController::sendSuccess(['message' => 'Pendaftaran berhasil dikirim, menunggu verifikasi']);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function statusPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $id_event_enc = $request->id_event;
        if (empty($id_event_enc)) return ResponseController::sendError(98);

        try {
            $event = $this->event->getEventByEncId($id_event_enc);
            if (!$event) return ResponseController::sendError(80);

            $existing = $this->peserta->getByUserAndEvent($currentUser["user_id"], $event->id);
            $this->decodePesertaJson($existing);
            if ($existing) { $this->attachPersyaratanDefinitions($existing); unset($existing->id_admin_approval); }
            $this->peserta->encodeForClient($existing);

            return ResponseController::sendSuccess([
                'message' => 'Status pendaftaran berhasil diambil',
                'data' => $existing
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // ==================== ADMIN: APPROVAL & REPORT ====================

    public function getListPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_event = null;
        if (!empty($request->id_event)) {
            $event = $this->event->getEventByEncId($request->id_event);
            if (!$event) return ResponseController::sendError(80);
            $id_event = $event->id;
        }

        try {
            $rows = $this->peserta->getListPeserta($id_event, $request->status, $request->keyword);
            foreach ($rows as $row) {
                $row->required_fields = json_decode($row->required_fields, true) ?? [];
                $row->data_persyaratan = json_decode($row->data_persyaratan, true) ?? [];
                $row->riwayat_penolakan = json_decode($row->riwayat_penolakan, true) ?? [];
                $this->attachPersyaratanDefinitions($row);
            }
            $paged = GlobalHelperController::paginate($rows, $request);

            return ResponseController::sendSuccess([
                'message' => 'List peserta berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function getDetailPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);

        try {
            $row = $this->peserta->getPesertaByEncId($id); // raw ids
            if (!$row) return ResponseController::sendError(86);
            $this->decodePesertaJson($row);
            $this->attachPersyaratanDefinitions($row);
            unset($row->id_admin_approval);
            $this->peserta->encodeForClient($row);

            return ResponseController::sendSuccess([
                'message' => 'Detail peserta berhasil diambil',
                'data' => $row
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function approvePeserta(Request $request)
    {
        return $this->processApproval($request, 1);
    }

    public function rejectPeserta(Request $request)
    {
        return $this->processApproval($request, -1);
    }

    private function processApproval(Request $request, $newStatus)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $id = $request->id;
        if (empty($id)) return ResponseController::sendError(98);
        if ($newStatus === -1 && empty($request->catatan)) {
            return ResponseController::sendErrorData(98, ['field' => 'catatan is required when rejecting']);
        }

        try {
            $row = $this->peserta->getPesertaByEncId($id); // raw ids
            if (!$row) return ResponseController::sendError(86);
            if ($row->status != 0) return ResponseController::sendError(87);

            DB::table('m_event_pendaftaran_peserta')->where('id', $row->id)->update([
                'status' => $newStatus,
                'catatan' => $request->catatan,
                'id_admin_approval' => $this->adminIdFromJwt($currentUser),
                'tgl_approval' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $user = User::find($row->id_user);
            $tokens = $user ? User::notifTokens($user) : [];
            if (!empty($tokens)) {
                $title = User::notifTitle($user, $newStatus === 1 ? 'Pendaftaran Event Diterima' : 'Pendaftaran Event Ditolak');
                $body = $newStatus === 1
                    ? 'Pendaftaran anda pada event "'.$row->nama_event.'" telah diterima.'
                    : 'Pendaftaran anda pada event "'.$row->nama_event.'" ditolak. '.($request->catatan ?: '');
                GlobalHelperController::sendNotif($tokens, $title, $body);
            }

            return ResponseController::sendSuccess([
                'message' => $newStatus === 1 ? 'Peserta berhasil diterima' : 'Peserta berhasil ditolak'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function reportPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_event_enc = $request->id_event;
        if (empty($id_event_enc)) return ResponseController::sendError(98);

        try {
            $event = $this->event->getEventByEncId($id_event_enc);
            if (!$event) return ResponseController::sendError(80);

            $report = $this->peserta->getReportByPerkumpulan($event->id);

            return ResponseController::sendSuccess([
                'message' => 'Laporan peserta berhasil diambil',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // ==================== ADMIN: IMPORT / TAMBAH PESERTA ====================

    /** m_admin.id of the JWT user (the token carries the RAW m_user id). */
    private function adminIdFromJwt($currentUser){
        $row = DB::table('m_admin')
            ->where('id_user', $currentUser['user_id'] ?? 0)
            ->where('status', 1)
            ->first();
        return $row ? $row->id : null;
    }

    /** "+62 812-3456-7890" / "812..." -> "0812...". Null when it is not a 10-15 digit number. */
    private function normalizeNoHp($raw){
        $d = preg_replace('/[\s\-().]/', '', (string) $raw);
        if (strpos($d, '+') === 0) $d = substr($d, 1);
        if (!ctype_digit($d)) return null;
        if (strpos($d, '62') === 0) $d = '0'.substr($d, 2);
        elseif (strpos($d, '8') === 0) $d = '0'.$d;
        return preg_match('/^0\d{9,14}$/', $d) ? $d : null;
    }

    /** Approved (status 2) perkumpulan memberships, as [raw id_user => [raw id_perkumpulan, ...]]. */
    private function approvedMemberships(array $userIds){
        if (empty($userIds)) return [];
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::select(
            'SELECT id_user, id_perkumpulan FROM m_pengajuan_pemain
                WHERE status = 2 AND id_perkumpulan IS NOT NULL AND id_user IN ('.$in.')
            UNION
            SELECT id_user, id_perkumpulan FROM m_pengajuan_nonaggota
                WHERE status = 2 AND id_perkumpulan IS NOT NULL AND id_user IN ('.$in.')',
            array_merge($userIds, $userIds)
        );
        $out = [];
        foreach ($rows as $r) $out[(int) $r->id_user][] = (int) $r->id_perkumpulan;
        return $out;
    }

    /**
     * Import makes the member a pemain/SDM of the row's perkumpulan: one
     * pengajuan per posisi in the file (kategori sdm -> m_pengajuan_nonaggota,
     * else m_pengajuan_pemain), saved already approved (status 2). Fills
     * $rows[i]['_pengajuan'] (for the writer) and ['pengajuan'] (preview):
     * aksi baru = new row, approve = pending row (0/1) for the same
     * user/perkumpulan/posisi gets approved, sudah = already approved.
     */
    private function planPengajuan(array &$rows, callable $addErr){
        $userIds = [];
        foreach ($rows as $r) if ($r['_user'] && !empty($r['_posisi'])) $userIds[] = (int) $r['_user']->id;
        $userIds = array_values(array_unique($userIds));
        if (empty($userIds)) return;

        $in = implode(',', array_fill(0, count($userIds), '?'));
        $pemain = DB::select(
            'SELECT id, id_user, id_kota, id_perkumpulan, id_posisi, posisi, status FROM m_pengajuan_pemain
                WHERE status IN (0, 1, 2) AND id_user IN ('.$in.') ORDER BY id DESC',
            $userIds
        );
        $sdm = DB::select(
            'SELECT id, id_user, id_perkumpulan, id_posisi, status FROM m_pengajuan_nonaggota
                WHERE status IN (0, 1, 2) AND id_user IN ('.$in.') ORDER BY id DESC',
            $userIds
        );
        $perkIds = array_values(array_unique(array_filter(array_column($rows, 'id_perkumpulan'))));
        $perkKota = empty($perkIds) ? [] : DB::table('m_perkumpulan AS pk')
            ->join('m_entitas AS e', 'pk.id_entitas', '=', 'e.id')
            ->whereIn('pk.id', $perkIds)->pluck('e.id_kota', 'pk.id')->all();

        foreach ($rows as $i => $r) {
            if (!$r['_user'] || empty($r['_posisi'])) continue;
            $uid = (int) $r['_user']->id;
            $pid = $r['id_perkumpulan'] !== null ? (int) $r['id_perkumpulan'] : null;

            foreach ($r['_posisi'] as $pos) {
                $tipe = $pos->kategori === 'sdm' ? 'sdm' : 'pemain';
                $match = null;
                foreach ($tipe === 'sdm' ? $sdm : $pemain as $ex) {
                    if ((int) $ex->id_user !== $uid) continue;
                    if (($ex->id_perkumpulan !== null ? (int) $ex->id_perkumpulan : null) !== $pid) continue;
                    $samePosisi = (int) $ex->id_posisi === (int) $pos->id
                        || ($tipe === 'pemain' && empty($ex->id_posisi) && strcasecmp(trim((string) $ex->posisi), $pos->nama) === 0);
                    if (!$samePosisi) continue;
                    if (!$match || (int) $ex->status > (int) $match->status) $match = $ex; // approved wins
                }
                $aksi = !$match ? 'baru' : ((int) $match->status === 2 ? 'sudah' : 'approve');

                $idKota = null;
                if ($tipe === 'pemain' && $aksi === 'baru') {
                    $idKota = $pid !== null && !empty($perkKota[$pid]) ? (int) $perkKota[$pid] : null;
                    if ($idKota === null) {
                        foreach ($pemain as $ex) {
                            if ((int) $ex->id_user === $uid && !empty($ex->id_kota)) { $idKota = (int) $ex->id_kota; break; }
                        }
                    }
                    if ($idKota === null) {
                        $default = $this->defaultKotaImport();
                        if ($default) $idKota = (int) $default->id;
                        else $addErr($i, 'posisi', 'Kota untuk pengajuan pemain "'.$pos->nama.'" tidak diketahui, pilih perkumpulan');
                    }
                }

                $rows[$i]['_pengajuan'][] = [
                    'tipe' => $tipe, 'aksi' => $aksi, 'id' => $match ? (int) $match->id : null,
                    'id_posisi' => (int) $pos->id, 'posisi' => $pos->nama, 'id_kota' => $idKota,
                ];
                $rows[$i]['pengajuan'][] = ['tipe' => $tipe, 'posisi' => $pos->nama, 'aksi' => $aksi];
            }
        }
    }

    /** Writes the planned pengajuan of one imported row as approved (status 2), like approveMaster. */
    private function writePengajuan(array $r, $id_admin, $now){
        $user = $r['_user'];
        $pid = $r['id_perkumpulan'];
        foreach ($r['_pengajuan'] as $p) {
            if ($p['aksi'] === 'sudah') continue;
            $table = $p['tipe'] === 'sdm' ? 'm_pengajuan_nonaggota' : 'm_pengajuan_pemain';

            // First approved pengajuan of this kind starts the membership year (approveMaster rule).
            if (!DB::table($table)->where('id_user', $user->id)->where('status', 2)->exists()) {
                DB::table('m_user')->where('id', $user->id)
                    ->update(['tgl_expired' => date('Y-m-d', strtotime('+1 year', strtotime($now)))]);
            }

            $approval = [
                'status' => 2,
                'status_aktif' => '1',
                'tgl_apv_admin' => $now,
                'id_apr_admin' => $id_admin,
            ];
            if ($pid !== null) {
                $approval['tgl_apv_perkumpulan'] = $now;
                $approval['id_apr_perkumpulan'] = $id_admin;
            }

            if ($p['aksi'] === 'approve') {
                DB::table($table)->where('id', $p['id'])->update($approval);
            } elseif ($p['tipe'] === 'sdm') {
                DB::table($table)->insert(array_merge([
                    'id_user' => $user->id,
                    'id_perkumpulan' => $pid,
                    'tahun' => date('Y', strtotime($now)),
                    'id_posisi' => $p['id_posisi'],
                    'jabatan' => $p['posisi'],
                    'tgl_pengajuan' => $now,
                ], $approval));
            } else {
                DB::table($table)->insert(array_merge([
                    'id_user' => $user->id,
                    'tahun' => date('Y', strtotime($now)),
                    'id_kota' => $p['id_kota'],
                    'id_perkumpulan' => $pid,
                    'id_posisi' => $p['id_posisi'],
                    'posisi' => $p['posisi'],
                    'no_punggung' => $r['no_punggung'] !== '' ? $r['no_punggung'] : null,
                    'tgl_pengajuan' => $now,
                ], $approval));
            }
        }
    }

    /** Lowercased email without spaces or invisible characters (zero-width, BOM, ...) pasted along from forms. */
    private function normalizeEmail($raw){
        return strtolower(preg_replace('/[\s\p{Cf}\x{00A0}]+/u', '', (string) $raw));
    }

    /** Default kota for accounts/pengajuan the import creates: Kabupaten Pemalang. */
    private function defaultKotaImport(){
        return DB::table('m_kota')
            ->where('name', 'like', '%pemalang%')
            ->orderByRaw('full_code = "3327" DESC')->orderBy('id')
            ->first();
    }

    /**
     * Creates an account for every email in the import that has no m_user row
     * at all, unless the row's No Anggota belongs to an active member (that
     * member is used instead). nama = the part before "@",
     * no_anggota generated for Kabupaten Pemalang, password 123456.
     * Must run inside the caller's transaction; the dry run rolls it back.
     * Returns [lower(email) => true] of the created accounts.
     */
    private function createImportAccounts(array $input){
        $emails = [];
        foreach ($input as $src) {
            if (!is_array($src)) continue;
            $email = $this->normalizeEmail($src['email'] ?? '');
            if ($email === '' || trim((string) ($src['id_user'] ?? '')) !== '') continue;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $emails[$email] = trim((string) ($src['no_anggota'] ?? ''));
        }
        // A No Anggota that belongs to an active member identifies them: no new account.
        $nos = array_values(array_filter(array_unique($emails)));
        if (!empty($nos)) {
            $known = array_flip(array_map('trim', DB::table('m_user')->where('status', 1)->whereIn('no_anggota', $nos)->pluck('no_anggota')->all()));
            foreach ($emails as $email => $no) {
                if ($no !== '' && isset($known[$no])) unset($emails[$email]);
            }
        }
        if (empty($emails)) return [];

        foreach (DB::table('m_user')->whereIn('email', array_keys($emails))->pluck('email') as $taken) {
            unset($emails[strtolower(trim($taken))]); // any status: an inactive account stays an error
        }
        if (empty($emails)) return [];

        $kota = $this->defaultKotaImport();
        if (!$kota) throw new \Exception('Kota Kabupaten Pemalang tidak ditemukan di master kota');

        $password = \Illuminate\Support\Facades\Hash::make('123456');
        foreach (array_keys($emails) as $email) {
            $user = new User([
                'nama' => substr($email, 0, strpos($email, '@')),
                'email' => $email,
                'password' => $password,
                'no_anggota' => $this->user->generateNoAnggota($kota->id),
                'status' => 1,
            ]);
            $user->save();
        }
        return $emails;
    }

    /** [raw id => nama] for the given raw perkumpulan ids. */
    private function perkumpulanNames(array $ids){
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) return [];
        return DB::table('m_perkumpulan')->whereIn('id', $ids)->pluck('nama', 'id')->all();
    }

    /**
     * Validates + resolves import/manual-add rows against one event. Pure
     * read: $existingRows (every m_event_pendaftaran_peserta row of the event,
     * keyed by raw id_user) is passed in so the writer can lock them first.
     * Returns [rows, errors] where each row carries both the client-facing
     * preview and the raw values the writer needs, and errors is the flat
     * [{index, field, message}] list used by status 96.
     */
    private function resolvePesertaRows($event, array $input, array $existingRows, array $createdEmails = []){
        $isMitra = $event->skema_pendaftaran === 'mitra_dan_anggota';
        $allowedPerk = $isMitra ? $this->event->getAllowedPerkumpulanIds($event->id) : [];

        // ---- normalize raw input
        $items = [];
        foreach (array_values($input) as $i => $src) {
            $src = is_array($src) ? $src : [];
            $items[$i] = [
                'email' => $this->normalizeEmail($src['email'] ?? ''),
                'no_anggota' => trim((string) ($src['no_anggota'] ?? '')),
                'id_user_enc' => trim((string) ($src['id_user'] ?? '')),
                'no_punggung' => trim((string) ($src['no_punggung'] ?? '')),
                'posisi' => trim((string) ($src['posisi'] ?? '')),
                'no_hp' => trim((string) ($src['no_hp'] ?? '')),
                'id_perkumpulan_enc' => trim((string) ($src['id_perkumpulan'] ?? '')),
            ];
        }

        // ---- batch lookups
        $emails = array_values(array_unique(array_filter(array_column($items, 'email'))));
        $nos = array_values(array_unique(array_filter(array_column($items, 'no_anggota'))));
        $encIds = array_values(array_unique(array_filter(array_column($items, 'id_user_enc'))));
        $userCols = ['id', 'nama', 'email', 'no_anggota', 'no_hp', 'fcm_token', 'id_parents'];

        $byEmail = [];
        if (!empty($emails)) {
            foreach (DB::table('m_user')->where('status', 1)->where('tipe_akun', '<>', 'ortu')->whereIn('email', $emails)->get($userCols) as $u) {
                $byEmail[strtolower(trim($u->email))] = $u;
            }
        }
        $inactiveEmails = []; // lower(email) => true for accounts that exist but are not active (deleted = -1)
        $missingEmails = array_values(array_diff($emails, array_keys($byEmail)));
        if (!empty($missingEmails)) {
            foreach (DB::table('m_user')->where('status', '<>', 1)->whereIn('email', $missingEmails)->pluck('email') as $e) {
                $inactiveEmails[strtolower(trim($e))] = true;
            }
        }
        $byNo = []; // no_anggota => [users] (a list, so a duplicated number is detected, not guessed)
        if (!empty($nos)) {
            foreach (DB::table('m_user')->where('status', 1)->where('tipe_akun', '<>', 'ortu')->whereIn('no_anggota', $nos)->get($userCols) as $u) {
                $byNo[trim($u->no_anggota)][] = $u;
            }
        }
        $byEnc = [];
        if (!empty($encIds)) {
            $in = implode(',', array_fill(0, count($encIds), '?'));
            $rows = DB::select(
                'SELECT id, nama, email, no_anggota, no_hp, fcm_token, id_parents, md5(concat(id, "'.$this->user->imbuhan.'")) AS id_enc
                FROM m_user WHERE status = 1 AND tipe_akun <> "ortu" AND md5(concat(id, "'.$this->user->imbuhan.'")) IN ('.$in.')',
                $encIds
            );
            foreach ($rows as $u) $byEnc[$u->id_enc] = $u;
        }

        $posisiMap = []; // lower(nama) => {id, nama, kategori}
        foreach (DB::table('m_posisi')->get(['id', 'nama', 'kategori']) as $p) {
            $posisiMap[strtolower(trim($p->nama))] = $p;
        }

        $perkCache = [];
        $resolveEncPerk = function ($enc) use (&$perkCache) {
            if (!array_key_exists($enc, $perkCache)) {
                $p = $this->perkumpulan->getPerkumpulanByEncId($enc);
                $perkCache[$enc] = $p ? (int) $p->id : null;
            }
            return $perkCache[$enc];
        };

        // ---- pass 1: identity, perkumpulan, field formats
        $rows = [];
        $errors = [];
        $addErr = function ($i, $field, $msg) use (&$rows, &$errors) {
            $rows[$i]['errors'][] = ['field' => $field, 'message' => $msg];
            $errors[] = ['index' => $i, 'field' => $field, 'message' => $msg];
        };
        $addWarn = function ($i, $field, $msg) use (&$rows) {
            $rows[$i]['warnings'][] = ['field' => $field, 'message' => $msg];
        };

        $seenUser = [];
        $claimedEmails = []; // file emails already assigned to a malformed account in this batch
        foreach ($items as $i => $it) {
            $rows[$i] = ['index' => $i, 'user' => null, 'id_perkumpulan' => null, 'no_punggung' => null,
                'posisi' => null, 'no_hp' => null, 'aksi' => null, 'status_lama' => null,
                'errors' => [], 'warnings' => [], 'pengajuan' => [], 'akun_baru' => false,
                '_user' => null, '_existing' => null, '_posisi' => [], '_pengajuan' => [], '_not_member' => false, '_fix_email' => null];

            // identity
            $user = null;
            if ($it['id_user_enc'] !== '') {
                $user = $byEnc[$it['id_user_enc']] ?? null;
                if (!$user) $addErr($i, 'id_user', 'Member tidak ditemukan atau tidak aktif');
            } elseif ($it['email'] === '' && $it['no_anggota'] === '') {
                $addErr($i, 'email', 'Isi Email atau No Anggota');
            } else {
                $uEmail = $it['email'] !== '' ? ($byEmail[$it['email']] ?? null) : null;
                $uNoList = $it['no_anggota'] !== '' ? ($byNo[$it['no_anggota']] ?? []) : [];
                $isCreated = $uEmail && isset($createdEmails[$it['email']]);

                if ($uEmail && !$isCreated) {
                    // existing account by email; a No Anggota, when given, must be its own
                    if ($it['no_anggota'] === '') {
                        $user = $uEmail;
                    } elseif (empty($uNoList)) {
                        $addErr($i, 'no_anggota', 'No Anggota tidak ditemukan atau akun tidak aktif');
                    } elseif (empty(array_filter($uNoList, function ($u) use ($uEmail) { return (int) $u->id === (int) $uEmail->id; }))) {
                        $addErr($i, 'no_anggota', 'Email dan No Anggota milik member yang berbeda');
                    } else {
                        $user = $uEmail;
                    }
                } elseif ($isCreated) {
                    // account made by this import (no active account had this email or No Anggota)
                    $user = $uEmail;
                    if ($it['no_anggota'] !== '') {
                        $addWarn($i, 'no_anggota', 'No Anggota "'.$it['no_anggota'].'" tidak ditemukan, diabaikan (akun baru mendapat No Anggota baru)');
                    }
                } elseif ($it['email'] !== '' && isset($inactiveEmails[$it['email']])) {
                    $addErr($i, 'email', 'Akun dengan email ini sudah dihapus/nonaktif, aktifkan dulu atau pakai email lain');
                } elseif ($it['email'] !== '' && !filter_var($it['email'], FILTER_VALIDATE_EMAIL)) {
                    $addErr($i, 'email', 'Format email tidak valid');
                } elseif (count($uNoList) === 1) {
                    // email unknown (or empty): the member is identified by No Anggota
                    $user = $uNoList[0];
                    if ($it['email'] !== '') {
                        // A malformed account email ("nama123", "@x@gmail.com") is replaced
                        // by the file's email, which here is valid and used by no account.
                        if (!filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL) && !isset($claimedEmails[$it['email']])) {
                            $claimedEmails[$it['email']] = true;
                            $rows[$i]['_fix_email'] = $it['email'];
                            $addWarn($i, 'email', 'Email akun salah format, akan diperbarui: "'.$user->email.'" -> "'.$it['email'].'" (login memakai email baru)');
                        } else {
                            $addWarn($i, 'email', 'Email "'.$it['email'].'" tidak terdaftar, memakai akun No Anggota '.$it['no_anggota'].' (email akun: '.$user->email.')');
                        }
                    }
                } elseif (count($uNoList) > 1) {
                    $addErr($i, 'no_anggota', 'No Anggota dipakai lebih dari satu akun, isi Email yang terdaftar');
                } elseif ($it['no_anggota'] !== '') {
                    $addErr($i, 'no_anggota', 'No Anggota tidak ditemukan atau akun tidak aktif');
                } else {
                    $addErr($i, 'email', 'Email tidak terdaftar atau akun tidak aktif');
                }
            }
            if ($user && empty($rows[$i]['errors'])) {
                $uid = (int) $user->id;
                if (isset($seenUser[$uid])) {
                    $addErr($i, 'email', 'Member yang sama sudah ada di baris '.($seenUser[$uid] + 1).' (urutan data)');
                } else {
                    $seenUser[$uid] = $i;
                }
                $rows[$i]['_user'] = $user;
                $rows[$i]['akun_baru'] = isset($createdEmails[strtolower(trim($user->email))]);
                if ($rows[$i]['akun_baru']) {
                    $addWarn($i, 'email', 'Email belum terdaftar, akan dibuatkan akun baru (password 123456)');
                    $domain = substr(strrchr(strtolower($user->email), '@'), 1);
                    foreach (['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'hotmail.com', 'icloud.com'] as $known) {
                        if ($domain !== $known && levenshtein($domain, $known) <= 2) {
                            $addWarn($i, 'email', 'Domain "@'.$domain.'" mungkin salah ketik, maksudnya "@'.$known.'"?');
                            break;
                        }
                    }
                }
                $rows[$i]['user'] = [
                    'id' => md5($user->id . $this->user->imbuhan),
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'no_anggota' => $user->no_anggota,
                ];
                $rows[$i]['_existing'] = $existingRows[$uid] ?? null;
            }

            // field formats (checked even when identity failed, so one preview shows everything)
            if ($it['no_punggung'] !== '') {
                if (!preg_match('/^\d{1,2}$/', $it['no_punggung'])) {
                    $addErr($i, 'no_punggung', 'Nomor punggung harus angka 0-99');
                } else {
                    $rows[$i]['no_punggung'] = $it['no_punggung'];
                }
            }
            if ($it['posisi'] !== '') {
                // One cell may hold several posisi ("Point Guard (PG), Pelatih").
                // The whole text is tried first so a master name containing a
                // comma still matches as one posisi.
                $whole = strtolower($it['posisi']);
                $parts = isset($posisiMap[$whole])
                    ? [$it['posisi']]
                    : array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;]/', $it['posisi'])), 'strlen')));
                $found = [];
                $missing = [];
                foreach ($parts as $part) {
                    $p = $posisiMap[strtolower($part)] ?? null;
                    if ($p) $found[(int) $p->id] = $p;
                    else $missing[] = $part;
                }
                if (!empty($missing)) {
                    $addErr($i, 'posisi', 'Posisi "'.implode('", "', $missing).'" tidak ada di daftar posisi');
                } else {
                    $label = implode(', ', array_map(function ($p) { return $p->nama; }, $found));
                    if (mb_strlen($label) > 100) {
                        $addErr($i, 'posisi', 'Posisi terlalu panjang (maksimal 100 karakter)');
                    } else {
                        $rows[$i]['posisi'] = $label;
                        $rows[$i]['_posisi'] = array_values($found);
                    }
                }
            }
            if ($it['no_hp'] !== '') {
                $hp = $this->normalizeNoHp($it['no_hp']);
                if ($hp === null) $addErr($i, 'no_hp', 'No HP tidak valid (10-15 digit angka)');
                else $rows[$i]['no_hp'] = $hp;
            }

            // explicit perkumpulan (manual form / "perkumpulan default" in the import screen)
            if ($it['id_perkumpulan_enc'] !== '') {
                $pid = $resolveEncPerk($it['id_perkumpulan_enc']);
                if ($pid === null) $addErr($i, 'id_perkumpulan', 'Perkumpulan tidak ditemukan');
                elseif ($isMitra && !in_array($pid, $allowedPerk)) $addErr($i, 'id_perkumpulan', 'Perkumpulan tidak termasuk peserta event ini');
                else $rows[$i]['id_perkumpulan'] = $pid;
            }
        }

        // ---- pass 2: perkumpulan resolution (needs memberships of every resolved user)
        $userIds = [];
        foreach ($rows as $r) if ($r['_user']) $userIds[] = (int) $r['_user']->id;
        $memberships = $this->approvedMemberships(array_values(array_unique($userIds)));

        foreach ($rows as $i => $r) {
            if (!$r['_user']) continue;
            $uid = (int) $r['_user']->id;
            $mine = $memberships[$uid] ?? [];
            $ex = $r['_existing'];

            if ($r['id_perkumpulan'] !== null) {
                // Warned after pass 2b, unless the import makes them a member.
                if (!in_array($r['id_perkumpulan'], $mine)) $rows[$i]['_not_member'] = true;
            } elseif ($items[$i]['id_perkumpulan_enc'] !== '') {
                // explicit id was invalid; already reported
            } elseif ($ex && !empty($ex->id_perkumpulan)) {
                $rows[$i]['id_perkumpulan'] = (int) $ex->id_perkumpulan;
            } else {
                $candidates = $isMitra ? array_values(array_intersect($mine, $allowedPerk)) : array_values(array_unique($mine));
                if (count($candidates) === 1) {
                    $rows[$i]['id_perkumpulan'] = $candidates[0];
                } elseif ($isMitra) {
                    $addErr($i, 'id_perkumpulan', count($candidates) === 0
                        ? 'Member bukan anggota perkumpulan yang diizinkan event ini, pilih perkumpulan'
                        : 'Member anggota lebih dari satu perkumpulan, pilih perkumpulan');
                }
            }

            // action vs existing registration
            if (!$ex) {
                $rows[$i]['aksi'] = 'baru';
            } else {
                $rows[$i]['status_lama'] = (int) $ex->status;
                $rows[$i]['aksi'] = (int) $ex->status === 1 ? 'update' : 'approve';
                // Keep stored roster values the file leaves empty.
                if ($rows[$i]['no_punggung'] === null && $items[$i]['no_punggung'] === '') $rows[$i]['no_punggung'] = $ex->no_punggung;
                if ($rows[$i]['posisi'] === null && $items[$i]['posisi'] === '') $rows[$i]['posisi'] = $ex->posisi_event;
                if ($rows[$i]['no_hp'] === null && $items[$i]['no_hp'] === '') $rows[$i]['no_hp'] = $ex->no_hp;
            }
        }

        // ---- pass 2b: pengajuan keanggotaan, one per posisi typed in the file
        $this->planPengajuan($rows, $addErr);
        foreach ($rows as $i => $r) {
            if (!$r['_not_member']) continue;
            $joins = array_filter($r['_pengajuan'], function ($p) { return $p['aksi'] !== 'sudah'; });
            if (empty($joins)) $addWarn($i, 'id_perkumpulan', 'Member belum tercatat sebagai anggota perkumpulan ini');
        }

        // ---- pass 3: nomor punggung clashes, per (perkumpulan, nomor) within the event
        $inFile = [];
        foreach ($rows as $r) if ($r['_user']) $inFile[(int) $r['_user']->id] = true;

        $taken = []; // key => ['nama' =>, 'status' =>] for registrations NOT touched by this batch
        $others = array_filter($existingRows, function ($ex) use ($inFile) {
            return !isset($inFile[(int) $ex->id_user]) && $ex->no_punggung !== null && $ex->no_punggung !== '';
        });
        if (!empty($others)) {
            $names = DB::table('m_user')->whereIn('id', array_map(function ($ex) { return $ex->id_user; }, $others))->pluck('nama', 'id')->all();
            foreach ($others as $ex) {
                $key = ((int) $ex->id_perkumpulan).'|'.$ex->no_punggung;
                if (!isset($taken[$key]) || (int) $ex->status === 1) {
                    $taken[$key] = ['nama' => $names[$ex->id_user] ?? '-', 'status' => (int) $ex->status];
                }
            }
        }

        $fileKeys = [];
        foreach ($rows as $i => $r) {
            if (!$r['_user'] || $r['no_punggung'] === null || $r['no_punggung'] === '') continue;
            $fileKeys[((int) $r['id_perkumpulan']).'|'.$r['no_punggung']][] = $i;
        }
        foreach ($fileKeys as $key => $idxs) {
            if (count($idxs) > 1) {
                foreach ($idxs as $i) {
                    $addErr($i, 'no_punggung', 'Nomor punggung '.$rows[$i]['no_punggung'].' dipakai lebih dari satu peserta di perkumpulan yang sama');
                }
            }
            if (isset($taken[$key])) {
                foreach ($idxs as $i) {
                    $t = $taken[$key];
                    $msg = 'Nomor punggung '.$rows[$i]['no_punggung'].' sudah dipakai '.$t['nama'];
                    if ($t['status'] === 1) $addErr($i, 'no_punggung', $msg);
                    else $addWarn($i, 'no_punggung', $msg.' (pendaftaran belum diterima)');
                }
            }
        }

        // ---- client-facing perkumpulan
        $perkNames = $this->perkumpulanNames(array_column($rows, 'id_perkumpulan'));
        foreach ($rows as $i => $r) {
            $pid = $r['id_perkumpulan'];
            $rows[$i]['perkumpulan'] = $pid ? ['id' => md5($pid . 'P3RKUMP'), 'nama' => $perkNames[$pid] ?? '-'] : null;
        }

        return [$rows, $errors];
    }

    /** Profile-sourced, non-upload persyaratan values of $userId, for the required keys of $event. */
    private function snapshotProfilPersyaratan($event, $userId){
        $required = json_decode($event->required_fields, true) ?? [];
        if (empty($required)) return [];
        $keyed = $this->masterPersyaratan->getActiveKeyed();
        $user = DB::table('m_user')->where('id', $userId)->first();
        if (!$user) return [];
        $out = [];
        foreach ($required as $key) {
            $def = $keyed[$key] ?? null;
            if (!$def || $def->sumber !== 'profil' || $def->tipe_input === 'upload' || empty($def->profil_field_map)) continue;
            $col = $def->profil_field_map;
            if (isset($user->$col) && $user->$col !== '') $out[$key] = $user->$col;
        }
        return $out;
    }

    /**
     * Admin adds participants to ONE event (Excel import or manual add, the
     * latter being a single row). Every stored row ends up diterima (1).
     * dry_run=true: validate/resolve only, status 00 with per-row
     * errors/warnings for the preview. dry_run=false: all-or-nothing; any
     * row error -> status 96 + data.errors [{index, field, message}].
     */
    public function bulkAddPeserta(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $peserta = $request->peserta;
        if (empty($request->id_event) || !is_array($peserta) || count($peserta) === 0) {
            return ResponseController::sendError(98);
        }
        if (count($peserta) > self::BULK_PESERTA_MAX_ROWS) {
            return ResponseController::sendErrorData(98, ['field' => 'Maksimal '.self::BULK_PESERTA_MAX_ROWS.' peserta per sekali import']);
        }
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        $notify = filter_var($request->input('notify', true), FILTER_VALIDATE_BOOLEAN);

        try {
            $event = $this->event->getEventByEncId($request->id_event); // raw ids
            if (!$event || $event->status === 'nonaktif') return ResponseController::sendError(80);

            $summarize = function (array $rows) {
                $sum = ['baru' => 0, 'approve' => 0, 'update' => 0, 'error' => 0, 'akun_baru' => 0];
                foreach ($rows as $r) {
                    if ($r['akun_baru'] && empty($r['errors'])) $sum['akun_baru']++;
                    if (!empty($r['errors'])) $sum['error']++;
                    elseif ($r['aksi']) $sum[$r['aksi']]++;
                }
                return $sum;
            };
            $publicRows = function (array $rows) {
                return array_map(function ($r) {
                    unset($r['_user'], $r['_existing'], $r['id_perkumpulan'], $r['_posisi'], $r['_pengajuan'], $r['_not_member'], $r['_fix_email']);
                    return $r;
                }, array_values($rows));
            };

            if ($dryRun) {
                // Accounts for unregistered emails are really created so the
                // preview resolves them like the save will, then rolled back.
                DB::beginTransaction();
                $created = $this->createImportAccounts($peserta);
                $existing = DB::table('m_event_pendaftaran_peserta')->where('id_event_pendaftaran', $event->id)->get()->keyBy('id_user')->all();
                [$rows] = $this->resolvePesertaRows($event, $peserta, $existing, $created);
                DB::rollBack();
                foreach ($rows as $i => $r) {
                    if ($r['akun_baru'] && $r['user']) $rows[$i]['user']['no_anggota'] = null; // assigned on save
                }
                return ResponseController::sendSuccess([
                    'message' => 'Pratinjau peserta',
                    'data' => ['rows' => $publicRows($rows), 'summary' => $summarize($rows)],
                ]);
            }

            $id_admin = $this->adminIdFromJwt($currentUser);
            $now = date('Y-m-d H:i:s');
            $toNotify = [];

            DB::beginTransaction();
            $created = $this->createImportAccounts($peserta);
            // Locking the event's registrations (and, via the index, the gap for new
            // ones) keeps a concurrent self-registration from creating a 2nd row.
            $existing = DB::table('m_event_pendaftaran_peserta')
                ->where('id_event_pendaftaran', $event->id)
                ->lockForUpdate()->get()->keyBy('id_user')->all();
            [$rows, $errors] = $this->resolvePesertaRows($event, $peserta, $existing, $created);
            if (!empty($errors)) {
                DB::rollBack();
                return ResponseController::sendErrorData(96, ['errors' => $errors]);
            }

            foreach ($rows as $r) {
                $user = $r['_user'];
                $ex = $r['_existing'];
                $roster = [
                    'id_perkumpulan' => $r['id_perkumpulan'],
                    'no_punggung' => $r['no_punggung'] !== '' ? $r['no_punggung'] : null,
                    'posisi_event' => $r['posisi'] !== '' ? $r['posisi'] : null,
                    'no_hp' => $r['no_hp'] !== '' ? $r['no_hp'] : null,
                ];

                if ($r['aksi'] === 'baru') {
                    DB::table('m_event_pendaftaran_peserta')->insert(array_merge($roster, [
                        'id_event_pendaftaran' => $event->id,
                        'id_user' => $user->id,
                        'data_persyaratan' => json_encode($this->snapshotProfilPersyaratan($event, $user->id)),
                        'status' => 1,
                        'sumber' => !empty($request->manual) ? 'admin' : 'import',
                        'jumlah_pengajuan' => 1,
                        'id_admin_approval' => $id_admin,
                        'id_admin_input' => $id_admin,
                        'tgl_daftar' => $now,
                        'tgl_approval' => $now,
                    ]));
                    $toNotify[] = $user;
                } elseif ($r['aksi'] === 'approve') {
                    $update = array_merge($roster, [
                        'status' => 1,
                        'catatan' => null,
                        'id_admin_approval' => $id_admin,
                        'id_admin_input' => $id_admin,
                        'tgl_approval' => $now,
                        'updated_at' => $now,
                    ]);
                    if ((int) $ex->status === -1) {
                        // Same history shape as daftarPeserta's resubmission path.
                        $riwayat = json_decode($ex->riwayat_penolakan, true) ?? [];
                        $riwayat[] = [
                            'catatan' => $ex->catatan,
                            'id_admin_approval' => $ex->id_admin_approval ?? null,
                            'tgl_approval' => $ex->tgl_approval,
                        ];
                        $update['riwayat_penolakan'] = json_encode($riwayat);
                    }
                    DB::table('m_event_pendaftaran_peserta')->where('id', $ex->id)->update($update);
                    $toNotify[] = $user;
                } else { // update: already diterima, roster fields only
                    DB::table('m_event_pendaftaran_peserta')->where('id', $ex->id)->update(array_merge($roster, [
                        'id_admin_input' => $id_admin,
                        'updated_at' => $now,
                    ]));
                }
                if ($r['_fix_email'] !== null) {
                    DB::table('m_user')->where('id', $user->id)->update(['email' => $r['_fix_email']]);
                }
                $this->writePengajuan($r, $id_admin, $now);
            }
            DB::commit();

            if ($notify) {
                $tokens = [];
                foreach ($toNotify as $u) {
                    $tokens = array_merge($tokens, User::notifTokens($u));
                }
                $tokens = array_values(array_unique($tokens));
                foreach (array_chunk($tokens, 500) as $chunk) {
                    try {
                        GlobalHelperController::sendNotif($chunk, 'Terdaftar di Event',
                            'Anda telah didaftarkan dan diterima sebagai peserta event "'.$event->nama_event.'".');
                    } catch (\Exception $e) {
                        // Notification failure must not undo a committed import.
                    }
                }
            }

            $summary = $summarize($rows);
            return ResponseController::sendSuccess([
                'message' => ($summary['baru'] + $summary['approve'] + $summary['update']).' peserta berhasil disimpan'
                    .($summary['akun_baru'] > 0 ? ' ('.$summary['akun_baru'].' akun baru, password 123456)' : ''),
                'data' => ['summary' => $summary],
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Member search for the manual "Tambah Peserta" form: active users by
     * nama/email/no_anggota/no_hp, each with the perkumpulan they may register
     * under for this event, their current registration (if any) and a
     * posisi/no_punggung suggestion from their latest approved pengajuan pemain.
     */
    public function searchMemberForEvent(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = trim((string) $request->keyword);
        if (empty($request->id_event)) return ResponseController::sendError(98);
        if (mb_strlen($keyword) < 3) {
            return ResponseController::sendSuccess(['message' => 'Keyword minimal 3 karakter', 'data' => []]);
        }

        try {
            $event = $this->event->getEventByEncId($request->id_event);
            if (!$event) return ResponseController::sendError(80);

            $like = '%'.$keyword.'%';
            $users = DB::select(
                'SELECT id, nama, email, no_anggota, no_hp
                FROM m_user
                WHERE status = 1 AND tipe_akun <> \'ortu\' AND (nama LIKE ? OR email LIKE ? OR no_anggota LIKE ? OR no_hp LIKE ?)
                ORDER BY nama ASC
                LIMIT 20', [$like, $like, $like, $like]
            );
            if (empty($users)) {
                return ResponseController::sendSuccess(['message' => 'Tidak ada member', 'data' => []]);
            }

            $ids = array_map(function ($u) { return (int) $u->id; }, $users);
            $memberships = $this->approvedMemberships($ids);
            $allowed = $event->skema_pendaftaran === 'mitra_dan_anggota' ? $this->event->getAllowedPerkumpulanIds($event->id) : null;
            $perkNames = $this->perkumpulanNames(array_merge([], ...array_values($memberships)));

            $existing = DB::table('m_event_pendaftaran_peserta')
                ->where('id_event_pendaftaran', $event->id)->whereIn('id_user', $ids)
                ->get()->keyBy('id_user')->all();

            $saran = [];
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pp = DB::select(
                'SELECT id_user, posisi, no_punggung FROM m_pengajuan_pemain
                WHERE status = 2 AND id_user IN ('.$in.')
                ORDER BY tahun DESC, id DESC', $ids
            );
            foreach ($pp as $r) {
                if (!isset($saran[(int) $r->id_user])) $saran[(int) $r->id_user] = $r;
            }

            $data = array_map(function ($u) use ($memberships, $allowed, $perkNames, $existing, $saran) {
                $uid = (int) $u->id;
                $perk = $memberships[$uid] ?? [];
                if ($allowed !== null) $perk = array_values(array_intersect($perk, $allowed));
                $ex = $existing[$uid] ?? null;
                $s = $saran[$uid] ?? null;
                $status = $ex ? (int) $ex->status : null;
                return [
                    'id' => md5($u->id . $this->user->imbuhan),
                    'nama' => $u->nama,
                    'email' => $u->email,
                    'no_anggota' => $u->no_anggota,
                    'no_hp' => $u->no_hp,
                    'perkumpulan' => array_map(function ($pid) use ($perkNames) {
                        return ['id' => md5($pid . 'P3RKUMP'), 'nama' => $perkNames[$pid] ?? '-'];
                    }, array_values(array_unique($perk))),
                    'pendaftaran' => $ex ? [
                        'status' => $status,
                        'status_text' => $status === 1 ? 'Diterima' : ($status === -1 ? 'Ditolak' : 'Menunggu'),
                        'id_perkumpulan' => !empty($ex->id_perkumpulan) ? md5($ex->id_perkumpulan . 'P3RKUMP') : null,
                        'no_punggung' => $ex->no_punggung,
                        'posisi' => $ex->posisi_event,
                        'no_hp' => $ex->no_hp,
                    ] : null,
                    'saran_posisi' => $s ? $s->posisi : null,
                    'saran_no_punggung' => $s ? $s->no_punggung : null,
                ];
            }, $users);

            return ResponseController::sendSuccess(['message' => 'Data member berhasil diambil', 'data' => $data]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
