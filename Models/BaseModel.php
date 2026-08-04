<?php
/**
 * File: Models/basemodel.php
 */
class BaseModel {
    protected $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Contoh fungsi umum untuk menghitung jumlah data di tabel apapun
    public function countRows($table, $condition = "1=1") {
        // Basic validation to prevent SQL injection via table name or condition
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            error_log("Invalid table name passed to countRows: $table");
            return 0;
        }

        // Reject obviously malicious condition patterns
        if (preg_match('/[;]|--|\/\*/', $condition)) {
            error_log("Invalid condition passed to countRows: $condition");
            return 0;
        }

        $sql = "SELECT COUNT(*) as total FROM `" . $table . "` WHERE " . $condition;
        $result = mysqli_query($this->db, $sql);
        if (!$result) return 0;
        $data = mysqli_fetch_assoc($result);
        return $data['total'] ?? 0;
    }
}