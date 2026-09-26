<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PertandinganCheckin extends Model
{
    protected $table = 'm_pertandingan_checkin';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 'id_pertandingan', 'id_event_pendaftaran_peserta', 'id_user', 'id_perkumpulan',
        'role', 'posisi', 'no_punggung', 'status_kehadiran', 'checkin_at'
    ];
    public $imbuhan = 'P3RT4ND1NGCHK1N';

    /**
     * Resolves whether $id_user is an approved pemain or sdm of $id_perkumpulan
     * (same approved-membership check as EventPendaftaranPeserta::selectPosisi()),
     * plus a prefill posisi/no_punggung — used to prefill the check-in form and
     * to freeze role/posisi/no_punggung into the checkin row at submit time.
     * Returns null role when the user has neither an approved pemain nor sdm row.
     */
    function resolveRoleAndPrefill($id_user, $id_perkumpulan){
        $data = DB::select(
            'SELECT
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM m_pengajuan_pemain pp
                        WHERE pp.id_user = ? AND pp.id_perkumpulan <=> ? AND pp.status = 2
                    ) THEN "pemain"
                    WHEN EXISTS (
                        SELECT 1 FROM m_pengajuan_nonaggota pn
                        WHERE pn.id_user = ? AND pn.id_perkumpulan <=> ? AND pn.status = 2
                    ) THEN "sdm"
                    ELSE NULL
                END AS role,
                COALESCE(
                    NULLIF(
                        (SELECT GROUP_CONCAT(DISTINCT pp.posisi SEPARATOR ", ")
                            FROM m_pengajuan_pemain pp
                            WHERE pp.id_user = ? AND pp.id_perkumpulan <=> ? AND pp.status = 2 AND pp.posisi <> ""),
                        ""
                    ),
                    (SELECT pos.nama FROM m_pengajuan_nonaggota pn
                        JOIN m_posisi pos ON pn.id_posisi = pos.id
                        WHERE pn.id_user = ? AND pn.id_perkumpulan <=> ? AND pn.status = 2
                        LIMIT 1)
                ) AS posisi,
                (SELECT pp.no_punggung FROM m_pengajuan_pemain pp
                    WHERE pp.id_user = ? AND pp.id_perkumpulan <=> ? AND pp.status = 2
                    LIMIT 1) AS no_punggung
            ', [
                $id_user, $id_perkumpulan,
                $id_user, $id_perkumpulan,
                $id_user, $id_perkumpulan,
                $id_user, $id_perkumpulan,
                $id_user, $id_perkumpulan,
            ]
        );
        return sizeof($data) > 0 ? $data[0] : (object) ['role' => null, 'posisi' => null, 'no_punggung' => null];
    }

    /** RAW row (internal use). Null if this user hasn't checked in to this match. */
    function getMyCheckin($id_pertandingan, $id_user){
        $data = DB::select(
            'SELECT
                md5(concat(id, "'.$this->imbuhan.'")) AS id,
                role, posisi, no_punggung, status_kehadiran, checkin_at
            FROM m_pertandingan_checkin
            WHERE id_pertandingan = ? AND id_user = ?
            LIMIT 1
            ', [$id_pertandingan, $id_user]
        );
        return sizeof($data) > 0 ? $data[0] : null;
    }

    /**
     * Idempotent: if a checkin already exists for (id_pertandingan, id_user),
     * returns that existing row instead of inserting a duplicate (both mobile
     * and compro may retry a submit).
     */
    function submitCheckin(array $data){
        $existing = $this->getMyCheckin($data['id_pertandingan'], $data['id_user']);
        if ($existing) return $existing;

        DB::table('m_pertandingan_checkin')->insert([
            'id_pertandingan' => $data['id_pertandingan'],
            'id_event_pendaftaran_peserta' => $data['id_event_pendaftaran_peserta'],
            'id_user' => $data['id_user'],
            'id_perkumpulan' => $data['id_perkumpulan'],
            'role' => $data['role'],
            'posisi' => $data['posisi'],
            'no_punggung' => $data['no_punggung'],
            'status_kehadiran' => $data['status_kehadiran'],
        ]);

        return $this->getMyCheckin($data['id_pertandingan'], $data['id_user']);
    }

    /**
     * Roster of everyone eligible to check in for $id_pertandingan — every
     * approved peserta of the governing event (no team/perkumpulan
     * restriction: eligibility is event-wide, matching whoever registered
     * and was approved), left-joined to their checkin row for this session
     * if any. Role/posisi fall back to the same live pengajuan lookup as
     * resolveRoleAndPrefill() for anyone who hasn't checked in yet, so the
     * "belum check-in" bucket is still grouped correctly. Rows are ordered
     * by perkumpulan so the controller can group them dynamically — there's
     * no fixed two-side split here.
     */
    function getAttendanceList($id_pertandingan, $id_event_pendaftaran){
        return DB::select(
            'SELECT
                md5(concat(pe.id, "P3S3RTAEV")) AS id_peserta,
                md5(concat(u.id, "U53R")) AS id_user,
                u.nama,
                pe.id_perkumpulan,
                pk.nama AS nama_perkumpulan,
                COALESCE(
                    chk.role,
                    CASE
                        WHEN EXISTS (
                            SELECT 1 FROM m_pengajuan_pemain pp
                            WHERE pp.id_user = pe.id_user AND pp.id_perkumpulan <=> pe.id_perkumpulan AND pp.status = 2
                        ) THEN "pemain"
                        WHEN EXISTS (
                            SELECT 1 FROM m_pengajuan_nonaggota pn
                            WHERE pn.id_user = pe.id_user AND pn.id_perkumpulan <=> pe.id_perkumpulan AND pn.status = 2
                        ) THEN "sdm"
                        ELSE NULL
                    END
                ) AS role,
                COALESCE(
                    chk.posisi,
                    NULLIF(
                        (SELECT GROUP_CONCAT(DISTINCT pp.posisi SEPARATOR ", ")
                            FROM m_pengajuan_pemain pp
                            WHERE pp.id_user = pe.id_user AND pp.id_perkumpulan <=> pe.id_perkumpulan AND pp.status = 2 AND pp.posisi <> ""),
                        ""
                    ),
                    (SELECT pos.nama FROM m_pengajuan_nonaggota pn
                        JOIN m_posisi pos ON pn.id_posisi = pos.id
                        WHERE pn.id_user = pe.id_user AND pn.id_perkumpulan <=> pe.id_perkumpulan AND pn.status = 2
                        LIMIT 1)
                ) AS posisi,
                chk.no_punggung,
                chk.status_kehadiran,
                chk.checkin_at
            FROM m_event_pendaftaran_peserta pe
            JOIN m_user u ON u.id = pe.id_user
            LEFT JOIN m_perkumpulan pk ON pk.id = pe.id_perkumpulan
            LEFT JOIN m_pertandingan_checkin chk ON chk.id_pertandingan = ? AND chk.id_user = pe.id_user
            WHERE pe.id_event_pendaftaran = ?
              AND pe.status = 1
            ORDER BY pk.nama, role, u.nama
            ', [$id_pertandingan, $id_event_pendaftaran]
        );
    }
}
