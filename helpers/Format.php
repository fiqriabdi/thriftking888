<?php
/**
 * Format.php - Formatting utility functions
 * 
 * Provides formatting functions for currency, dates, URL slugs,
 * and text truncation.
 */

/**
 * Format amount as Indonesian Rupiah currency
 * 
 * Converts numeric value to formatted Rupiah string with proper
 * thousands separator and Rp prefix.
 * 
 * @param int|float $amount The amount to format
 * @param bool $show_cents Whether to show decimal places (default: false)
 * @return string Formatted Rupiah string (e.g., "Rp 1.234.567")
 */
function formatRupiah($amount, $show_cents = false)
{
    if (!is_numeric($amount)) {
        return 'Rp 0';
    }
    
    $amount = (int) $amount;
    
    if ($show_cents) {
        return 'Rp ' . number_format($amount, 2, ',', '.');
    }
    
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format date string to readable format
 * 
 * Converts date strings to formatted date output. Supports various
 * input formats and customizable output formats.
 * 
 * @param string $date The date string to format (ISO format or strtotime compatible)
 * @param string $format The desired output format using strftime/date format codes
 *                       (default: 'd M Y' for "01 Jan 2024")
 * @return string|false Formatted date string or false on invalid input
 */
function formatDate($date, $format = 'd M Y')
{
    if (empty($date)) {
        return '';
    }
    
    // Convert to timestamp if string
    if (is_string($date)) {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }
    } else {
        $timestamp = $date;
    }
    
    // Map common format aliases to PHP date format
    $format_map = [
        'short' => 'd M Y',
        'long' => 'd F Y',
        'datetime' => 'd M Y H:i',
        'time' => 'H:i',
        'iso' => 'Y-m-d',
        'iso-time' => 'Y-m-d H:i:s'
    ];
    
    $format = $format_map[$format] ?? $format;
    
    return date($format, $timestamp);
}

/**
 * Convert text to URL-safe slug
 * 
 * Converts text to lowercase, removes special characters, and replaces
 * spaces with hyphens to create URL-safe slugs.
 * 
 * @param string $text The text to convert
 * @param string $separator Character to use as separator (default: '-')
 * @param int $limit Maximum length of slug (0 = no limit, default: 0)
 * @return string URL-safe slug
 */
function slugify($text, $separator = '-', $limit = 0)
{
    if (!is_string($text)) {
        return '';
    }
    
    // Convert to lowercase
    $slug = strtolower($text);
    
    // Replace spaces with separator
    $slug = preg_replace('/[\s_]+/', $separator, $slug);
    
    // Remove special characters, keep only alphanumeric, hyphens, and underscores
    $slug = preg_replace('/[^a-z0-9\-_]/', '', $slug);
    
    // Remove multiple consecutive separators
    $slug = preg_replace('/' . preg_quote($separator) . '{2,}/', $separator, $slug);
    
    // Trim separator from start and end
    $slug = trim($slug, $separator);
    
    // Apply length limit if specified
    if ($limit > 0) {
        // Try to cut at word boundary
        if (strlen($slug) > $limit) {
            $slug = substr($slug, 0, $limit);
            // Remove partial word at end
            $last_sep = strrpos($slug, $separator);
            if ($last_sep !== false) {
                $slug = substr($slug, 0, $last_sep);
            }
        }
    }
    
    return $slug;
}

/**
 * Truncate text to specified length
 * 
 * Shortens text to specified length and optionally appends ellipsis.
 * Can truncate at word boundary to avoid cutting words.
 * 
 * @param string $text The text to truncate
 * @param int $length Maximum length in characters
 * @param string $suffix Suffix to append if text is truncated (default: '...')
 * @param bool $word_boundary Whether to truncate at word boundary (default: true)
 * @return string Truncated text
 */
function truncate($text, $length = 100, $suffix = '...', $word_boundary = true)
{
    if (!is_string($text)) {
        return '';
    }
    
    if (strlen($text) <= $length) {
        return $text;
    }
    
    if ($word_boundary) {
        // Truncate to slightly longer to find word boundary
        $text = substr($text, 0, $length);
        
        // Find last space
        $last_space = strrpos($text, ' ');
        if ($last_space !== false) {
            $text = substr($text, 0, $last_space);
        }
    } else {
        $text = substr($text, 0, $length);
    }
    
    // Remove trailing punctuation
    $text = rtrim($text, '.,;:!?\- ');
    
    return $text . $suffix;
}

/**
 * Memformat nomor telepon ke standar internasional WhatsApp (62).
 * @param string $number Nomor telepon yang akan diformat.
 * @return string Nomor telepon yang sudah diformat, atau string kosong jika input kosong.
 */
function formatWhatsappNumber($number) {
    if (empty($number)) {
        return '';
    }
    // 1. Hapus semua karakter non-numerik
    $cleaned = preg_replace('/[^0-9]/', '', $number);
    
    // 2. Jika nomor sudah dalam format internasional (diawali 62), kembalikan langsung.
    if (substr($cleaned, 0, 2) === '62') {
        return $cleaned;
    }
    
    // 3. Jika nomor diawali '0', ganti dengan '62'.
    if (substr($cleaned, 0, 1) === '0') {
        return '62' . substr($cleaned, 1);
    }
    
    // 4. Untuk kasus lain (misal, nomor langsung 812...), tambahkan '62' di depan.
    return '62' . $cleaned;
}
