<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\Pertandingan;
use App\PertandinganCheckin;
use App\EventPendaftaran;
use App\EventPendaftaranPeserta;
use App\News;
use App\Admin;
use Illuminate\Support\Facades\DB;

class APIPertandinganController extends Controller
{
    protected $pertandingan;
    protected $checkin;
    protected $event;
    protected $peserta;
    protected $news;
    protected $admin;

    public function __construct()
    {
        $this->pertandingan = new Pertandingan();
        $this->checkin = new PertandinganCheckin();
        $this->event = new EventPendaftaran();
        $this->peserta = new EventPendaftaranPeserta();
        $this->news = new News();
        $this->admin = new Admin();
    }

    /**
     * Resolves the event a request targets. `id_event_pendaftaran` is the real
     * key (one news can carry many events). Legacy clients only know
     * `id_news`: still accepted, but only while that news has exactly one
     * active event — otherwise it is ambiguous and the client must upgrade.
     * Returns [rawEventRow|null, errorJson|null].
     */
    private function resolveEvent(Request $request, $allowLegacyNews = false){
        if (!empty($request->id_event_pendaftaran)) {
            $ev = $this->event->getEventByEncId($request->id_event_pendaftaran);
            return $ev ? [$ev, null] : [null, ResponseController::sendError(80)];
        }
        if ($allowLegacyNews && !empty($request->id_news)) {
            $newsRow = $this->news->getNewsByEncId($request->id_news);
            if (!$newsRow) return [null, ResponseController::sendError(48)];
            $ids = $this->event->getActiveEventIdsByNewsId($newsRow->id);
            if (count($ids) === 0) return [null, ResponseController::sendError(91)];
            if (count($ids) > 1) {
                return [null, ResponseController::sendErrorData(98, ['field' => 'Banner/berita ini memuat lebih dari satu jadwal, kirim id_event_pendaftaran'])];
            }
            $ev = $this->event->getEventById($ids[0]);
            return $ev ? [$ev, null] : [null, ResponseController::sendError(80)];
        }
        return [null, ResponseController::sendError(98)];
    }

    // ==================== ADMIN: SESSION CONFIG ====================

    /**
     * A check-in session belongs to exactly one Event Pendaftaran (unique per
     * event) and is not restricted to any particular perkumpulan — it can be
     * a match, a seminar, or any other activity. [judul] is a free-text title
     * used for the admin's own lists (defaults to the event name). The news
     * (banner/berita) it shows up on is simply the event's own `id_news`, so
     * one news can carry many events each with its own session. The
     * session's date always mirrors the event's own tanggal_mulai, so only
     * [jam_mulai]/[jam_checkin] (time-of-day, e.g. "14:00") come from the
     * client; jam_mulai is also written back to the event.
     */
    public function savePertandingan(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $jam_mulai_time = $this->pertandingan->normalizeTime($request->jam_mulai);
        $jam_checkin_time = $this->pertandingan->normalizeTime($request->jam_checkin);
        $checkin_enabled = filter_var($request->checkin_enabled, FILTER_VALIDATE_BOOLEAN);
        $id_admin_enc = $request->id_admin; // encrypted USER id of the admin

        if (empty($request->id_event_pendaftaran) || empty($jam_mulai_time) || empty($jam_checkin_time)) {
            return ResponseController::sendError(98);
        }

        try {
            [$eventRow, $err] = $this->resolveEvent($request);
            if ($err) return $err;

            $judul = trim((string) $request->judul);
            if ($judul === '') $judul = $eventRow->nama_event;

            $adminRow = !empty($id_admin_enc) ? $this->admin->getAdminByUserEncId($id_admin_enc) : null;

            $code = $this->pertandingan->saveForEvent(
                $eventRow, $judul, $jam_mulai_time, $jam_checkin_time, $checkin_enabled,
                $adminRow ? $adminRow->id : null
            );
            if ($code !== null) return ResponseController::sendError($code);

            DB::table('m_event_pendaftaran')->where('id', $eventRow->id)->update(['jam_mulai' => $jam_mulai_time]);

            $saved = $this->pertandingan->getByEventId($eventRow->id);
            return ResponseController::sendSuccess([
                'message' => 'Sesi check-in berhasil disimpan',
                'data' => [
                    'id' => md5($saved->id . $this->pertandingan->imbuhan),
                    'id_event_pendaftaran' => md5($eventRow->id . $this->event->imbuhan),
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /** Admin prefill: the session of one event (`id_event_pendaftaran`), or null data. */
    public function getDetailPertandingan(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            [$eventRow, $err] = $this->resolveEvent($request);
            if ($err) return $err;

            $row = $this->pertandingan->getByEventId($eventRow->id); // raw ids, or null
            $this->pertandingan->encodeForClient($row);

            return ResponseController::sendSuccess([
                'message' => 'Detail sesi check-in berhasil diambil',
                'data' => $row
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function getListPertandingan(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id_event = null;
        if (!empty($request->id_event_pendaftaran)) {
            $eventRow = $this->event->getEventByEncId($request->id_event_pendaftaran);
            if (!$eventRow) return ResponseController::sendError(80);
            $id_event = $eventRow->id;
        }

        try {
            $rows = $this->pertandingan->getListByEvent($id_event, $request->keyword, $request->status);
            $paged = GlobalHelperController::paginate($rows, $request);

            return ResponseController::sendSuccess([
                'message' => 'List sesi check-in berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    // ==================== MEMBER: CHECK-IN ====================

    /**
     * Single combined endpoint for both the member app and compro: session
     * info + eligibility + role/prefill + my existing checkin, in one
     * round-trip, so the client can render all 4 CTA states without extra
     * requests. Eligibility is event-wide: any approved (status=1) peserta
     * of the session's governing event — no perkumpulan restriction, since
     * that's already enforced at registration time.
     */
    public function detailForMember(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        try {
            [$eventRow, $err] = $this->resolveEvent($request, true);
            if ($err) return $err;

            $row = $this->pertandingan->getByEventId($eventRow->id); // raw ids, or null
            if (!$row) {
                return ResponseController::sendSuccess([
                    'message' => 'Post ini belum punya konfigurasi check-in',
                    'data' => [
                        'pertandingan' => null,
                        'is_eligible' => false,
                        'role' => null,
                        'prefill' => null,
                        'my_checkin' => null,
                    ]
                ]);
            }

            $isEligible = false;
            $role = null;
            $prefill = null;
            $myCheckin = null;

            if ((bool) $row->checkin_enabled) {
                $peserta = $this->peserta->getByUserAndEvent($currentUser['user_id'], $row->id_event_pendaftaran);
                if ($peserta && (int) $peserta->status === 1) {
                    $isEligible = true;
                    $resolved = $this->checkin->resolveRoleAndPrefill($currentUser['user_id'], $peserta->id_perkumpulan);
                    $role = $resolved->role;
                    $prefill = ['posisi' => $resolved->posisi, 'no_punggung' => $resolved->no_punggung];
                }

                $myCheckin = $this->checkin->getMyCheckin($row->id, $currentUser['user_id']);
            }

            $rawId = $row->id;
            $this->pertandingan->encodeForClient($row);

            return ResponseController::sendSuccess([
                'message' => 'Detail sesi check-in berhasil diambil',
                'data' => [
                    'pertandingan' => $row,
                    'is_eligible' => $isEligible,
                    'role' => $role,
                    'prefill' => $prefill,
                    'my_checkin' => $myCheckin,
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function submitCheckin(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $currentUser = $validation['user'];

        $status_kehadiran = $request->status_kehadiran;
        if ($status_kehadiran === null || $status_kehadiran === '') {
            return ResponseController::sendError(98);
        }
        $status_kehadiran = (int) filter_var($status_kehadiran, FILTER_VALIDATE_BOOLEAN);

        try {
            [$eventRow, $err] = $this->resolveEvent($request, true);
            if ($err) return $err;

            $pertandingan = $this->pertandingan->getByEventId($eventRow->id);
            if (!$pertandingan) return ResponseController::sendError(91);
            if (!(bool) $pertandingan->checkin_enabled) return ResponseController::sendError(95);

            // Re-validate eligibility server-side — never trust the client.
            // Event-wide: any approved peserta of the governing event.
            $peserta = $this->peserta->getByUserAndEvent($currentUser['user_id'], $pertandingan->id_event_pendaftaran);
            if (!$peserta || (int) $peserta->status !== 1) {
                return ResponseController::sendError(94);
            }

            $resolved = $this->checkin->resolveRoleAndPrefill($currentUser['user_id'], $peserta->id_perkumpulan);
            $role = $resolved->role;

            $posisi = null;
            $no_punggung = null;
            if ($role === 'pemain') {
                $posisi = $request->posisi;
                $no_punggung = $request->no_punggung;
                if (empty($posisi) || empty($no_punggung)) {
                    return ResponseController::sendErrorData(98, ['field' => 'posisi dan no_punggung wajib diisi untuk pemain']);
                }
            }

            $row = $this->checkin->submitCheckin([
                'id_pertandingan' => $pertandingan->id,
                'id_event_pendaftaran_peserta' => $peserta->id,
                'id_user' => $currentUser['user_id'],
                'id_perkumpulan' => $peserta->id_perkumpulan,
                'role' => $role,
                'posisi' => $posisi,
                'no_punggung' => $no_punggung,
                'status_kehadiran' => $status_kehadiran,
            ]);

            return ResponseController::sendSuccess([
                'message' => 'Check-in berhasil dikirim',
                'data' => $row
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Roster of every approved peserta of the session's governing event,
     * dynamically grouped by whichever perkumpulan they actually belong to
     * (not a fixed two-side split — a session can involve any number of
     * schools, e.g. a seminar), each further split into pemain/sdm/lainnya
     * with a Hadir/Tidak Hadir/Belum status. Used by the member app (after
     * checkin), compro, and the admin monitoring screen.
     */
    public function getAttendanceList(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        try {
            [$eventRow, $err] = $this->resolveEvent($request, true);
            if ($err) return $err;

            $pertandingan = $this->pertandingan->getByEventId($eventRow->id);
            if (!$pertandingan) return ResponseController::sendError(91);

            $rows = $this->checkin->getAttendanceList($pertandingan->id, $pertandingan->id_event_pendaftaran);

            $groups = [];
            $overall = ['hadir' => 0, 'tidak_hadir' => 0, 'belum' => 0, 'total' => 0];

            foreach ($rows as $row) {
                $groupKey = $row->id_perkumpulan !== null ? (string) $row->id_perkumpulan : 'lainnya';
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'nama_perkumpulan' => $row->nama_perkumpulan ?: 'Tanpa Perkumpulan',
                        'pemain' => [], 'sdm' => [], 'lainnya' => [],
                        'summary' => ['hadir' => 0, 'tidak_hadir' => 0, 'belum' => 0, 'total' => 0],
                    ];
                }

                $statusLabel = $row->status_kehadiran === null
                    ? 'Belum Check In'
                    : ((int) $row->status_kehadiran === 1 ? 'Hadir' : 'Tidak Hadir');

                $bucket = in_array($row->role, ['pemain', 'sdm'], true) ? $row->role : 'lainnya';
                $groups[$groupKey][$bucket][] = [
                    'id_user' => $row->id_user,
                    'nama' => $row->nama,
                    'posisi' => $row->posisi,
                    'no_punggung' => $row->no_punggung,
                    'status_kehadiran' => $row->status_kehadiran,
                    'status_label' => $statusLabel,
                    'checkin_at' => $row->checkin_at,
                ];

                $groups[$groupKey]['summary']['total']++;
                $overall['total']++;
                if ($row->status_kehadiran === null) {
                    $groups[$groupKey]['summary']['belum']++;
                    $overall['belum']++;
                } elseif ((int) $row->status_kehadiran === 1) {
                    $groups[$groupKey]['summary']['hadir']++;
                    $overall['hadir']++;
                } else {
                    $groups[$groupKey]['summary']['tidak_hadir']++;
                    $overall['tidak_hadir']++;
                }
            }

            return ResponseController::sendSuccess([
                'message' => 'Daftar hadir berhasil diambil',
                'data' => [
                    'groups' => array_values($groups),
                    'summary' => $overall,
                ]
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
