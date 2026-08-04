<?php

class Router {
    private static $routes = [];
    private static $currentRoute = null;
    private static $routeParams = [];

    /**
     * Mendaftarkan rute baru berdasarkan metode HTTP
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path Jalur rute (e.g., '/produk/:id' atau '/katalog/:slug')
     * @param string $handler Format pengontrol 'ControllerName@methodName'
     */
    public static function register($method, $path, $handler) {
        $method = strtoupper($method);
        if (!isset(self::$routes[$method])) {
            self::$routes[$method] = [];
        }
        
        // Ekstraksi nama parameter dari path (misal: ':id' diambil nama 'id')
        $paramNames = [];
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $path, $matches);
        if (!empty($matches[1])) {
            $paramNames = $matches[1];
        }

        self::$routes[$method][] = [
            'path' => $path,
            'handler' => $handler,
            'paramNames' => $paramNames,
            'pattern' => self::pathToPattern($path)
        ];
    }

    /**
     * Registrasi rute GET
     */
    public static function get($path, $handler) {
        self::register('GET', $path, $handler);
    }

    /**
     * Registrasi rute POST
     */
    public static function post($path, $handler) {
        self::register('POST', $path, $handler);
    }

    /**
     * Registrasi rute PUT
     */
    public static function put($path, $handler) {
        self::register('PUT', $path, $handler);
    }

    /**
     * Registrasi rute DELETE
     */
    public static function delete($path, $handler) {
        self::register('DELETE', $path, $handler);
    }

    /**
     * Mengalirkan permintaan HTTP ke Controller yang cocok
     */
    public static function dispatch($uri = null) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($uri === null) {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        }

        // Membersihkan query string jika ada (?page=1, dsb)
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        if (!isset(self::$routes[$method])) {
            return null;
        }

        foreach (self::$routes[$method] as $route) {
            $params = [];
            if (self::matchRoute($route, $uri, $params)) {
                self::$currentRoute = $route;
                self::$routeParams = $params;
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'path' => $route['path']
                ];
            }
        }

        return null;
    }

    public static function getCurrentRoute() {
        return self::$currentRoute;
    }

    public static function getParams() {
        return self::$routeParams;
    }

    public static function getParam($name, $default = null) {
        return self::$routeParams[$name] ?? $default;
    }

    /**
     * Mengubah path statis menjadi Pola Regex (Regular Expression)
     * Contoh: '/produk/:id' menjadi '^/produk/([^/]+)$'
     */
    private static function pathToPattern($path) {
        // Mengubah parameter :nama menjadi grup regex capture
        $pattern = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/', '([^/]+)', $path);
        // Menambahkan pembatas awal dan akhir string rute
        return '#^' . $pattern . '$#i';
    }

    /**
     * Mencocokkan URI permintaan dengan pola rute yang terdaftar
     */
    private static function matchRoute($route, $uri, &$params) {
        $matches = [];
        if (preg_match($route['pattern'], $uri, $matches)) {
            // Hapus hasil full match pada indeks ke-0
            array_shift($matches); 
            
            // Pasangkan nama parameter dengan nilainya secara asosiatif
            foreach ($matches as $index => $value) {
                if (isset($route['paramNames'][$index])) {
                    $paramName = $route['paramNames'][$index];
                    $params[$paramName] = urldecode($value);
                } else {
                    $params[$index] = urldecode($value);
                }
            }
            return true;
        }
        return false;
    }

    public static function getRoutes() {
        return self::$routes;
    }

    public static function clearRoutes() {
        self::$routes = [];
        self::$currentRoute = null;
        self::$routeParams = [];
    }

    /**
     * Memecah string handler 'ProdukController@detail' menjadi nama kelas dan metodenya
     */
    public static function parseHandler($handler) {
        if (strpos($handler, '@') === false) {
            return null;
        }

        list($controller, $method) = explode('@', $handler, 2);
        return [
            'controller' => trim($controller),
            'method' => trim($method)
        ];
    }
}