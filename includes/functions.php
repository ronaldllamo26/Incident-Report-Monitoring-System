<?php

/**
 * Sanitize output — always use this when displaying user input
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date
 */
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime(string $date): string {
    return date('M d, Y g:i A', strtotime($date));
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $colors = [
        'pending'     => 'warning',
        'in_progress' => 'primary',
        'resolved'    => 'success',
        'closed'      => 'secondary',
    ];
    $color = $colors[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

/**
 * Get severity badge HTML
 */
function severityBadge(string $severity): string {
    $colors = [
        'low'      => 'success',
        'medium'   => 'warning',
        'high'     => 'danger',
        'critical' => 'dark',
    ];
    $color = $colors[$severity] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">" . ucfirst($severity) . "</span>";
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 50): string {
    return strlen($text) > $length
        ? substr($text, 0, $length) . '...'
        : $text;
}

/**
 * Redirect with message
 */
function redirectWith(string $url, string $type, string $msg): void {
    header('Location: ' . $url . '?' . $type . '=' . urlencode($msg));
    exit;
}