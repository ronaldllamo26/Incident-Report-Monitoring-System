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
            SELECT i.*,
                   c.name AS category_name,
                   c.sla_critical, c.sla_high, c.sla_medium, c.sla_low,
                   COALESCE(u.name,  i.anon_name,  'Anonymous') AS reporter_name,
                   COALESCE(u.email, i.anon_email, '')          AS reporter_email,
                   COALESCE(u.phone, i.anon_phone, '')          AS reporter_phone,
                   a.name AS responder_name
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAll(array $filters = [], ?int $limit = null, ?int $offset = null): array {
        $where  = [];
        $params = [];

        if (!empty($filters['status']))      { $where[] = 'i.status = ?';      $params[] = $filters['status']; }
        if (!empty($filters['category_id'])) { $where[] = 'i.category_id = ?'; $params[] = $filters['category_id']; }
        if (!empty($filters['severity']))    { $where[] = 'i.severity = ?';    $params[] = $filters['severity']; }
        if (!empty($filters['assigned_to'])) { $where[] = 'i.assigned_to = ?'; $params[] = $filters['assigned_to']; }
        if (!empty($filters['date_from']))   { $where[] = 'DATE(i.reported_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'DATE(i.reported_at) <= ?'; $params[] = $filters['date_to']; }

        $sql = "
            SELECT i.*,
                   c.name AS category_name,
                   COALESCE(u.name, i.anon_name, 'Anonymous') AS reporter_name,
                   a.name AS responder_name
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
        ";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY i.priority ASC, i.reported_at DESC';

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countTotal(array $filters = []): int {
        $where  = [];
        $params = [];

        if (!empty($filters['status']))      { $where[] = 'status = ?';      $params[] = $filters['status']; }
        if (!empty($filters['category_id'])) { $where[] = 'category_id = ?'; $params[] = $filters['category_id']; }
        if (!empty($filters['severity']))    { $where[] = 'severity = ?';    $params[] = $filters['severity']; }
        if (!empty($filters['assigned_to'])) { $where[] = 'assigned_to = ?'; $params[] = $filters['assigned_to']; }
        if (!empty($filters['date_from']))   { $where[] = 'DATE(reported_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'DATE(reported_at) <= ?'; $params[] = $filters['date_to']; }

        $sql = "SELECT COUNT(*) FROM incidents";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
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
            LEFT JOIN users u ON sl.changed_by = u.id
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

        if ($oldStatus === 'pending' && $newStatus === 'in_progress') {
            $this->pdo->prepare("
                UPDATE incidents SET acknowledged_at = NOW() WHERE id = ? AND acknowledged_at IS NULL
            ")->execute([$incidentId]);
        }

        if ($newStatus === 'resolved') {
            $this->pdo->prepare("
                UPDATE incidents SET resolved_at = NOW() WHERE id = ? AND resolved_at IS NULL
            ")->execute([$incidentId]);
        }

        if ($newStatus === 'closed') {
            $this->pdo->prepare("
                UPDATE incidents SET closed_at = NOW() WHERE id = ? AND closed_at IS NULL
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

    public function getRecentPublicReports(int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT i.title, c.name as category, i.location, i.reported_at, i.status
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            WHERE i.status != 'rejected'
            ORDER BY i.reported_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function processNewIncident(int $incidentId, int $categoryId, string $severity): void {
        require_once __DIR__ . '/../includes/AIService.php';

        // Fetch incident details for AI analysis
        $stmt = $this->pdo->prepare("
            SELECT i.title, i.description, i.location, i.latitude, i.longitude, c.name as category_name 
            FROM incidents i 
            JOIN categories c ON i.category_id = c.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$incidentId]);
        $incData = $stmt->fetch();

        $aiResult = AIService::analyze($incData['title'] ?? '', $incData['description'] ?? '');
        $originalSeverity = $severity;

        // Generate AI Summary
        $aiSummary = AIService::generateSummary(
            $incData['category_name'] ?? 'Incident',
            $incData['location']      ?? 'Unknown Location',
            $incData['description']   ?? ''
        );

        // Check for potential duplicates
        $duplicateOf = AIService::detectPotentialDuplicate(
            $this->pdo, 
            $incData['latitude'], 
            $incData['longitude'], 
            $categoryId, 
            $incidentId
        );

        if ($duplicateOf) {
            $this->pdo->prepare("UPDATE incidents SET is_duplicate = 1, duplicate_of = ? WHERE id = ?")
                      ->execute([$duplicateOf, $incidentId]);
            
            // Log Duplicate detection
            $this->pdo->prepare("
                INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
                VALUES (?, NULL, 'pending', 'pending', ?)
            ")->execute([$incidentId, "✨ AI Duplicate Filter: Flagged as potential duplicate of Incident #{$duplicateOf}."]);
        }

        // AI Oversight: If AI detects critical urgency but user set it lower, upgrade it.
        if ($aiResult['is_critical'] && $originalSeverity !== 'critical') {
            $severity = 'critical';
            $this->pdo->prepare("UPDATE incidents SET severity = ?, ai_summary = ? WHERE id = ?")
                      ->execute([$severity, $aiSummary, $incidentId]);
            
            // Log AI intervention
            $this->pdo->prepare("
                INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
                VALUES (?, NULL, 'pending', 'pending', ?)
            ")->execute([$incidentId, "✨ AI Auditor: Automatically upgraded severity to Critical based on emergency keyword detection."]);
        } else {
            // Just update the summary if no severity change
            $this->pdo->prepare("UPDATE incidents SET ai_summary = ? WHERE id = ?")
                      ->execute([$aiSummary, $incidentId]);
            
            // Log general AI completion for the timeline
            $this->pdo->prepare("
                INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
                VALUES (?, NULL, 'pending', 'pending', ?)
            ")->execute([$incidentId, "✨ AI: Analysis completed. summary: \"{$aiSummary}\""]);
        }

        // Generate Formal English Official Report
        $formalReport = AIService::generateFormalReport(
            $incData['category_name'] ?? 'Incident',
            $incData['location']      ?? 'Unknown Location',
            $incData['description']   ?? '',
            $severity
        );
        $this->pdo->prepare("UPDATE incidents SET ai_formal_report = ? WHERE id = ?")
                  ->execute([$formalReport, $incidentId]);

        $cat = $this->pdo->prepare("
            SELECT default_responder_id, sla_critical, sla_high, sla_medium, sla_low
            FROM categories WHERE id = ?
        ");
        $cat->execute([$categoryId]);
        $category = $cat->fetch();

        if (!$category) return;

        $slaMinutes = match($severity) {
            'critical' => (int)$category['sla_critical'],
            'high'     => (int)$category['sla_high'],
            'medium'   => (int)$category['sla_medium'],
            default    => (int)$category['sla_low'],
        };

        $priority = match($severity) {
            'critical' => 1,
            'high'     => 2,
            'medium'   => 3,
            default    => 4,
        };

        $slaDeadline      = date('Y-m-d H:i:s', time() + ($slaMinutes * 60));
        
        // ── SMART AI ASSIGNMENT ──────────────────────────
        $activeResponders = $this->pdo->query("SELECT id, name FROM users WHERE role = 'responder' AND is_active = 1")->fetchAll();
        $suggestedId = AIService::suggestResponder($incData['title'], $incData['category_name'], $activeResponders);
        
        $finalResponder = $suggestedId ?: $category['default_responder_id'];
        
        // If AI chose a valid responder from our active list, log it
        if ($suggestedId) {
            $this->pdo->prepare("
                INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
                VALUES (?, NULL, 'pending', 'pending', ?)
            ")->execute([$incidentId, "✨ AI Dispatcher: Smart-matched this case to " . ($this->getResponderName($suggestedId) ?: "Personnel #".$suggestedId)]);
        }

        $stmt = $this->pdo->prepare("
            UPDATE incidents
            SET sla_deadline = ?,
                priority     = ?,
                assigned_to  = COALESCE(?, assigned_to)
            WHERE id = ?
        ");
        $stmt->execute([$slaDeadline, $priority, $finalResponder, $incidentId]);

        // ── SMS NOTIFICATION SIMULATION ──────────────────
        if ($severity === 'critical' && $finalResponder) {
            $resp = $this->pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $resp->execute([$finalResponder]);
            $phone = $resp->fetchColumn();

            if ($phone) {
                require_once __DIR__ . '/../includes/SMSService.php';
                $fullInc = $this->getById($incidentId);
                SMSService::notifyCriticalIncident($fullInc, $phone);
                
                // Log SMS event
                $this->pdo->prepare("
                    INSERT INTO status_logs (incident_id, changed_by, old_status, new_status, remarks)
                    VALUES (?, NULL, 'pending', 'pending', ?)
                ")->execute([$incidentId, "📱 SMS Alert: Critical notification sent to " . ($fullInc['responder_name'] ?: "assigned personnel") . "."]);
            }
        }
    }

    private function getResponderName($id) {
        $st = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $st->execute([$id]);
        return $st->fetchColumn();
    }

    public function getBreachedUnescalated(): array {
        $stmt = $this->pdo->query("
            SELECT i.*,
                   c.name AS category_name,
                   COALESCE(u.name, i.anon_name, 'Anonymous') AS reporter_name,
                   a.name AS responder_name,
                   COALESCE(u.email, i.anon_email) AS reporter_email
            FROM incidents i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.reporter_id = u.id
            LEFT JOIN users a ON i.assigned_to = a.id
            WHERE i.sla_deadline < NOW()
              AND i.status NOT IN ('resolved','closed')
              AND i.escalated = 0
        ");
        return $stmt->fetchAll();
    }

    public function markEscalated(int $incidentId): void {
        $this->pdo->prepare("
            UPDATE incidents SET escalated = 1, sla_breached = 1 WHERE id = ?
        ")->execute([$incidentId]);
    }

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

    public function submitFeedback(int $incidentId, int $citizenId, int $rating, string $comment): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO feedback (incident_id, citizen_id, rating, comment)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
        ");
        return $stmt->execute([$incidentId, $citizenId, $rating, $comment]);
    }

    public function getResponderStats(int $responderId): array {
        $avgStmt = $this->pdo->prepare("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, reported_at, acknowledged_at)) AS avg_response
            FROM incidents
            WHERE assigned_to = ? AND acknowledged_at IS NOT NULL
        ");
        $avgStmt->execute([$responderId]);
        $avg = $avgStmt->fetch();

        $resolvedStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS count FROM incidents
            WHERE assigned_to = ? AND status IN ('resolved','closed')
        ");
        $resolvedStmt->execute([$responderId]);
        $resolved = $resolvedStmt->fetch();

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
                'status'       => 'breached',
                'label'        => 'SLA Breached!',
                'minutes_left' => 0,
                'percent'      => 100,
            ];
        }

        $percent  = round((1 - ($left / $total)) * 100);
        $minsLeft = round($left / 60);

        return [
            'status'       => $percent >= 75 ? 'warning' : 'ok',
            'label'        => $minsLeft < 60
                ? "{$minsLeft} mins left"
                : round($minsLeft / 60, 1) . ' hrs left',
            'minutes_left' => $minsLeft,
            'percent'      => $percent,
        ];
    }

    public function getTrendData(int $days = 30): array {
        $stmt = $this->pdo->prepare("
            SELECT DATE(reported_at) as date, COUNT(*) as count
            FROM incidents
            WHERE reported_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(reported_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function getGlobalKPIs(): array {
        // MTTA: Mean Time to Acknowledge (mins)
        $mtta = $this->pdo->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, reported_at, acknowledged_at)) as avg
            FROM incidents WHERE acknowledged_at IS NOT NULL
        ")->fetchColumn();

        // MTTR: Mean Time to Resolve (mins)
        $mttr = $this->pdo->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, reported_at, resolved_at)) as avg
            FROM incidents WHERE resolved_at IS NOT NULL
        ")->fetchColumn();

        // SLA Performance
        $sla = $this->pdo->query("
            SELECT 
                (COUNT(CASE WHEN sla_breached = 0 AND status IN ('resolved','closed') THEN 1 END) / 
                 NULLIF(COUNT(CASE WHEN status IN ('resolved','closed') THEN 1 END), 0)) * 100 as rate
            FROM incidents
        ")->fetchColumn();

        // CSAT Score (Average Rating)
        $csat = $this->pdo->query("SELECT AVG(rating) FROM feedback")->fetchColumn();

        return [
            'mtta' => round($mtta ?? 0),
            'mttr' => round(($mttr ?? 0) / 60, 1), // in hours
            'sla'  => round($sla ?? 0, 1),
            'csat' => round($csat ?? 0, 1),
        ];
    }

    public function getSeverityDist(): array {
        return $this->pdo->query("
            SELECT severity, COUNT(*) as count 
            FROM incidents 
            GROUP BY severity
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getFullTimeline(int $incidentId): array {
        $timeline = [];

        // 1. Initial Submission
        $incStmt = $this->pdo->prepare("
            SELECT reported_at as event_date, title as event_title, location as event_location, 
                   COALESCE(u.name, i.anon_name, 'Anonymous') as actor_name,
                   'reported' as type
            FROM incidents i
            LEFT JOIN users u ON i.reporter_id = u.id
            WHERE i.id = ?
        ");
        $incStmt->execute([$incidentId]);
        if ($res = $incStmt->fetch()) {
            $timeline[] = [
                'date'    => $res['event_date'],
                'type'    => 'submission',
                'title'   => 'Incident Reported',
                'actor'   => $res['actor_name'],
                'content' => "Status: Pending. Location: {$res['event_location']}",
                'icon'    => 'bi-file-earmark-text',
                'color'   => '#64748b'
            ];
        }

        // 2. Status Logs
        $logStmt = $this->pdo->prepare("
            SELECT sl.*, u.name as actor_name
            FROM status_logs sl
            LEFT JOIN users u ON sl.changed_by = u.id
            WHERE sl.incident_id = ?
            ORDER BY sl.changed_at ASC
        ");
        $logStmt->execute([$incidentId]);
        while ($l = $logStmt->fetch()) {
            $type = 'status';
            $icon = 'bi-arrow-repeat';
            $color = '#0d6efd';
            $title = "Status changed to " . ucwords(str_replace('_', ' ', $l['new_status']));
            
            if ($l['new_status'] === 'resolved') { $icon = 'bi-check-circle-fill'; $color = '#10b981'; }
            if ($l['new_status'] === 'closed')   { $icon = 'bi-lock-fill'; $color = '#64748b'; }
            
            // Special Case for AI
            if (str_contains($l['remarks'] ?? '', 'AI Auditor') || 
                str_contains($l['remarks'] ?? '', 'AI:') || 
                str_contains($l['remarks'] ?? '', 'AI Duplicate')) {
                $type = 'ai';
                $icon = 'bi-stars';
                $color = '#6366f1';
                $title = "AI Analysis Completed";
            }

            $timeline[] = [
                'date'    => $l['changed_at'],
                'type'    => $type,
                'title'   => $title,
                'actor'   => $l['actor_name'] ?? 'QC-ALERTO AI',
                'content' => $l['remarks'],
                'icon'    => $icon,
                'color'   => $color
            ];
        }

        // 3. Responses
        $respStmt = $this->pdo->prepare("
            SELECT r.*, u.name as actor_name
            FROM responses r
            JOIN users u ON r.responder_id = u.id
            WHERE r.incident_id = ?
            ORDER BY r.responded_at ASC
        ");
        $respStmt->execute([$incidentId]);
        while ($r = $respStmt->fetch()) {
            $timeline[] = [
                'date'    => $r['responded_at'],
                'type'    => 'response',
                'title'   => 'Update Provided',
                'actor'   => $r['actor_name'],
                'content' => $r['message'],
                'icon'    => 'bi-chat-left-text',
                'color'   => '#0369a1'
            ];
        }

        // 4. Feedback
        $feedStmt = $this->pdo->prepare("
            SELECT f.*, u.name as actor_name
            FROM feedback f
            JOIN users u ON f.citizen_id = u.id
            WHERE f.incident_id = ?
        ");
        $feedStmt->execute([$incidentId]);
        if ($f = $feedStmt->fetch()) {
            $timeline[] = [
                'date'    => $f['created_at'],
                'type'    => 'feedback',
                'title'   => 'Citizen Feedback Received',
                'actor'   => $f['actor_name'],
                'content' => "Rating: {$f['rating']}/5 Stars. \"{$f['comment']}\"",
                'icon'    => 'bi-star-fill',
                'color'   => '#f59e0b'
            ];
        }

        // Sort by date ASC
        usort($timeline, function($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return $timeline;
    }
}