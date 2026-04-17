<?php
/**
 * Iron Dome: L7 Application Firewall
 * Handles site-wide rate limiting and automated bot defense.
 */
class Firewall {
    
    // Limits
    private const WINDOW_SECONDS     = 60;   // 1 Minute window
    private const WARNING_THRESHOLD  = 80;   // Show warnings or slow down
    private const BLOCK_THRESHOLD    = 150;  // 429 Too Many Requests
    private const BAN_THRESHOLD      = 400;  // Permanent IP Ban

    /**
     * Entry point for site-wide protection
     */
    public static function protect() {
        global $pdo;
        
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/db.php';
        }

        $ip = self::getIp();
        
        // 1. Check if already banned
        self::checkBlacklist($ip);

        // 2. Track activity and enforce limits
        self::enforceRateLimit($ip);

        // 3. Bot/Honeypot detection on POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::checkHoneypots($ip);
        }
    }

    private static function getIp() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return $ip;
    }

    private static function checkBlacklist($ip) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, reason FROM banned_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $ban = $stmt->fetch();

        if ($ban) {
            http_response_code(403);
            die("<h2>403 Forbidden</h2><p>Ang iyong IP address ({$ip}) ay permanenteng na-ban sa system na ito.<br>Reason: " . ($ban['reason'] ?? 'Violation of security policies') . "</p>");
        }
    }

    private static function enforceRateLimit($ip) {
        // ── DEV BYPASS: Huwag i-limit ang localhost ─────────
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return;
        }
        // ──────────────────────────────────────────────────

        global $pdo;
        $action = 'GLOBAL_TRAFFIC';
        
        // Reuse the logic from functions.php but optimized for Firewall
        $stmt = $pdo->prepare("SELECT hit_count, window_start FROM rate_limits WHERE identifier = ? AND action = ?");
        $stmt->execute([$ip, $action]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->prepare("INSERT INTO rate_limits (identifier, action, hit_count, window_start) VALUES (?, ?, 1, NOW())")
                ->execute([$ip, $action]);
            return;
        }

        $windowExpiry = strtotime($row['window_start']) + self::WINDOW_SECONDS;
        $hitCount = (int)$row['hit_count'];

        if (time() > $windowExpiry) {
            // New window
            $pdo->prepare("UPDATE rate_limits SET hit_count = 1, window_start = NOW() WHERE identifier = ? AND action = ?")
                ->execute([$ip, $action]);
        } else {
            // Increment
            $hitCount++;
            $pdo->prepare("UPDATE rate_limits SET hit_count = ? WHERE identifier = ? AND action = ?")
                ->execute([$hitCount, $ip, $action]);

            // ENFORCE
            if ($hitCount >= self::BAN_THRESHOLD) {
                self::permanentBan($ip, "DDoS/Flood Attack Detected (>".self::BAN_THRESHOLD." req/min)");
            } elseif ($hitCount >= self::BLOCK_THRESHOLD) {
                http_response_code(429);
                die("<h2>429 Too Many Requests</h2><p>Masyado kang mabilis, QCitizen! Mag-hintay ng isang minuto bago sumubok ulit.</p>");
            }
        }
    }

    private static function checkHoneypots($ip) {
        // Honeypot field detection
        // If these fields (meant to be hidden) are filled, it's a bot.
        $honeypots = ['full_name_confirm', 'website_url', 'phone_alt'];
        foreach ($honeypots as $hp) {
            if (!empty($_POST[$hp])) {
                self::permanentBan($ip, "Bot/Honeypot Trap Triggered ($hp filled)");
            }
        }
    }

    private static function permanentBan($ip, $reason) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT IGNORE INTO banned_ips (ip_address, reason, banned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $reason]);
        
        http_response_code(403);
        die("<h2>Security Violation</h2><p>Security system blocked your request. Action logged.</p>");
    }
}
