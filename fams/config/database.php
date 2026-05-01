<?php
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbPath = __DIR__ . '/../database/fams.sqlite';
    $dbDir  = dirname($dbPath);
    if (!is_dir($dbDir)) mkdir($dbDir, 0755, true);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    _createSchema($pdo);
    _migrate($pdo);
    _seedAdmin($pdo);
    return $pdo;
}

function _migrate(PDO $pdo): void
{
    // Add allocation_amount to villages if missing
    $cols = $pdo->query("PRAGMA table_info(villages)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('allocation_amount', $cols)) {
        $pdo->exec("ALTER TABLE villages ADD COLUMN allocation_amount REAL DEFAULT 0");
    }

    // Rename applicant_children to applicant_dependants
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('applicant_children', $tables) && !in_array('applicant_dependants', $tables)) {
        $pdo->exec("ALTER TABLE applicant_children RENAME TO applicant_dependants");
    }

    // Add relationship to applicant_dependants
    if (in_array('applicant_dependants', $tables)) {
        $cols = $pdo->query("PRAGMA table_info(applicant_dependants)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('relationship', $cols)) {
            $pdo->exec("ALTER TABLE applicant_dependants ADD COLUMN relationship TEXT");
        }
    }

    // Add marital_status to applicants
    $cols = $pdo->query("PRAGMA table_info(applicants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('marital_status', $cols)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN marital_status TEXT");
    }
}

function _createSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    UNIQUE NOT NULL,
            password_hash TEXT    NOT NULL,
            full_name     TEXT    NOT NULL,
            role          TEXT    NOT NULL,
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS villages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            district   TEXT,
            allocation_amount REAL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS user_villages (
            user_id    INTEGER NOT NULL,
            village_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, village_id),
            FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
            FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS fund_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            description TEXT,
            is_active   INTEGER NOT NULL DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS applicants (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name  TEXT    NOT NULL,
            address    TEXT,
            gender     TEXT    NOT NULL,
            age        INTEGER,
            id_number  TEXT,
            telephone  TEXT,
            village_id INTEGER NOT NULL,
            marital_status TEXT,
            notes      TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (village_id) REFERENCES villages(id)
        );

        CREATE TABLE IF NOT EXISTS applicant_spouse (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL UNIQUE,
            full_name    TEXT    NOT NULL,
            age          INTEGER,
            id_number    TEXT,
            telephone    TEXT,
            FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS applicant_dependants (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL,
            full_name    TEXT    NOT NULL,
            age          INTEGER,
            gender       TEXT,
            relationship TEXT,
            FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS applications (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id         INTEGER NOT NULL,
            fund_category_id     INTEGER NOT NULL,
            amount_requested     REAL    NOT NULL,
            status               TEXT    NOT NULL DEFAULT 'draft',
            is_valid             INTEGER NOT NULL DEFAULT 0,
            is_privileged        INTEGER NOT NULL DEFAULT 0,
            created_by           INTEGER NOT NULL,
            validated_by         INTEGER,
            validated_at         DATETIME,
            reviewed_by          INTEGER,
            approved_by          INTEGER,
            disbursement_type    TEXT,
            disbursement_amount  REAL,
            disbursement_count   INTEGER,
            disbursement_start_date DATE,
            created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (applicant_id)     REFERENCES applicants(id),
            FOREIGN KEY (fund_category_id) REFERENCES fund_categories(id),
            FOREIGN KEY (created_by)       REFERENCES users(id),
            FOREIGN KEY (validated_by)     REFERENCES users(id),
            FOREIGN KEY (reviewed_by)      REFERENCES users(id),
            FOREIGN KEY (approved_by)      REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS application_documents (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id    INTEGER NOT NULL,
            uploaded_by       INTEGER NOT NULL,
            original_filename TEXT    NOT NULL,
            stored_filename   TEXT    NOT NULL,
            mime_type         TEXT,
            file_size         INTEGER,
            description       TEXT,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by)    REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS application_edits (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id INTEGER NOT NULL,
            edited_by      INTEGER NOT NULL,
            field_name     TEXT    NOT NULL,
            old_value      TEXT,
            new_value      TEXT,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (edited_by)      REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS application_logs (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id INTEGER NOT NULL,
            user_id        INTEGER NOT NULL,
            action         TEXT    NOT NULL,
            comment        TEXT,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)        REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS disbursements (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id   INTEGER NOT NULL,
            installment_no   INTEGER NOT NULL,
            due_date         DATE,
            amount           REAL    NOT NULL,
            status           TEXT    NOT NULL DEFAULT 'pending',
            authorized_by    INTEGER,
            authorized_at    DATETIME,
            notes            TEXT,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (authorized_by)  REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS activity_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER,
            action      TEXT NOT NULL,
            entity_type TEXT,
            entity_id   INTEGER,
            ip_address  TEXT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
}

function _seedAdmin(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hash, 'System Administrator', 'sysadmin']);
    }
}
