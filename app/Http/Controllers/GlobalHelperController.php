<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Input;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\File;
use DateTime;
use App\Http\Controllers\ResponseController;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Helpers\JWTHelper;


class GlobalHelperController extends Controller{

    private static $nama;
    protected static $mail;


    public static function sendMailVerification( $email, $nama, $otp_code){
        static::$nama = $nama;
        static::$mail = $email;

        $data = array('name'=>$nama, "otp_code"=> $otp_code);

        Mail::send('mail_otp', $data, function($message) {
         $message->to(static::$mail, static::$nama)->subject
            ('OTP Code');
         $message->from('info@perbasipemalang.org','Perbasi Pemalang');
        });
    }

    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function normalizePhoneNumber($phone) {
        $phone = preg_replace('/^-/', '', $phone);
        // If the phone number starts with +62, replace it with an empty string
        if (strpos($phone, '+62') === 0) {
            $phone = preg_replace('/^\+62/', '', $phone);
        }
        // If the phone number starts with 0, replace it with an empty string
        if (strpos($phone, '0') === 0) {
            $phone = preg_replace('/^0/', '', $phone);
        }
        return $phone;
    }


    public static function sendSingleNotif($token, $title, $message){
        $firebase = app('firebase.messaging');
        $message = CloudMessage::withTarget("token", $token)
            ->withNotification(Notification::create($title, $message))
            ->withData([
                'title' => $title,
                'body' => $message
            ]);

        $firebase->send($message);
    }
     public static function sendNotif($tokens, $title, $message, $idorder = null){
        $firebase = app('firebase.messaging');
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $message ))
            ->withData([
                'title' => $title,
                'body' => $message,
                'data' => json_encode([
                    "id_order" => $idorder
                ])
            ])
            ->withAndroidConfig([
                "notification" => [
                    "sound" => "res_siren.mp3",
                    "default_sound" => false,
                ]
            ])
            ->withApnsConfig([
                "headers" => [
                    "apns-collapse-id"=>"solo_changed_administrator",
                    "content-available"=>"1",
                    "apns-priority"=>"10"
                ],           
                "payload" => [
                    "aps" => [
                        "sound" => "siren.caf",
                    ]
                ]
            ]);

        $sendReport = $firebase->sendMulticast($message, $tokens);
    }

    public static function decodeNotificationData($dataString){
        return json_decode($dataString, true);
    }

    /**
     * Apply pagination on an array result.
     * Reads `page` (default 1) and `per_page` (default 20, max 100) from request.
     * Returns ['data' => sliced, 'pagination' => [...]]
     */
    public static function paginate($items, Request $request)
    {
        $items = is_array($items) ? array_values($items) : [];

        $page = (int) ($request->input('page') ?: 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) ($request->input('per_page') ?: 100);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $total = count($items);
        $totalPage = $total > 0 ? (int) ceil($total / $perPage) : 0;

        $offset = ($page - 1) * $perPage;
        $sliced = array_slice($items, $offset, $perPage);

        return [
            'data' => $sliced,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_page' => $totalPage,
            ],
        ];
    }


    public static function checkLoginSession(){
        if(empty(Session::get('id'))){
            return redirect()->away(url("/"));
        }
        // echo 'logino'.Session::get('id').'asdasd';
        // die();
    }


    public static function getStatusString($status)
    {
        switch ($status) {
            case -5:
                return 'Cancelled';
            case -4:
                return 'Deleted';
            case -3:
                return 'Re-cleaning';
            case -2:
                return 'Receipt Rejected';
            case -1:
                return 'Awaiting Payment';
            case 0:
                return 'Validation';
            case 1:
                return 'Scheduled';
            case 2:
                return 'In Progress';
            case 3:
                return 'Waiting Approval';
            case 4:
                return 'Completed';
            default:
                return 'Unknown';
        }
    }

    // ==================== JWT FUNCTIONS ====================

    /**
     * Validate JWT Token from Request Header
     */
    public static function validateJWT(Request $request)
    {
        $token = $request->header('Authorization');
        
        // Remove 'Bearer ' prefix if exists
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (!$token) {
            return [
                'valid' => false,
                'message' => 'Authorization token is required',
                'code' => 401
            ];
        }

        $validation = JWTHelper::validateToken($token);

        if (!$validation['valid']) {
            return [
                'valid' => false,
                'message' => $validation['message'],
                'code' => 401
            ];
        }

        // Parents mode: a parent acts as one of their children. Requests without
        // the X-Acting-As header (all existing apps) keep the legacy behaviour.
        $acting = $request->header('X-Acting-As');
        if (!empty($acting)) {
            return self::resolveActingUser($validation['data'], $acting);
        }

        return [
            'valid' => true,
            'user' => $validation['data']
        ];
    }

    /** HTTP code of the last failed validateJWT, used by sendUnauthorized. */
    private static $lastInvalidCode = 401;

    /**
     * Parents mode. $acting is the encoded id (md5(id . imbuhan)) of a child.
     * On success user['user_id'] becomes the child's raw id and the parent's
     * raw id is kept in user['real_user_id'].
     */
    private static function resolveActingUser(array $jwtUser, $acting)
    {
        $deny = function () {
            self::$lastInvalidCode = 403;
            return [
                'valid' => false,
                'message' => ResponseController::getErrorMessage(101),
                'code' => 403
            ];
        };

        $imbuhan = (new \App\User())->imbuhan;
        $child = DB::table('m_user')
            ->whereRaw('md5(concat(id, ?)) = ?', [$imbuhan, $acting])
            ->where('status', 1)
            ->first(['id', 'id_parents', 'email', 'nama']);
        if (!$child || empty($child->id_parents) || (int) $child->id_parents !== (int) $jwtUser['user_id']) {
            return $deny();
        }

        $parent = DB::table('m_user')->where('id', $jwtUser['user_id'])->where('status', 1)->first(['tipe_akun']);
        if (!$parent || $parent->tipe_akun !== 'ortu') {
            return $deny();
        }

        $jwtUser['real_user_id'] = $jwtUser['user_id'];
        $jwtUser['user_id'] = $child->id;
        $jwtUser['email'] = $child->email;
        $jwtUser['nama'] = $child->nama;
        $jwtUser['acting_as'] = true;

        return [
            'valid' => true,
            'user' => $jwtUser
        ];
    }

    /**
     * Get Current User from JWT Token
     */
    public static function getCurrentUser(Request $request)
    {
        $validation = self::validateJWT($request);
        
        if (!$validation['valid']) {
            return null;
        }

        return $validation['user'];
    }

    /**
     * Generate JWT Token
     */
    public static function generateToken($userId, $email, $nama, $posisi = null)
    {
        return JWTHelper::generateToken($userId, $email, $nama, $posisi);
    }

    /**
     * Send Unauthorized Response (Token Invalid)
     */
    public static function sendUnauthorized($message = 'Unauthorized - Invalid or expired token')
    {
        $code = self::$lastInvalidCode;
        self::$lastInvalidCode = 401;
        return response()->json([
            'status' => (string) $code,
            'message' => $message,
            'code' => $code
        ], $code);
    }

    /**
     * Check if User is Admin
     */
    public static function isAdmin(Request $request)
    {
        $user = self::getCurrentUser($request);
        
        if (!$user) {
            return false;
        }

        // Admin memiliki posisi tertentu (sesuaikan dengan business logic)
        return isset($user['posisi']) && in_array($user['posisi'], [1, 2, 3]);
    }

    /**
     * Format Date to Indonesian
     */
    public static function formatTanggalIndo($date)
    {
        if (!$date) return '-';
        
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $split = explode('-', $date);
        if (count($split) == 3) {
            return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
        }
        
        return $date;
    }

    /**
     * Format DateTime to Indonesian
     */
    public static function formatDateTimeIndo($datetime)
    {
        if (!$datetime) return '-';
        
        $timestamp = strtotime($datetime);
        $tanggal = date('Y-m-d', $timestamp);
        $waktu = date('H:i', $timestamp);
        
        return self::formatTanggalIndo($tanggal) . ' ' . $waktu;
    }
}