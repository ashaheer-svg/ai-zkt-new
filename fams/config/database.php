<?php
/**
 * Database Configuration & Initialization
 * 
 * Manages SQLite connection, schema creation, migrations, and seeding.
 * Uses Write-Ahead Logging (WAL) for better concurrency.
 */

/**
 * Initializes and returns the PDO database connection.
 * Automatically handles schema creation and migrations on first connect.
 * 
 * @return PDO
 */
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
    
    // SQLite optimizations
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    _createSchema($pdo);
    _migrate($pdo);
    _seedAdmin($pdo);
    
    return $pdo;
}

/**
 * Incremental Migrations
 * Handles updates to the database schema without losing data.
 * 
 * @param PDO $pdo
 */
function _migrate(PDO $pdo): void
{
    // Villages table updates
    $cols = $pdo->query("PRAGMA table_info(villages)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('allocation_amount', $cols)) {
        $pdo->exec("ALTER TABLE villages ADD COLUMN allocation_amount REAL DEFAULT 0");
    }

    // Application documents updates
    $colsDoc = $pdo->query("PRAGMA table_info(application_documents)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('doc_type', $colsDoc)) {
        $pdo->exec("ALTER TABLE application_documents ADD COLUMN doc_type TEXT");
    }
    if (!in_array('doc_language', $colsDoc)) {
        $pdo->exec("ALTER TABLE application_documents ADD COLUMN doc_language TEXT");
    }

    // Rename applicant_children to applicant_dependants for inclusivity
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('applicant_children', $tables) && !in_array('applicant_dependants', $tables)) {
        $pdo->exec("ALTER TABLE applicant_children RENAME TO applicant_dependants");
    }

    // Dependants table updates
    if (in_array('applicant_dependants', $tables)) {
        $cols = $pdo->query("PRAGMA table_info(applicant_dependants)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('relationship', $cols)) {
            $pdo->exec("ALTER TABLE applicant_dependants ADD COLUMN relationship TEXT");
        }
    }

    // Applicant details updates
    $colsA = $pdo->query("PRAGMA table_info(applicants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('marital_status', $colsA)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN marital_status TEXT");
    }

    // Application workflow & scheduling updates
    $colsB = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('requested_type', $colsB)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN requested_type TEXT");
    }
    if (!in_array('requested_installment', $colsB)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN requested_installment REAL");
    }
    if (!in_array('requested_count', $colsB)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN requested_count INTEGER");
    }
    if (!in_array('previous_status', $colsB)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN previous_status TEXT");
    }

    // User wallet/balance updates
    $colsU = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('balance', $colsU)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN balance REAL DEFAULT 0");
    }

    // Cash transfers tracking
    $pdo->exec("CREATE TABLE IF NOT EXISTS cash_transfers (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        from_user_id  INTEGER NOT NULL,
        to_user_id    INTEGER NOT NULL,
        amount        REAL NOT NULL,
        reference     TEXT,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_user_id) REFERENCES users(id),
        FOREIGN KEY (to_user_id)   REFERENCES users(id)
    )");

    // Disbursement tracking updates
    $colsD = $pdo->query("PRAGMA table_info(disbursements)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('assigned_to', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN assigned_to INTEGER");
    }
    if (!in_array('payment_method', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN payment_method TEXT");
    }
    if (!in_array('payment_date', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN payment_date DATE");
    }
    if (!in_array('payment_reference', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN payment_reference TEXT");
    }
    if (!in_array('paid_at', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN paid_at DATETIME");
    }
    if (!in_array('paid_by', $colsD)) {
        $pdo->exec("ALTER TABLE disbursements ADD COLUMN paid_by INTEGER");
    }

    // --- 2026 Form Expansion Fields ---
    
    // Additional applicant demographics
    $colsA = $pdo->query("PRAGMA table_info(applicants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('telephone_home', $colsA)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN telephone_home TEXT");
    }
    if (!in_array('residency_status', $colsA)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN residency_status TEXT");
    }
    if (!in_array('occupation', $colsA)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN occupation TEXT");
    }
    if (!in_array('employer_details', $colsA)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN employer_details TEXT");
    }

    // Additional application details
    $colsApp = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('reason_for_application', $colsApp)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN reason_for_application TEXT");
    }
    if (!in_array('applied_other_funds', $colsApp)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN applied_other_funds TEXT");
    }
    if (!in_array('expected_date', $colsApp)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN expected_date DATE");
    }

    // Additional dependant details
    $colsDep = $pdo->query("PRAGMA table_info(applicant_dependants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('occupation', $colsDep)) {
        $pdo->exec("ALTER TABLE applicant_dependants ADD COLUMN occupation TEXT");
    }
    if (!in_array('income', $colsDep)) {
        $pdo->exec("ALTER TABLE applicant_dependants ADD COLUMN income REAL DEFAULT 0");
    }

    // --- Trilingual Support ---
    // Track which language was used during data entry (ta=Tamil, si=Sinhala, en=English)
    $colsApp2 = $pdo->query("PRAGMA table_info(applications)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('input_language', $colsApp2)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN input_language TEXT DEFAULT 'en'");
    }
    $colsApplicant2 = $pdo->query("PRAGMA table_info(applicants)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('input_language', $colsApplicant2)) {
        $pdo->exec("ALTER TABLE applicants ADD COLUMN input_language TEXT DEFAULT 'en'");
    }
}

/**
 * Base Schema Creation
 * 
 * @param PDO $pdo
 */
function _createSchema(PDO $pdo): void
{
    $pdo->exec("
        -- Core Users & RBAC
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT    UNIQUE NOT NULL,
            password_hash TEXT    NOT NULL,
            full_name     TEXT    NOT NULL,
            role          TEXT    NOT NULL,
            balance       REAL    DEFAULT 0,
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Geographic Management
        CREATE TABLE IF NOT EXISTS villages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            district   TEXT,
            allocation_amount REAL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Many-to-Many Assignment for RBAC scoping
        CREATE TABLE IF NOT EXISTS user_villages (
            user_id    INTEGER NOT NULL,
            village_id INTEGER NOT NULL,
            PRIMARY KEY (user_id, village_id),
            FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
            FOREIGN KEY (village_id) REFERENCES villages(id) ON DELETE CASCADE
        );

        -- Financial Categories
        CREATE TABLE IF NOT EXISTS fund_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            description TEXT,
            is_active   INTEGER NOT NULL DEFAULT 1,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Main Entity: The Applicant
        CREATE TABLE IF NOT EXISTS applicants (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name        TEXT    NOT NULL,
            address          TEXT,
            gender           TEXT    NOT NULL,
            age              INTEGER,
            id_number        TEXT,
            telephone        TEXT,
            telephone_home   TEXT,
            village_id       INTEGER NOT NULL,
            marital_status   TEXT,
            residency_status TEXT,
            occupation       TEXT,
            employer_details TEXT,
            notes            TEXT,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (village_id) REFERENCES villages(id)
        );

        -- Applicant Spouse (1-to-1)
        CREATE TABLE IF NOT EXISTS applicant_spouse (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL UNIQUE,
            full_name    TEXT    NOT NULL,
            age          INTEGER,
            id_number    TEXT,
            telephone    TEXT,
            FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
        );

        -- Applicant Dependants (1-to-Many)
        CREATE TABLE IF NOT EXISTS applicant_dependants (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            applicant_id INTEGER NOT NULL,
            full_name    TEXT    NOT NULL,
            age          INTEGER,
            gender       TEXT,
            relationship TEXT,
            occupation   TEXT,
            income       REAL DEFAULT 0,
            FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
        );

        -- The Application Instance
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
            reason_for_application TEXT,
            applied_other_funds  TEXT,
            expected_date        DATE,
            disbursement_type    TEXT,
            disbursement_amount  REAL,
            disbursement_count   INTEGER,
            disbursement_start_date DATE,
            requested_type       TEXT,
            requested_installment REAL,
            requested_count      INTEGER,
            previous_status      TEXT,
            created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (applicant_id)     REFERENCES applicants(id),
            FOREIGN KEY (fund_category_id) REFERENCES fund_categories(id),
            FOREIGN KEY (created_by)       REFERENCES users(id),
            FOREIGN KEY (validated_by)     REFERENCES users(id),
            FOREIGN KEY (reviewed_by)      REFERENCES users(id),
            FOREIGN KEY (approved_by)      REFERENCES users(id)
        );

        -- Attached Files
        CREATE TABLE IF NOT EXISTS application_documents (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id    INTEGER NOT NULL,
            uploaded_by       INTEGER NOT NULL,
            original_filename TEXT    NOT NULL,
            stored_filename   TEXT    NOT NULL,
            mime_type         TEXT,
            file_size         INTEGER,
            description       TEXT,
            doc_type          TEXT,
            doc_language      TEXT,
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by)    REFERENCES users(id)
        );

        -- Mobile API Security
        CREATE TABLE IF NOT EXISTS api_tokens (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL,
            token        TEXT UNIQUE NOT NULL,
            expires_at   DATETIME NOT NULL,
            last_used_at DATETIME,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Auditing: Field-level changes
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

        -- Auditing: Lifecycle logs
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

        -- Financial: Payment installments
        CREATE TABLE IF NOT EXISTS disbursements (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            application_id   INTEGER NOT NULL,
            installment_no   INTEGER NOT NULL,
            due_date         DATE,
            amount           REAL    NOT NULL,
            status           TEXT    NOT NULL DEFAULT 'pending',
            authorized_by    INTEGER,
            authorized_at    DATETIME,
            assigned_to      INTEGER,
            payment_method   TEXT,
            payment_date     DATE,
            payment_reference TEXT,
            paid_at          DATETIME,
            paid_by          INTEGER,
            notes            TEXT,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (authorized_by)  REFERENCES users(id),
            FOREIGN KEY (assigned_to)    REFERENCES users(id),
            FOREIGN KEY (paid_by)        REFERENCES users(id)
        );

        -- System-wide activity
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

        -- Key-Value Store for app settings
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        );

        -- Meta-data for document grouping
        CREATE TABLE IF NOT EXISTS document_types (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL UNIQUE,
            is_active  INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Universal Translation Cache
        -- Stores English translations keyed by (table, record_id, field_name).
        -- Populated on first translate request; subsequent reads are instant (DB only).
        CREATE TABLE IF NOT EXISTS translation_cache (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            table_name      TEXT NOT NULL,
            record_id       INTEGER NOT NULL,
            field_name      TEXT NOT NULL,
            source_lang     TEXT NOT NULL DEFAULT 'auto',
            translated_text TEXT NOT NULL,
            translated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (table_name, record_id, field_name)
        );
    ");
}

/**
 * Seeding Default Settings
 */
function _seedSettings(PDO $pdo): void
{
    $defaults = [
        'debug_mode' => '0',
        'timezone'   => 'Asia/Colombo'
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }
}

/**
 * Seeding Administrative Data
 */
function _seedAdmin(PDO $pdo): void
{
    _seedSettings($pdo);
    
    // Default Admin User
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $hash, 'System Administrator', 'sysadmin']);
    }

    // Default Document Types
    $countTypes = (int)$pdo->query('SELECT COUNT(*) FROM document_types')->fetchColumn();
    if ($countTypes === 0) {
        $types = ['Application Form', 'Photo', 'ID Copy', 'Address Proof', 'Other'];
        $stmt = $pdo->prepare("INSERT INTO document_types (name) VALUES (?)");
        foreach ($types as $t) { $stmt->execute([$t]); }
    }
}

/**
 * Utility: Wipes all system tables (used for Factory Reset)
 */
function dropAllTables(PDO $pdo): void
{
    $tables = [
        'disbursements', 'application_logs', 'application_edits', 
        'applications', 'applicants', 'user_villages', 'villages', 
        'fund_categories', 'activity_log', 'settings', 'users', 'cash_transfers',
        'api_tokens', 'document_types', 'applicant_spouse', 'applicant_dependants', 'application_documents'
    ];
    $pdo->exec('PRAGMA foreign_keys = OFF');
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
}
