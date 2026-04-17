<?php
require_once __DIR__ . '/../config/db.php';

class User {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Find a user by email. Returns the user row regardless of email_verified
     * status so that the caller (AuthController) can give a specific message.
     * Still requires is_active = 1 — deactivated users cannot login.
     */
    public function findByEmail(string $email): array|false {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users
            WHERE email = ? AND is_active = 1
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function emailExists(string $email): bool {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    /**
     * Create a new citizen account in unverified state.
     * Returns the new user's ID on success, false on failure.
     *
     * @param array $data Must include: name, email, password, verify_token,
     *                    verify_token_expires. Optional: phone, address.
     * @return int|false
     */
    public function create(array $data): int|false {
        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (name, email, password, phone, address, role,
                 email_verified, verify_token, verify_token_expires)
            VALUES
                (:name, :email, :password, :phone, :address, 'citizen',
                 0, :token, :expires)
        ");

        $result = $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':password'=> password_hash($data['password'], PASSWORD_BCRYPT),
            ':phone'   => $data['phone']   ?? null,
            ':address' => $data['address'] ?? null,
            ':token'   => $data['verify_token'],
            ':expires' => $data['verify_token_expires'],
        ]);

        return $result ? (int) $this->pdo->lastInsertId() : false;
    }

    /**
     * Verify an account using a token from a verification email link.
     *
     * @return string 'verified' | 'expired' | 'invalid'
     */
    public function verifyByToken(string $token): string {
        // Find user by token — include expired ones so we can distinguish
        $stmt = $this->pdo->prepare("
            SELECT id, verify_token_expires, email_verified
            FROM users
            WHERE verify_token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            return 'invalid'; // Token not found or already cleared
        }

        if ($user['email_verified']) {
            return 'invalid'; // Already verified — treat as invalid link
        }

        if ($user['verify_token_expires'] === null ||
            strtotime($user['verify_token_expires']) < time()) {
            return 'expired'; // Token found but expired
        }

        // ── Mark as verified, clear token ─────────────────
        $this->pdo->prepare("
            UPDATE users
            SET email_verified        = 1,
                verify_token          = NULL,
                verify_token_expires  = NULL
            WHERE id = ?
        ")->execute([$user['id']]);

        return 'verified';
    }
    public function update(int $userId, array $fields): bool {
        $set = [];
        $params = [':id' => $userId];

        foreach ($fields as $key => $val) {
            $set[] = "{$key} = :{$key}";
            $params[":{$key}"] = $val;
        }

        $sql = "UPDATE users SET " . implode(', ', $set) . " WHERE id = :id";
        return $this->pdo->prepare($sql)->execute($params);
    }
    public function getAll(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = "u.role = :role";
            $params[':role'] = $filters['role'];
        }

        if (!empty($filters['agency'])) {
            $where[] = "u.agency = :agency";
            $params[':agency'] = $filters['agency'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }

        $sql = "
            SELECT u.*, 
                   (SELECT COUNT(*) FROM incidents WHERE reporter_id = u.id) AS report_count
            FROM users u
        ";

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}