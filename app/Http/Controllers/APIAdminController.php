<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\GlobalHelperController;
use App\User;
use App\Admin;
use App\Perkumpulan;

class APIAdminController extends Controller
{
    protected $user;
    protected $admin;
    protected $perkumpulan;

    public function __construct()
    {
        $this->user = new User();
        $this->admin = new Admin();
        $this->perkumpulan = new Perkumpulan();
    }

    public function searchUserByEmail(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $email = $request->email;

        if (empty($email)) {
            return ResponseController::sendError(98);
        }

        try {
            return ResponseController::sendSuccess([
                'message' => 'Data user berhasil diambil',
                'data' => $this->user->getUserWithAdminInfo($email)
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }


    public function registerUserAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['id_member', 'role'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id_memberenc = $request->id_member;
        $role = $request->role; // 1 = master admin, 2 = admin perkumpulan
        $id_perkumpulanenc = $request->id_perkumpulan;

        // Validasi role
        if (!in_array($role, [1, 2])) {
            return ResponseController::sendError(34); // Invalid role
        }

        // Jika role = 2 (admin perkumpulan), wajib ada id_perkumpulan
        if ($role == 2 && empty($id_perkumpulanenc)) {
            return ResponseController::sendErrorData(98, ['field' => 'perkumpulan required for Admin Perkumpulan']);
        }

        try {
            DB::beginTransaction();

            // Get user/member by encrypted id
            $user = $this->user->getUserByEncId($id_memberenc);
            if (!$user) {
                DB::rollBack();
                return ResponseController::sendError(code: 97); // User not found
            }
            $iduser = $user->id;
            $username = $user->email; // Use email as username

            // Check if username already exists in admin table
            // $existingAdmin = $this->admin->getAdminByUserEncId($id_memberenc);
            // if ($existingAdmin) {
            //     DB::rollBack();
            //     return ResponseController::sendError(68); // Username already exists
            // }

            // Check if id_perkumpulan is valid
            $id_perkumpulan = null;
            if ($role == 2) {
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulanenc);
                if (!$perkumpulan) {
                    DB::rollBack();
                    return ResponseController::sendError(4);
                }
                $id_perkumpulan = $perkumpulan->id;

                // // Check if user already exists as admin for this perkumpulan
                // $existingAdminPerkumpulan = Admin::where('id_perkumpulan', $id_perkumpulan)
                //     ->where('status', '1')
                //     ->first();
                // if ($existingAdminPerkumpulan) {
                //     DB::rollBack();
                //     return ResponseController::sendError(67);
                // }
            }

            // Create admin in m_admin
            $admin = new Admin([
                'id_user' => $iduser,
                'username' => $username,
                'id_role' => $role,
                'id_perkumpulan' => $role == 2 ? $id_perkumpulan : null,
                'status' => 1,
            ]);
            $admin->save();

            DB::commit();

            return ResponseController::sendSuccess([
                'message' => 'User admin berhasil didaftarkan',
                'data' => [
                    'username' => $username,
                    'role' => $role
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 3. Get List Admin
     * Menampilkan list nama, email, status, no_perkumpulan, nama_perkumpulan, id_role, nama_role
     */
    public function getListAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $keyword = $request->keyword;
        $id_role = $request->id_role;
        $status = $request->status ?? 1;

        try {
            $admins = $this->admin->getListAdmin($keyword, $id_role, $status);

            $paged = GlobalHelperController::paginate($admins, $request);

            return ResponseController::sendSuccess([
                'message' => 'Data admin berhasil diambil',
                'data' => $paged['data'],
                'pagination' => $paged['pagination']
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 4. Get Detail Admin
     * Menampilkan semua informasi admin
     */
    public function getDetailAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $admin = $this->admin->getAdminDetails($id);

            if(!$admin){
                return ResponseController::sendError(97);
            }

            return ResponseController::sendSuccess([
                'message' => 'Detail admin berhasil diambil',
                'data' => $admin
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 5. Update Admin
     * Update data admin (role, perkumpulan)
     */
    public function updateAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        // Validate required fields
        $required = ['id', 'role'];
        foreach ($required as $field) {
            if (empty($request->$field)) {
                return ResponseController::sendErrorData(98, ['field' => $field . ' is required']);
            }
        }

        $id = $request->id;
        $role = $request->role; // 1 = master admin, 2 = admin perkumpulan
        $id_perkumpulanenc = $request->id_perkumpulan;

        // Validasi role
        if (!in_array($role, [1, 2])) {
            return ResponseController::sendError(34); // Invalid role
        }

        // Jika role = 2 (admin perkumpulan), wajib ada id_perkumpulan
        if ($role == 2 && empty($id_perkumpulanenc)) {
            return ResponseController::sendErrorData(98, ['field' => 'perkumpulan required for Admin Perkumpulan']);
        }

        try {
            DB::beginTransaction();

            // Get existing admin
            $admin = $this->admin->getAdminByEncId($id);
            if (!$admin) {
                DB::rollBack();
                return ResponseController::sendError(97);
            }

            // Check if id_perkumpulan is valid
            $id_perkumpulan = null;
            if ($role == 2) {
                $perkumpulan = $this->perkumpulan->getPerkumpulanByEncId($id_perkumpulanenc);
                if (!$perkumpulan) {
                    DB::rollBack();
                    return ResponseController::sendError(4);
                }
                $id_perkumpulan = $perkumpulan->id;

                // Check if user already exists as admin for this perkumpulan (exclude current admin)
                $existingAdminPerkumpulan = Admin::where('id_perkumpulan', $id_perkumpulan)
                    ->where('id_user', $admin->id_user)
                    ->where('id', '!=', $admin->id)
                    ->where('status', "1")
                    ->first();
                if ($existingAdminPerkumpulan) {
                    DB::rollBack();
                    return ResponseController::sendError(67);
                }
            }

            // Update admin in m_admin
            $updateAdmin = Admin::find($admin->id);
            $updateAdmin->id_role = $role;
            $updateAdmin->id_perkumpulan = $role == 2 ? $id_perkumpulan : null;
            $updateAdmin->save();

            DB::commit();

            return ResponseController::sendSuccess([
                'message' => 'Data admin berhasil diupdate',
                'data' => [
                    'username' => $admin->username,
                    'role' => $role
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 6. Update Admin Status
     * Activate/Deactivate admin
     */
    public function updateAdminStatus(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;
        $status = $request->status;

        if (empty($id) || !isset($status)) {
            return ResponseController::sendError(98);
        }

        try {
            $admin = $this->admin->getAdminByEncId($id);
            if (!$admin) {
                return ResponseController::sendError(97);
            }

            $updateAdmin = Admin::find($admin->id);
            $updateAdmin->status = $status;
            $updateAdmin->save();

            // Update user status juga
            // $updateUser = User::find($admin->id_user);
            // $updateUser->status = $status;
            // $updateUser->save();

            return ResponseController::sendSuccess([
                'message' => 'Status admin berhasil diubah'
            ]);
        } catch (\Exception $e) {
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }

    /**
     * 7. Delete Admin (Soft Delete)
     */
    public function deleteAdmin(Request $request)
    {
        // Validate JWT Token
        $validation = GlobalHelperController::validateJWT($request);
        if (!$validation['valid']) {
            return GlobalHelperController::sendUnauthorized($validation['message']);
        }

        $id = $request->id;

        if (empty($id)) {
            return ResponseController::sendError(98);
        }

        try {
            $admin = $this->admin->getAdminByEncId($id);
            if (!$admin) {
                return ResponseController::sendError(97);
            }

            $hasNews = DB::select('SELECT COUNT(*) as total FROM m_news WHERE id_admin = ? AND status != -1', [$admin->id]);
            if ($hasNews[0]->total > 0) {
                return ResponseController::sendError(71);
            }

            $hasBannerProgram = DB::select('SELECT COUNT(*) as total FROM m_banner_program WHERE id_admin = ?', [$admin->id]);
            if ($hasBannerProgram[0]->total > 0) {
                return ResponseController::sendError(71);
            }

            DB::beginTransaction();

            // Soft delete admin
            $deleteAdmin = Admin::find($admin->id);
            $deleteAdmin->status = -1;
            $deleteAdmin->save();

            // Soft delete user
            // $deleteUser = User::find($admin->id_user);
            // $deleteUser->status = -1;
            // $deleteUser->save();

            DB::commit();

            return ResponseController::sendSuccess([
                'message' => 'Data admin berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseController::sendErrorData(99, ['error' => $e->getMessage()]);
        }
    }
}
