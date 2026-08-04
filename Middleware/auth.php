<?php
/**
 * File: middleware/Auth.php
 * Deskripsi: Kelas middleware otentikasi dan otorisasi terpusat.
 */

class auth {
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user']) && !empty($_SESSION['user']);
    }

    public static function getUser() {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function getRole() {
        self::startSession();
        return $_SESSION['user']['role'] ?? null;
    }

    public static function requireLogin($redirectTo = 'auth/login') {
        if (!self::isLoggedIn()) {
            self::redirect($redirectTo);
        }
        return true;
    }

    public static function requireRole($role, $message = null) {
        if (!self::isLoggedIn() || self::getRole() !== $role) {
            $redirectUrl = 'auth/login';
            if ($message) { // Jika ada pesan, tambahkan ke URL
                $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
                $redirectUrl .= $separator . 'pesan=' . urlencode($message);
            }
            self::redirect($redirectUrl);
        }
        return true;
    }

    public static function requireAnyRole($roles = []) {
        if (!self::isLoggedIn()) {
            self::redirect('auth/login');
        }
        
        if (!in_array(self::getRole(), $roles)) {
            $redirectUrl = (self::getRole() === 'admin') ? 'admin/dashboard' : ''; // Redirect ke root jika bukan admin atau ke dashboard admin
            self::redirect($redirectUrl);
        }
        return true;
    }

    public static function requireGuest() {
        if (self::isLoggedIn()) {
            $role = self::getRole();
            $redirectUrl = ($role === 'admin') ? 'admin/dashboard' : ''; // Redirect ke root jika bukan admin
            self::redirect($redirectUrl);
        }
        return true;
    }

    public static function guard($role = null) {
        if ($role === null) {
            return self::requireLogin();
        } else {
            return self::requireRole($role);
        }
    }

    public static function logout($redirectTo = 'index.php') {
        self::startSession();
        
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
        self::redirect($redirectTo);
    }

    public static function hasRole($role) {
        return self::isLoggedIn() && self::getRole() === $role;
    }

    public static function isAdmin() {
        return self::hasRole('admin');
    }

    public static function isPelanggan() {
        return self::hasRole('pelanggan');
    }

    private static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function redirect($url) {
        if (strpos($url, 'http') !== 0 && defined('BASE_URL')) {
            $url = BASE_URL . ltrim($url, '/');
        }
        header("Location: " . $url);
        exit();
    }
} // <--- KURUNG KURAWAL INI PENUTUP KELAS AUTH YANG BENAR
?>