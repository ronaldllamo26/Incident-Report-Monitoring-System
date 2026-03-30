<?php
require_once __DIR__ . '/../config/db.php';

class Incident {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

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
                   u.name AS reporter_name, u.email AS reporter_email, u.phone AS reporter_phone,
                   a.name AS responder_name
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

        if (!empty($filters['status'])) {
            $where[]  = 'i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $where[]  = 'i.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['severity'])) {
            $where[]  = 'i.severity = ?';
            $params[] = $filters['severity'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[]  = 'i.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

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
        $sql .= ' ORDER BY i.reported_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAttachments(int $incidentId): array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM attachments WHERE incident_id = ? ORDER BY uploaded_at ASC
        ");
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
        // Update incident
        $stmt = $this->pdo->prepare("
            UPDATE incidents SET status = ?, updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$newStatus, $incidentId]);

        // Log it
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
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) AS count FROM incidents GROUP BY status
        ");
        $rows   = $stmt->fetchAll();
        $counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) $counts[$r['status']] = $r['count'];
        }
        return $counts;
    }

    public function getForMap(): array {
        $stmt = $this->pdo->query("
            SELECT id, title, status, severity, latitude, longitude, location
            FROM incidents
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ");
        return $stmt->fetchAll();
    }
}