<?php
/**
 * Helper: RajaOngkir.php
 */
class RajaOngkir {
    private static $apiKey = null;
    private static $baseUrl = "https://api.rajaongkir.com/starter/";

    private static function init() {
        if (self::$apiKey === null) {
            self::$apiKey = env('RAJAONGKIR_API_KEY', '');
        }
    }

    private static function makeRequest($endpoint, $params = [], $method = 'GET') {
        self::init();
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => self::$baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["key: " . self::$apiKey],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false // Diubah ke false untuk menghindari masalah sertifikat SSL di XAMPP/Local
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
            $options[CURLOPT_HTTPHEADER][] = "content-type: application/x-www-form-urlencoded";
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            error_log("RajaOngkir cURL Error: " . $error);
            return null;
        }

        $decoded = json_decode($response, true);
        
        // Debugging: Log jika RajaOngkir mengembalikan status error (bukan 200)
        if (isset($decoded['rajaongkir']['status']['code']) && $decoded['rajaongkir']['status']['code'] !== 200) {
            error_log("RajaOngkir API Error: " . $decoded['rajaongkir']['status']['description']);
        }

        return $decoded;
    }

    public static function getCities() {
        $data = self::makeRequest("city");
        if (!$data || !isset($data['rajaongkir']['results'])) return [];
        return $data['rajaongkir']['results'] ?? [];
    }

    public static function getCost($origin, $destination, $weight, $courier = 'jne') {
        if ($destination <= 0 || $weight <= 0) return ['success' => false, 'message' => 'Parameter pengiriman tidak valid.'];

        $params = [
            'origin' => $origin,
            'destination' => $destination,
            'weight' => $weight,
            'courier' => $courier
        ];

        $data = self::makeRequest("cost", $params, 'POST');
        
        if ($data && isset($data['rajaongkir']['results'][0]['costs'][0])) {
            $costDetail = $data['rajaongkir']['results'][0]['costs'][0];
            return [
                'success' => true,
                'value' => $costDetail['cost'][0]['value'] ?? 0,
                'service' => $costDetail['service'] ?? 'Unknown'
            ];
        }
        return ['success' => false, 'message' => 'Gagal mendapatkan ongkir.'];
    }
}