<?php
// includes/security.php

/**
 * Universal Security Hardening for EduRemarks
 * Fingerprint security removed to prevent session mismatch on online servers.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

class Security {

    /** Generate or retrieve CSRF token */
    public static function csrf_token() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /** Verify CSRF token – optional */
    public static function verify_csrf($token) {

        if (defined('DISABLE_CSRF') && DISABLE_CSRF) {
            return true;
        }

        return hash_equals(self::csrf_token(), $token);
    }

    /** Sanitize input recursively */
    public static function sanitize($data) {

        if (is_array($data)) {

            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value);
            }

        } else {

            $data = trim($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        }

        return $data;
    }

    /** Initialize security system */
    public static function init() {

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // Ensure CSRF token exists
        self::csrf_token();
    }

    /** Rate Limiting Orchestrator */
    public static function checkRateLimit($action, $limit = 5, $period = 60) {

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $key = 'rate_limit_' . $action;

        if (!isset($_SESSION[$key])) {

            $_SESSION[$key] = [
                'count' => 1,
                'start' => time()
            ];

            return true;
        }

        $elapsed = time() - $_SESSION[$key]['start'];

        if ($elapsed > $period) {

            $_SESSION[$key] = [
                'count' => 1,
                'start' => time()
            ];

            return true;
        }

        if ($_SESSION[$key]['count'] >= $limit) {
            return false;
        }

        $_SESSION[$key]['count']++;

        return true;
    }

    /** Validate the current request */
    public static function validateRequest() {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Origin Validation
            $allowed_origin =
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . "://"
                . $_SERVER['HTTP_HOST'];

            $origin =
                $_SERVER['HTTP_ORIGIN']
                ?? $_SERVER['HTTP_REFERER']
                ?? '';

            if (!empty($origin) && strpos($origin, $allowed_origin) !== 0) {

                return [
                    'status' => false,
                    'message' => 'Invalid request origin.'
                ];

            }

            // CSRF token validation
            $token =
                $_POST['csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? '';

            if (!self::verify_csrf($token)) {

                return [
                    'status' => false,
                    'message' => 'CSRF verification failed. Request blocked.'
                ];

            }

        }

        return ['status' => true];
    }

    /** Apply security headers */
    public static function applyHeaders() {

        if (!headers_sent()) {

            header("X-Frame-Options: SAMEORIGIN");
            header("X-Content-Type-Options: nosniff");
            header("X-XSS-Protection: 1; mode=block");
            header("Referrer-Policy: strict-origin-when-cross-origin");

            header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:;");

        }

    }

}

/* ================= GLOBAL CONFIG ================= */

// Disable CSRF globally if needed
if (!defined('DISABLE_CSRF')) {
    define('DISABLE_CSRF', true);
}


/* ================= AUTO RUN ================= */

Security::init();
Security::applyHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $validation = Security::validateRequest();

    if (!$validation['status']) {

        header('HTTP/1.1 403 Forbidden');

        die(json_encode([
            'success' => false,
            'message' => $validation['message']
        ]));

    }

}