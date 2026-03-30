<?php
require_once __DIR__ . '/../config/db.php';

class Incident {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    // ── EXISTING METHODS (unchanged) ──────────────────

    public function getByReporter(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.name AS category_name
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            WHERE i.reporter_id = ?
            ORDER BY i.reported_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.name AS category_name,
                   c.sla_critical, c.sla_high, c.sla_medium, c.sla_low,
                   u.name  AS reporter_name,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone,
                   a.name  AS responder_name
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll(array $filters = []): array {
        $where  = [];
        $params = [];

        if (!empty($filters['status']))      { $where[] = 'i.status = ?';      $params[] = $filters['status']; }
        if (!empty($filters['category_id'])) { $where[] = 'i.category_id = ?'; $params[] = $filters['category_id']; }
        if (!empty($filters['severity']))    { $where[] = 'i.severity = ?';    $params[] = $filters['severity']; }
        if (!empty($filters['assigned_to'])) { $where[] = 'i.assigned_to = ?'; $params[] = $filters['assigned_to']; }

        $sql = "
            SELECT i.*, c.name AS category_name,
                   u.name AS reporter_name,
                   a.name AS responder_name
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
        ";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY i.priority ASC, i.reported_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAttachments(int $incidentId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM attachments WHERE incident_id = ?");
        $stmt->execute([$incidentId]);
        return $stmt->fetchAll();
    }

    public function getStatusLogs(int $incidentId): array {
        $stmt = $this->pdo->prepare("
            SELECT sl.*, u.name AS changed_by_name
            FROM status_logs sl
            JOIN users u ON sl.changed_by = u.id
            WHERE sl.incident_id = ?
            ORDER BY sl.changed_at ASC
        ");
        $stmt->execute([$incidentId]);
        return $stmt->fetchAll();
    }

    public function getResponses(int $incidentId): array {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.name AS responder_name
            FROM responses r
            JOIN users u ON r.responder_id = u.id
            WHERE r.incident_id = ?
            ORDER BY r.responded_at ASC
        ");
        $stmt->execute([$incidentId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $incidentId, string $newStatus, int $changedBy, string $oldStatus, string $remarks = ''): bool {
        $this->pdo->prepare("
            UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?
        ")->execute([$newStatus, $incidentId]);

        // Mark acknowledged_at pag first time nag-respond ang responder
        if ($oldStatus === 'pending' && $newStatus === 'in_progress') {
            $this->pdo->prepare("
                UPDATE incidents SET acknowledged_at = NOW() WHERE id = ? AND acknowledged_at IS NULL
            ")->execute([$incidentId]);
        }

        $log = $this->pdo->prepare("
            INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $log->execute([$incidentId, $changedBy, $oldStatus, $newStatus, $remarks]);
    }

    public function assignResponder(int $incidentId, int $responderId): bool {
        $stmt = $this->pdo->prepare("
            UPDATE incidents SET assigned_to = ?, updated_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$responderId, $incidentId]);
    }

    public function addResponse(int $incidentId, int $responderId, string $message): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO responses (incident_id, responder_id, message) VALUES (?, ?, ?)
        ");
        return $stmt->execute([$incidentId, $responderId, $message]);
    }

    public function getCountsByStatus(): array {
        $rows   = $this->pdo->query("SELECT status, COUNT(*) AS count FROM incidents GROUP BY status")->fetchAll();
        $counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) $counts[$r['status']] = $r['count'];
        }
        return $counts;
    }

    public function getForMap(): array {
        $stmt = $this->pdo->query("
            SELECT id, title, status, severity, latitude, longitude, location
            FROM incidents WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ");
        return $stmt->fetchAll();
    }

    // ── NEW METHODS ────────────────────────────────────

    /**
     * Auto-assign responder + compute SLA deadline + set priority
     * Tinatawag agad pagkatapos mag-INSERT ng bagong incident
     */
    public function processNewIncident(int $incidentId, int $categoryId, string $severity): void {
        // Kunin ang category defaults
        $cat = $this->pdo->prepare("
            SELECT default_responder_id, sla_critical, sla_high, sla_medium, sla_low
            FROM categories WHERE id = ?
        ");
        $cat->execute([$categoryId]);
        $category = $cat->fetch();

        if (!$category) return;

        // Compute SLA deadline (in minutes)
        $slaMinutes = match($severity) {
            'critical' => (int)$category['sla_critical'],
            'high'     => (int)$category['sla_high'],
            'medium'   => (int)$category['sla_medium'],
            default    => (int)$category['sla_low'],
        };

        // Priority level (1=highest, 4=lowest)
        $priority = match($severity) {
            'critical' => 1,
            'high'     => 2,
            'medium'   => 3,
            default    => 4,
        };

        $slaDeadline     = date('Y-m-d H:i:s', time() + ($slaMinutes * 60));
        $defaultResponder = $category['default_responder_id'];

        // Update incident
        $stmt = $this->pdo->prepare("
            UPDATE incidents
            SET sla_deadline  = ?,
                priority      = ?,
                assigned_to   = COALESCE(?, assigned_to)
            WHERE id = ?
        ");
        $stmt->execute([$slaDeadline, $priority, $defaultResponder, $incidentId]);
    }

    /**
     * Kunin ang lahat ng incidents na nag-breach ng SLA at hindi pa escalated
     */
    public function getBreachedUnescalated(): array {
        $stmt = $this->pdo->query("
            SELECT i.*, c.name AS category_name,
                   u.name AS reporter_name,
                   a.name AS responder_name,
                   u.email AS reporter_email
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.sla_deadline < NOW()
              AND i.status NOT IN ('resolved','closed')
              AND i.escalated = 0
        ");
        return $stmt->fetchAll();
    }

    /**
     * Mark as escalated + breached
     */
    public function markEscalated(int $incidentId): void {
        $this->pdo->prepare("
            UPDATE incidents
            SET escalated = 1, sla_breached = 1
            WHERE id = ?
        ")->execute([$incidentId]);
    }

    /**
     * Kunin ang feedback ng isang incident
     */
    public function getFeedback(int $incidentId): array|false {
        $stmt = $this->pdo->prepare("
            SELECT f.*, u.name AS citizen_name
            FROM feedback f
            JOIN users u ON f.citizen_id = u.id
            WHERE f.incident_id = ?
        ");
        $stmt->execute([$incidentId]);
        return $stmt->fetch();
    }

    /**
     * Submit feedback
     */
    public function submitFeedback(int $incidentId, int $citizenId, int $rating, string $comment): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO feedback (incident_id, citizen_id, rating, comment)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
        ");
        return $stmt->execute([$incidentId, $citizenId, $rating, $comment]);
    }

    /**
     * Responder performance stats
     */
    public function getResponderStats(int $responderId): array {
        // Average response time (minutes)
        $avgStmt = $this->pdo->prepare("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, reported_at, acknowledged_at)) AS avg_response
            FROM incidents
            WHERE assigned_to = ? AND acknowledged_at IS NOT NULL
        ");
        $avgStmt->execute([$responderId]);
        $avg = $avgStmt->fetch();

        // Resolved count
        $resolvedStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS count FROM incidents
            WHERE assigned_to = ? AND status IN ('resolved','closed')
        ");
        $resolvedStmt->execute([$responderId]);
        $resolved = $resolvedStmt->fetch();

        // Average rating
        $ratingStmt = $this->pdo->prepare("
            SELECT AVG(f.rating) AS avg_rating
            FROM feedback f
            JOIN incidents i ON f.incident_id = i.id
            WHERE i.assigned_to = ?
        ");
        $ratingStmt->execute([$responderId]);
        $rating = $ratingStmt->fetch();

        return [
            'avg_response_mins' => round($avg['avg_response'] ?? 0),
            'resolved_count'    => (int)($resolved['count'] ?? 0),
            'avg_rating'        => round((float)($rating['avg_rating'] ?? 0), 1),
        ];
    }

    /**
     * Get SLA status ng isang incident
     * Returns: 'ok', 'warning' (75% ng time consumed), 'breached'
     */
    public function getSlaStatus(array $incident): array {
        if (!$incident['sla_deadline']) {
            return ['status' => 'none', 'label' => 'No SLA', 'minutes_left' => null];
        }

        if (in_array($incident['status'], ['resolved', 'closed'])) {
            return ['status' => 'done', 'label' => 'Resolved', 'minutes_left' => null];
        }

        $now      = time();
        $deadline = strtotime($incident['sla_deadline']);
        $reported = strtotime($incident['reported_at']);
        $total    = $deadline - $reported;
        $left     = $deadline - $now;

        if ($left <= 0) {
            return [
                'status'      => 'breached',
                'label'       => 'SLA Breached!',
                'minutes_left' => 0,
                'percent'     => 100,
            ];
        }

        $percent = round((1 - ($left / $total)) * 100);
        $minsLeft = round($left / 60);

        return [
            'status'      => $percent >= 75 ? 'warning' : 'ok',
            'label'       => $minsLeft < 60
                ? "{$minsLeft} mins left"
                : round($minsLeft / 60, 1) . ' hrs left',
            'minutes_left' => $minsLeft,
            'percent'     => $percent,
        ];
    }
}