<?php
require_once __DIR__ . '/BaseModel.php';

class SettingsModel extends BaseModel {

    /**
     * Mengambil semua pengaturan dari database (diasumsikan hanya ada 1 baris).
     * @return array
     */
    public function getSettings() {
        $sql = "SELECT * FROM settings LIMIT 1";
        $result = mysqli_query($this->db, $sql);
        return $result ? mysqli_fetch_assoc($result) : [];
    }

    /**
     * Memperbarui atau membuat baris pengaturan.
     * @param array $data Data pengaturan yang akan disimpan.
     * @return bool
     */
    public function updateSettings($data) {
        // Helper function to get actual table columns
        $getTableColumns = function($tableName) {
            $columns = [];
            $result = mysqli_query($this->db, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($this->db, $tableName) . "`");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $columns[] = $row['Field'];
                }
            }
            return $columns;
        };

        $current_settings = $this->getSettings();
        $tableColumns = $getTableColumns('settings');

        // Filter the input data to only include keys that are actual columns in the table
        $valid_data = array_filter(
            $data,
            fn($key) => in_array($key, $tableColumns),
            ARRAY_FILTER_USE_KEY
        );

        if ($current_settings) {
            // Mode UPDATE jika data sudah ada
            $fields = [];
            $params = [];
            $types = '';
            foreach ($valid_data as $key => $value) {
                $fields[] = "`$key` = ?";
                $params[] = $value;
                $types .= 's';
            }

            if (empty($fields)) return true; // Nothing to update

            $params[] = $current_settings['id'];
            $types .= 'i';
            $sql = "UPDATE settings SET " . implode(', ', $fields) . " WHERE id = ?";
        } else {
            // Mode INSERT jika data belum ada
            $keys = implode(', ', array_map(fn($k) => "`$k`", array_keys($valid_data)));
            $placeholders = rtrim(str_repeat('?,', count($valid_data)), ',');
            $params = array_values($valid_data);
            $types = str_repeat('s', count($valid_data));
            $sql = "INSERT INTO settings ($keys) VALUES ($placeholders)";
        }

        $stmt = mysqli_prepare($this->db, $sql);
        if (!$stmt) return false;
        
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        return mysqli_stmt_execute($stmt);
    }
}