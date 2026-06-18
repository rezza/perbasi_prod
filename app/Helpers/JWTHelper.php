<?php

namespace App\Helpers;

use Exception;

class JWTHelper
{
    private static $encrypt_method = "AES-256-CBC";
    
    /**
     * Get Secret Key from ENV
     */
    private static function getSecretKey()
    {
        return env('JWT_SECRET', 'PERBASI_SECRET_KEY_2024_GUYUB_APP');
    }
    
    /**
     * Generate JWT Token
     */
    public static function generateToken($userId, $email, $nama, $posisi = null)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'email' => $email,
            'nama' => $nama,
            'posisi' => $posisi,
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24 * 30) // 30 days expiration
        ]);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        
        return $jwt;
    }

    /**
     * Validate and Decode JWT Token
     */
    public static function validateToken($token)
    {
        if (!$token) {
            return ['valid' => false, 'message' => 'Token is required'];
        }

        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            return ['valid' => false, 'message' => 'Invalid token format'];
        }

        list($header, $payload, $signature) = $tokenParts;

        // Verify signature
        $base64UrlHeader = $header;
        $base64UrlPayload = $payload;
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlExpectedSignature = self::base64UrlEncode($expectedSignature);

        if ($signature !== $base64UrlExpectedSignature) {
            return ['valid' => false, 'message' => 'Invalid token signature'];
        }

        // Decode payload
        $payloadData = json_decode(self::base64UrlDecode($payload), true);

        // Check expiration
        if (isset($payloadData['exp']) && time() > $payloadData['exp']) {
            return ['valid' => false, 'message' => 'Token has expired'];
        }

        return ['valid' => true, 'data' => $payloadData];
    }

    /**
     * Get User Data from Token
     */
    public static function getUserFromToken($token)
    {
        $validation = self::validateToken($token);
        
        if (!$validation['valid']) {
            return null;
        }

        return $validation['data'];
    }

    /**
     * Base64 URL Encode
     */
    private static function base64UrlEncode($text)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    /**
     * Base64 URL Decode
     */
    private static function base64UrlDecode($text)
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $text));
    }

    /**
     * Encrypt Data
     */
    public static function encrypt($data)
    {
        $key = hash('sha256', self::$secret_key);
        $iv = substr(hash('sha256', 'perbasi_iv'), 0, 16);
        
        $output = openssl_encrypt($data, self::$encrypt_method, $key, 0, $iv);
        return base64_encode($output);
    }

    /**
     * Decrypt Data
     */
    public static function decrypt($data)
    {
        $key = hash('sha256', self::$secret_key);
        $iv = substr(hash('sha256', 'perbasi_iv'), 0, 16);
        
        return openssl_decrypt(base64_decode($data), self::$encrypt_method, $key, 0, $iv);
    }
}
