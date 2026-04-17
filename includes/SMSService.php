<?php
/**
 * Mock SMS Service for IRMS Simulation
 * In a real environment, this would integrate with Twilio, Semaphore, or Chikka.
 */
class SMSService {
    
    public static function send(string $to, string $message): bool {
        require_once __DIR__ . '/../config/db.php';
        global $pdo;

        // Simulate network delay
        // usleep(500000); 

        // For simulation, we log the SMS to a 'mock_sms_outbox' table
        try {
            // Check if table exists, if not create it (auto-migration style)
            $pdo->exec("CREATE TABLE IF NOT EXISTS mock_sms_outbox (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone_number VARCHAR(20),
                message TEXT,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'delivered'
            )");

            $stmt = $pdo->prepare("INSERT INTO mock_sms_outbox (phone_number, message) VALUES (?, ?)");
            return $stmt->execute([$to, $message]);

        } catch (Exception $e) {
            error_log("SMS Simulation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Specialized alert for Critical incidents
     */
    public static function notifyCriticalIncident(array $incident, string $responderPhone): void {
        $msg = "[QC-ALERTO CRITICAL] Incident #{$incident['tracking_number']}: {$incident['title']} reported at {$incident['location']}. Please respond immediately.";
        self::send($responderPhone, $msg);
    }
}
