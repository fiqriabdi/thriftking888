-- ========================================================
-- LAPORAN PERFORMA INDEKS THRIFTKING888
-- ========================================================

-- 1. Melihat Indeks yang TIDAK PERNAH digunakan
-- Indeks ini sebaiknya dihapus karena memperlambat INSERT/UPDATE tanpa memberi manfaat SELECT
SELECT 
    object_schema AS 'Database',
    object_name AS 'Tabel',
    index_name AS 'Indeks'
FROM sys.schema_unused_indexes 
WHERE object_schema = 'thriftking888';

-- 2. Statistik Penggunaan Indeks (Efisiensi)
-- Melihat seberapa sering indeks digunakan dan seberapa besar biaya operasinya
SELECT 
    table_name AS 'Tabel',
    index_name AS 'Indeks',
    rows_selected AS 'Baris Terpilih',
    rows_inserted AS 'Baris Disisipkan',
    rows_updated AS 'Baris Diperbarui'
FROM sys.schema_index_statistics 
WHERE table_schema = 'thriftking888'
ORDER BY rows_selected DESC;

-- 3. Melihat kueri yang paling lambat (Slow Queries)
-- Membantu menentukan kolom mana yang butuh indeks baru
SELECT query, full_scan, exec_count, err_count, warn_count, total_latency
FROM sys.statement_analysis
WHERE db = 'thriftking888'
ORDER BY total_latency DESC
LIMIT 10;