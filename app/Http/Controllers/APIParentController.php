<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Kota;
use App\User;

/**
 * Parents mode: a parent account (m_user.tipe_akun = 'ortu') registers and lists
 * its children (m_user rows with id_parents = the parent's id).
 *
 * These endpoints use the REAL JWT user (never X-Acting-As), because they are
 * called from the parent dashboard before any child is selected.
 */
class APIParentController extends Controller
{
    protected $user;
    protected $kota;

    public function __construct()
    {
        $this->user = new User();
        $this->kota = new Kota();
    }

    /** Returns the parent row, or null if the JWT user is not an active parent account. */
    private function currentParent($validation)
    {
        $userId = $validation['user']['real_user_id'] ?? $validation['user']['user_id'];
        $parent = User::find($userId);
        if (!$parent || $parent->status != 1 || $parent->tipe_akun !== 'ortu') {
            return null;
        }
        return $parent;
    }

    public function getChildren(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $parent = $this->currentParent($validation);
        if (!$parent) {
            return ResponseController::sendError(103);
        }

        try {
            $children = DB::select(
                'SELECT
                    md5(CONCAT(u.id, "'.$this->user->imbuhan.'")) AS id,
                    u.nama,
                    u.no_anggota,
                    u.filename_foto,
                    u.email,
                    (u.email IS NULL AND u.password IS NULL) AS dikelola_ortu,
                    (SELECT COUNT(*) FROM m_pengajuan_pemain pp WHERE pp.id_user = u.id AND pp.status = 2) AS pemain_aktif,
                    (SELECT COUNT(*) FROM m_pengajuan_nonaggota pn WHERE pn.id_user = u.id AND pn.status = 2) AS sdm_aktif,
                    (SELECT COUNT(*) FROM m_pengajuan_pemain pp WHERE pp.id_user = u.id AND pp.status IN (0, 1)) +
                    (SELECT COUNT(*) FROM m_pengajuan_nonaggota pn WHERE pn.id_user = u.id AND pn.status IN (0, 1)) AS pengajuan_proses
                FROM m_user u
                WHERE u.id_parents = ? AND u.status = 1
                ORDER BY u.nama ASC',
                [$parent->id]
            );

            return ResponseController::sendSuccess([
                'message' => 'Data anak berhasil diambil',
                'data' => $children
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    public function addChild(Request $request)
    {
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }
        $parent = $this->currentParent($validation);
        if (!$parent) {
            return ResponseController::sendError(103);
        }

        $nama     = trim((string) $request->nama);
        $email    = trim((string) $request->email);
        $password = (string) $request->password;
        $email    = $email === '' ? null : $email;
        $password = $password === '' ? null : $password;

        if ($nama === '') {
            return ResponseController::sendError(98);
        }

        $resKota = $this->kota->getKotaByEncId($request->id_kota);
        if (!$resKota) {
            return ResponseController::sendError(36);
        }

        // Email dan password: diisi bersamaan atau dikosongkan bersamaan
        if (($email === null) !== ($password === null)) {
            return ResponseController::sendError(102);
        }
        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ResponseController::sendError(63);
            }
            if (User::where('email', $email)->first()) {
                return ResponseController::sendError(3);
            }
            if (strlen($password) < 6) {
                return ResponseController::sendError(66);
            }
        }

        try {
            $no_anggota = $this->user->generateNoAnggota($resKota->id);

            $child = new User([
                'nama' => $nama,
                'email' => $email,
                'password' => $password === null ? null : Hash::make($password),
                'no_anggota' => $no_anggota,
                'id_parents' => $parent->id,
                'tipe_akun' => 'member',
                'status' => 1,
            ]);
            $child->save();

            return ResponseController::sendSuccess([
                'message' => 'Data anak berhasil ditambahkan',
                'no_anggota' => $no_anggota,
                'id' => md5($child->id . $this->user->imbuhan),
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
