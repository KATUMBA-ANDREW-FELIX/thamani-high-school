<?php
// THAMANI HIGH SCHOOL - Database Connection Engine & Multi-Driver Polyfill (PostgreSQL & SQLite)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$host = getenv('DB_HOST') ?: "localhost";
$user = getenv('DB_USERNAME') ?: "root";
$pass = getenv('DB_PASSWORD') ?: "";
$dbname = getenv('DB_DATABASE') ?: "themani";

// Global connection object
global $conn;
$conn = null;

// Polyfill mysqli using PDO (PostgreSQL / SQLite) so application runs seamlessly across environments
if (!class_exists('ThamaniPolyfillResult')) {
    class ThamaniPolyfillResult {
        public $rows = [];
        public $currentIndex = 0;
        public $num_rows = 0;

        public function fetch_assoc() {
            if ($this->currentIndex < count($this->rows)) {
                return $this->rows[$this->currentIndex++];
            }
            return null;
        }

        public function fetch_row() {
            if ($this->currentIndex < count($this->rows)) {
                return array_values($this->rows[$this->currentIndex++]);
            }
            return null;
        }

        public function fetch_array() {
            if ($this->currentIndex < count($this->rows)) {
                $row = $this->rows[$this->currentIndex++];
                return array_merge(array_values($row), $row);
            }
            return null;
        }
    }
}

if (!class_exists('ThamaniPolyfillStmt')) {
    class ThamaniPolyfillStmt {
        public $pdo;
        public $driver;
        public $sql;
        public $params = [];
        public $resultRows = [];
        public $affectedRows = 0;
        public $lastInsertId = 0;
        public $errorMsg = '';

        public function __construct($pdo, $sql, $driver = 'sqlite') {
            $this->pdo = $pdo;
            $this->driver = $driver;
            if ($driver === 'pgsql') {
                $this->sql = str_ireplace("datetime('now')", "CURRENT_TIMESTAMP", $sql);
                $this->sql = str_ireplace("NOW()", "CURRENT_TIMESTAMP", $this->sql);
            } else {
                $this->sql = str_ireplace("NOW()", "datetime('now')", $sql);
            }
        }

        public function bind_param($types, ...$vars) {
            $this->params = $vars;
            return true;
        }

        public function execute() {
            try {
                $stmt = $this->pdo->prepare($this->sql);
                $res = $stmt->execute($this->params);
                if ($res) {
                    $trimmed = strtoupper(trim($this->sql));
                    if (str_starts_with($trimmed, 'SELECT')) {
                        $this->resultRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $this->affectedRows = $stmt->rowCount();
                        try {
                            $this->lastInsertId = (int)$this->pdo->lastInsertId();
                        } catch (Exception $e) {
                            $this->lastInsertId = 0;
                        }
                    }
                    return true;
                }
            } catch (Exception $e) {
                $this->errorMsg = $e->getMessage();
                error_log("[ThamaniPolyfillStmt Execute Error] " . $e->getMessage() . " | SQL: " . $this->sql);
            }
            return false;
        }

        public function get_result() {
            $res = new ThamaniPolyfillResult();
            $res->rows = $this->resultRows;
            $res->num_rows = count($this->resultRows);
            return $res;
        }

        public function store_result() {
            return true;
        }

        public function num_rows() {
            return count($this->resultRows);
        }

        public function close() {
            return true;
        }
    }
}

if (!class_exists('ThamaniPolyfillConn')) {
    class ThamaniPolyfillConn {
        public $pdo;
        public $driver = 'sqlite';
        public $insert_id = 0;
        public $error = '';

        public function __construct() {
            $pgHost = getenv('POSTGRES_HOST') ?: (getenv('DB_HOST') ?: '');
            $pgPort = getenv('POSTGRES_PORT') ?: (getenv('DB_PORT') ?: '5432');
            $pgDb   = getenv('POSTGRES_DB')   ?: (getenv('DB_DATABASE') ?: 'thamani_postgress');
            $pgUser = getenv('POSTGRES_USER') ?: (getenv('DB_USERNAME') ?: 'postgres');
            $pgPass = getenv('POSTGRES_PASSWORD') ?: (getenv('DB_PASSWORD') ?: '');

            $connectedPg = false;
            if (extension_loaded('pdo_pgsql') && ($pgHost !== '' || getenv('POSTGRES_DB') || getenv('DB_CONNECTION') === 'pgsql')) {
                try {
                    $dsn = "pgsql:host={$pgHost};port={$pgPort};dbname={$pgDb}";
                    $this->pdo = new PDO($dsn, $pgUser, $pgPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    $this->driver = 'pgsql';
                    $connectedPg = true;
                } catch (Exception $e) {
                    error_log("[ThamaniPolyfillConn] PostgreSQL connection attempt failed: " . $e->getMessage());
                }
            }

            if (!$connectedPg) {
                $dbPath = __DIR__ . '/thamani_database.sqlite';
                $this->pdo = new PDO('sqlite:' . $dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->driver = 'sqlite';
            }

            $this->initTables();
        }

        private function initTables() {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            try {
                $chk = $this->pdo->query("SELECT 1 FROM admins LIMIT 1");
            } catch (Exception $e) {}

            if ($this->driver === 'pgsql') {
                $schemaFile = __DIR__ . '/schema_pg.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    try {
                        $this->pdo->exec($sql);
                    } catch (Exception $e) {
                        error_log("[ThamaniPolyfillConn] Schema init warning: " . $e->getMessage());
                    }
                }
            } else {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS students (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        full_name TEXT,
                        date_of_birth TEXT,
                        gender TEXT,
                        nationality TEXT,
                        lin_number TEXT UNIQUE,
                        previous_school TEXT,
                        class_level TEXT,
                        stream TEXT,
                        guardian_name TEXT,
                        guardian_relationship TEXT,
                        guardian_phone TEXT,
                        guardian_email TEXT,
                        guardian_address TEXT,
                        guardian_occupation TEXT,
                        emergency_name TEXT,
                        emergency_phone TEXT,
                        medical_notes TEXT,
                        academic_doc_path TEXT,
                        recommendation_doc_path TEXT,
                        medical_doc_path TEXT,
                        status TEXT DEFAULT 'Pending',
                        password_hash TEXT,
                        must_change_password INTEGER DEFAULT 1,
                        account_active INTEGER DEFAULT 1,
                        last_login DATETIME,
                        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS teachers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        staff_id TEXT UNIQUE,
                        full_name TEXT,
                        email TEXT UNIQUE,
                        department TEXT,
                        is_class_teacher INTEGER DEFAULT 0,
                        class_teacher_of TEXT,
                        class_teacher_stream TEXT DEFAULT 'Stream A',
                        classes_taught TEXT,
                        can_view_enrollments INTEGER DEFAULT 0,
                        can_manage_duty_roster INTEGER DEFAULT 0,
                        password_hash TEXT,
                        must_change_password INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1,
                        last_login DATETIME,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS student_attendance (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        student_id INTEGER NOT NULL,
                        class_level TEXT NOT NULL,
                        attendance_date DATE NOT NULL,
                        status TEXT NOT NULL,
                        recorded_by_teacher_id INTEGER,
                        remarks TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS student_marks (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        student_id INTEGER NOT NULL,
                        class_level TEXT NOT NULL,
                        subject TEXT NOT NULL,
                        term TEXT NOT NULL,
                        score REAL NOT NULL,
                        max_score REAL DEFAULT 100,
                        comments TEXT,
                        recorded_by_teacher_id INTEGER,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS class_announcements (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT,
                        content TEXT,
                        class_level TEXT,
                        posted_by_teacher_id INTEGER,
                        posted_by_name TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS admins (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        admin_id TEXT UNIQUE,
                        full_name TEXT,
                        email TEXT UNIQUE,
                        password_hash TEXT,
                        must_change_password INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1,
                        last_login DATETIME,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS alumni (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT,
                        year TEXT,
                        profession TEXT,
                        phone TEXT,
                        email TEXT
                    );

                    CREATE TABLE IF NOT EXISTS calendar_documents (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT,
                        doc_type TEXT,
                        description TEXT,
                        file_name TEXT,
                        stored_name TEXT,
                        file_path TEXT,
                        file_size INTEGER,
                        mime_type TEXT,
                        uploaded_by INTEGER,
                        is_active INTEGER DEFAULT 1,
                        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS gallery_photos (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT,
                        caption TEXT,
                        category TEXT,
                        file_name TEXT,
                        stored_name TEXT,
                        file_path TEXT,
                        file_size INTEGER,
                        mime_type TEXT,
                        uploaded_by INTEGER,
                        is_active INTEGER DEFAULT 1,
                        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS library_resources (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT,
                        author TEXT,
                        subject TEXT,
                        category TEXT,
                        class_level TEXT,
                        description TEXT,
                        file_name TEXT,
                        stored_name TEXT,
                        file_path TEXT,
                        file_size INTEGER,
                        mime_type TEXT,
                        uploaded_by INTEGER,
                        is_active INTEGER DEFAULT 1,
                        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS class_timetables (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT,
                        class_level TEXT,
                        stream TEXT DEFAULT 'All Streams',
                        schedule_json TEXT,
                        file_name TEXT,
                        file_path TEXT,
                        file_size INTEGER,
                        uploaded_by INTEGER,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS teacher_duty_rosters (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        week_title TEXT,
                        senior_duty_teacher TEXT,
                        assistant_duty_teacher TEXT,
                        primary_focus_area TEXT,
                        notes TEXT,
                        created_by INTEGER,
                        created_by_role TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS teacher_personal_schedules (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        teacher_id INTEGER NOT NULL,
                        day_of_week TEXT NOT NULL,
                        start_time TEXT NOT NULL,
                        end_time TEXT NOT NULL,
                        subject TEXT NOT NULL,
                        class_level TEXT NOT NULL,
                        stream TEXT DEFAULT 'All Streams',
                        room_no TEXT,
                        notes TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS reporting_windows (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        academic_year TEXT NOT NULL,
                        term TEXT NOT NULL,
                        assessment_type TEXT DEFAULT 'EOT',
                        is_open INTEGER DEFAULT 1,
                        is_published INTEGER DEFAULT 0,
                        show_positions INTEGER DEFAULT 1,
                        show_points INTEGER DEFAULT 1,
                        disabled_points_levels TEXT DEFAULT '',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS teacher_subject_assignments (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        teacher_id INTEGER NOT NULL,
                        subject TEXT NOT NULL,
                        class_level TEXT NOT NULL,
                        stream TEXT DEFAULT 'All Streams',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS grading_scales (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        scale_name TEXT DEFAULT 'O-Level Standard',
                        min_score REAL NOT NULL,
                        max_score REAL NOT NULL,
                        grade TEXT NOT NULL,
                        points INTEGER DEFAULT 1,
                        remark TEXT,
                        education_level TEXT DEFAULT 'O-Level'
                    );

                    CREATE TABLE IF NOT EXISTS report_comments (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        student_id INTEGER NOT NULL,
                        window_id INTEGER NOT NULL,
                        class_teacher_comment TEXT,
                        head_teacher_comment TEXT,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(student_id, window_id)
                    );

                    CREATE TABLE IF NOT EXISTS school_streams (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        stream_name TEXT UNIQUE NOT NULL,
                        stream_code TEXT,
                        description TEXT,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS bank_integrations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        bank_code TEXT UNIQUE NOT NULL,
                        bank_name TEXT NOT NULL,
                        account_number TEXT,
                        api_endpoint TEXT,
                        api_key TEXT,
                        secret_key TEXT,
                        webhook_url TEXT,
                        webhook_secret TEXT,
                        environment TEXT DEFAULT 'sandbox',
                        is_active INTEGER DEFAULT 1,
                        last_sync_at DATETIME,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS student_fees (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        student_id INTEGER UNIQUE NOT NULL,
                        class_level TEXT,
                        term TEXT DEFAULT 'Term III 2026',
                        total_fee REAL DEFAULT 0,
                        paid_amount REAL DEFAULT 0,
                        balance REAL DEFAULT 0,
                        status TEXT DEFAULT 'UNPAID',
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS bank_transactions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        transaction_ref TEXT UNIQUE NOT NULL,
                        student_id INTEGER,
                        lin_number TEXT,
                        student_name TEXT,
                        bank_code TEXT NOT NULL,
                        payment_channel TEXT,
                        amount REAL NOT NULL,
                        payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                        receipt_number TEXT,
                        raw_payload TEXT,
                        status TEXT DEFAULT 'SUCCESS',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS school_settings (
                        setting_key TEXT UNIQUE NOT NULL,
                        setting_value TEXT,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS class_fee_rates (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        class_level TEXT UNIQUE NOT NULL,
                        fee_amount REAL NOT NULL DEFAULT 850000,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");

            try {
                $stCheck = $this->pdo->query("SELECT COUNT(*) as cnt FROM school_streams");
                $stRow = $stCheck ? $stCheck->fetch(PDO::FETCH_ASSOC) : null;
                if (empty($stRow['cnt'])) {
                    $defaultStreams = [
                        ['name' => 'Stream A', 'code' => 'STR-A', 'desc' => 'Primary Stream A division'],
                        ['name' => 'Stream B', 'code' => 'STR-B', 'desc' => 'Primary Stream B division'],
                        ['name' => 'Stream C', 'code' => 'STR-C', 'desc' => 'Stream C division'],
                        ['name' => 'Stream D', 'code' => 'STR-D', 'desc' => 'Stream D division'],
                        ['name' => 'North', 'code' => 'NTH', 'desc' => 'North Wing Stream'],
                        ['name' => 'South', 'code' => 'STH', 'desc' => 'South Wing Stream'],
                        ['name' => 'East', 'code' => 'EST', 'desc' => 'East Wing Stream'],
                        ['name' => 'West', 'code' => 'WST', 'desc' => 'West Wing Stream']
                    ];
                    $insSt = $this->pdo->prepare("INSERT INTO school_streams (stream_name, stream_code, description, is_active) VALUES (?, ?, ?, 1)");
                    foreach ($defaultStreams as $ds) {
                        try { $insSt->execute([$ds['name'], $ds['code'], $ds['desc']]); } catch (Exception $e) {}
                    }
                }
            } catch (Exception $e) {}

            try {
                $frCheck = $this->pdo->query("SELECT COUNT(*) as cnt FROM class_fee_rates");
                $frRow = $frCheck ? $frCheck->fetch(PDO::FETCH_ASSOC) : null;
                if (empty($frRow['cnt'])) {
                    $defaultClassFees = [
                        'Senior 1' => 850000,
                        'Senior 2' => 850000,
                        'Senior 3' => 850000,
                        'Senior 4' => 850000,
                        'Senior 5' => 950000,
                        'Senior 6' => 950000
                    ];
                    $insFR = $this->pdo->prepare("INSERT INTO class_fee_rates (class_level, fee_amount) VALUES (?, ?)");
                    foreach ($defaultClassFees as $cLvl => $amt) {
                        try { $insFR->execute([$cLvl, $amt]); } catch (Exception $e) {}
                    }
                }
            } catch (Exception $e) {}

            try {
                $bkCheck = $this->pdo->query("SELECT COUNT(*) as cnt FROM bank_integrations");
                $bkRow = $bkCheck ? $bkCheck->fetch(PDO::FETCH_ASSOC) : null;
                if (empty($bkRow['cnt'])) {
                    $defaultBanks = [
                        [
                            'code' => 'CENTENARY',
                            'name' => 'Centenary Bank Uganda',
                            'account' => '3100045892',
                            'endpoint' => 'https://api.centenarybank.co.ug/v2/payments',
                            'key' => 'CENT-API-KEY-998124',
                            'secret' => 'CENT-SEC-KEY-771239',
                            'env' => 'production',
                            'active' => 1
                        ],
                        [
                            'code' => 'STANBIC',
                            'name' => 'Stanbic Bank Uganda (FlexiPay)',
                            'account' => '9030018872201',
                            'endpoint' => 'https://flexipay.stanbicbank.co.ug/api/v1/collections',
                            'key' => 'STAN-FLEX-KEY-441029',
                            'secret' => 'STAN-SEC-KEY-881920',
                            'env' => 'production',
                            'active' => 1
                        ],
                        [
                            'code' => 'EQUITY',
                            'name' => 'Equity Bank Uganda',
                            'account' => '103420088192',
                            'endpoint' => 'https://api.equitybankgroup.com/ug/v1/payments',
                            'key' => 'EQ-UG-KEY-102938',
                            'secret' => 'EQ-SEC-KEY-492019',
                            'env' => 'sandbox',
                            'active' => 1
                        ],
                        [
                            'code' => 'MTN_MOMO',
                            'name' => 'MTN MoMo Pay (Merchant 670912)',
                            'account' => '670912',
                            'endpoint' => 'https://sandbox.momodeveloper.mtn.com/collection/v1_0',
                            'key' => 'MTN-MOMO-KEY-392019',
                            'secret' => 'MTN-SEC-KEY-882910',
                            'env' => 'sandbox',
                            'active' => 1
                        ],
                        [
                            'code' => 'AIRTEL_MONEY',
                            'name' => 'Airtel Money Pay (Merchant 440182)',
                            'account' => '440182',
                            'endpoint' => 'https://openapi.airtel.africa/merchant/v1/payments',
                            'key' => 'AIRTEL-MONEY-KEY-551029',
                            'secret' => 'AIRTEL-SEC-KEY-771920',
                            'env' => 'sandbox',
                            'active' => 1
                        ],
                        [
                            'code' => 'SCHOOLPAY',
                            'name' => 'SchoolPay Uganda (PegPay Aggregator)',
                            'account' => 'SCHPAY-UG-8802',
                            'endpoint' => 'https://api.schoolpay.co.ug/v1/collections/reconcile',
                            'key' => 'SCHPAY-KEY-991823',
                            'secret' => 'SCHPAY-SEC-771209',
                            'env' => 'production',
                            'active' => 1
                        ]
                    ];
                    $insBk = $this->pdo->prepare("INSERT INTO bank_integrations (bank_code, bank_name, account_number, api_endpoint, api_key, secret_key, environment, is_active, last_sync_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))");
                    foreach ($defaultBanks as $b) {
                        try {
                            $insBk->execute([$b['code'], $b['name'], $b['account'], $b['endpoint'], $b['key'], $b['secret'], $b['env'], $b['active']]);
                        } catch (Exception $e) {}
                    }
                }

                // Ensure SchoolPay integration exists in existing databases
                $spCheck = $this->pdo->query("SELECT COUNT(*) FROM bank_integrations WHERE bank_code = 'SCHOOLPAY'");
                if ($spCheck && $spCheck->fetchColumn() == 0) {
                    $insSp = $this->pdo->prepare("INSERT INTO bank_integrations (bank_code, bank_name, account_number, api_endpoint, api_key, secret_key, webhook_url, environment, is_active, last_sync_at) VALUES ('SCHOOLPAY', 'SchoolPay Uganda (PegPay Aggregator)', 'SCHPAY-UG-8802', 'https://api.schoolpay.co.ug/v1/collections/reconcile', 'SCHPAY-KEY-991823', 'SCHPAY-SEC-771209', 'http://localhost:8080/admin_fees_actions.php?webhook=1&bank=SCHOOLPAY', 'production', 1, datetime('now'))");
                    $insSp->execute();
                }
            } catch (Exception $e) {}

            // Safe auto-migration for existing database schemas (PostgreSQL & SQLite)
            if ($this->driver === 'pgsql') {
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS is_class_teacher INT DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS class_teacher_of VARCHAR(50)"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS class_teacher_stream VARCHAR(50) DEFAULT 'Stream A'"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS classes_taught VARCHAR(255)"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS can_view_enrollments INT DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS can_manage_duty_roster INT DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS must_change_password INT DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS account_active INT DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS last_login TIMESTAMP WITH TIME ZONE"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS academic_doc_path TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS recommendation_doc_path TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS medical_doc_path TEXT"); } catch (Exception $e) {}
                try {
                    $this->pdo->exec("
                        CREATE TABLE IF NOT EXISTS teacher_personal_schedules (
                            id SERIAL PRIMARY KEY,
                            teacher_id INT NOT NULL,
                            day_of_week VARCHAR(20) NOT NULL,
                            start_time VARCHAR(20) NOT NULL,
                            end_time VARCHAR(20) NOT NULL,
                            subject VARCHAR(100) NOT NULL,
                            class_level VARCHAR(50) NOT NULL,
                            stream VARCHAR(50) DEFAULT 'All Streams',
                            room_no VARCHAR(50),
                            notes TEXT,
                            created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
                        );
                    ");
                } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE reporting_windows ADD COLUMN IF NOT EXISTS show_points INT DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE reporting_windows ADD COLUMN IF NOT EXISTS disabled_points_levels TEXT DEFAULT ''"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE bank_integrations ADD COLUMN IF NOT EXISTS webhook_url TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE bank_integrations ADD COLUMN IF NOT EXISTS webhook_secret TEXT"); } catch (Exception $e) {}
            } else {
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN is_class_teacher INTEGER DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN class_teacher_of TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN class_teacher_stream TEXT DEFAULT 'Stream A'"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN classes_taught TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN can_view_enrollments INTEGER DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE teachers ADD COLUMN can_manage_duty_roster INTEGER DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN password_hash TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN must_change_password INTEGER DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN account_active INTEGER DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN last_login DATETIME"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN academic_doc_path TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN recommendation_doc_path TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE students ADD COLUMN medical_doc_path TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE student_marks ADD COLUMN stream TEXT DEFAULT 'Stream A'"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE student_marks ADD COLUMN window_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE student_marks ADD COLUMN assessment_type TEXT DEFAULT 'EOT'"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE student_marks ADD COLUMN academic_year TEXT DEFAULT '2026'"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE reporting_windows ADD COLUMN show_points INTEGER DEFAULT 1"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE reporting_windows ADD COLUMN disabled_points_levels TEXT DEFAULT ''"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE bank_integrations ADD COLUMN webhook_url TEXT"); } catch (Exception $e) {}
                try { $this->pdo->exec("ALTER TABLE bank_integrations ADD COLUMN webhook_secret TEXT"); } catch (Exception $e) {}
            }

            // Ensure default admin exists and has valid Admin@2026 hash
            $validHash = '$2y$10$NmyZfb876NINiIdxUOgROOSHCRe5SmBF5nt1Ja1DTXjr7/zVj8J6O';
            try {
                $stmtAdmin = $this->pdo->query("SELECT COUNT(*) FROM admins WHERE email = 'admin@thamani.ac.ug' OR admin_id = 'ADM-2026-001'");
                if ($stmtAdmin && $stmtAdmin->fetchColumn() == 0) {
                    $insAdmin = $this->pdo->prepare("INSERT INTO admins (admin_id, full_name, email, password_hash, must_change_password, is_active) VALUES ('ADM-2026-001', 'System Administrator', 'admin@thamani.ac.ug', ?, 0, 1)");
                    $insAdmin->execute([$validHash]);
                } else {
                    $this->pdo->exec("UPDATE admins SET password_hash = '$validHash' WHERE email = 'admin@thamani.ac.ug' OR admin_id = 'ADM-2026-001'");
                }
            } catch (Exception $e) {}

            // Ensure default teacher exists and has valid Admin@2026 hash & TOD permission
            try {
                $stmtTeacher = $this->pdo->query("SELECT COUNT(*) FROM teachers WHERE email = 'teacher@thamani.ac.ug' OR staff_id = 'TSC-2026-001'");
                if ($stmtTeacher && $stmtTeacher->fetchColumn() == 0) {
                    $insTeacher = $this->pdo->prepare("INSERT INTO teachers (staff_id, full_name, email, department, password_hash, must_change_password, is_active, can_manage_duty_roster) VALUES ('TSC-2026-001', 'Mr. Denis Mukasa', 'teacher@thamani.ac.ug', 'Science & Technology', ?, 0, 1, 1)");
                    $insTeacher->execute([$validHash]);
                } else {
                    $this->pdo->exec("UPDATE teachers SET password_hash = '$validHash', can_manage_duty_roster = 1 WHERE email = 'teacher@thamani.ac.ug' OR staff_id = 'TSC-2026-001'");
                }
            } catch (Exception $e) {}

            // Seed default duty roster rows if empty
            try {
                $stmtRoster = $this->pdo->query("SELECT COUNT(*) FROM teacher_duty_rosters");
                if ($stmtRoster && $stmtRoster->fetchColumn() == 0) {
                    $insRoster = $this->pdo->prepare("INSERT INTO teacher_duty_rosters (week_title, senior_duty_teacher, assistant_duty_teacher, primary_focus_area, notes, created_by_role) VALUES (?, ?, ?, ?, ?, 'admin')");
                    $insRoster->execute(['Week 1 (Sept 15 - Sept 21)', 'Mr. Mukasa Denis (Physics Dept)', 'Ms. Namatovu Sarah (English Dept)', 'Dining Hall & Evening Prep Supervision', 'Ensure strict timekeeping during meals and evening preps.']);
                    $insRoster->execute(['Week 2 (Sept 22 - Sept 28)', 'Mr. Okello Patrick (Math Dept)', 'Mrs. Akello Grace (Chemistry Dept)', 'Campus Cleanliness & Assembly Rollcall', 'Inspect dormitory sanitation daily before morning assembly.']);
                    $insRoster->execute(['Week 3 (Sept 29 - Oct 5)', 'Dr. Kiggundu John (Biology Dept)', 'Ms. Atuhaire Brenda (History Dept)', 'Library Silence & Dormitory Lights Out', 'Ensure all students are in dorms by 9:30 PM.']);
                }
            } catch (Exception $e) {}

            // Ensure default teacher exists and has valid Admin@2026 hash
            try {
                $stmtTeacher = $this->pdo->query("SELECT COUNT(*) FROM teachers WHERE email = 'teacher@thamani.ac.ug' OR staff_id = 'TSC-2026-001'");
                if ($stmtTeacher && $stmtTeacher->fetchColumn() == 0) {
                    $insTeacher = $this->pdo->prepare("INSERT INTO teachers (staff_id, full_name, email, department, password_hash, must_change_password, is_active) VALUES ('TSC-2026-001', 'Mr. Denis Mukasa', 'teacher@thamani.ac.ug', 'Science & Technology', ?, 0, 1)");
                    $insTeacher->execute([$validHash]);
                } else {
                    $this->pdo->exec("UPDATE teachers SET password_hash = '$validHash' WHERE email = 'teacher@thamani.ac.ug' OR staff_id = 'TSC-2026-001'");
                }
            } catch (Exception $e) {}
            }
        }

        public function prepare($sql) {
            return new ThamaniPolyfillStmt($this->pdo, $sql, $this->driver);
        }

        public function __get($name) {
            if ($name === 'insert_id' && $this->pdo) {
                try {
                    return (int)$this->pdo->lastInsertId();
                } catch (Exception $e) {
                    return 0;
                }
            }
            return null;
        }
    }
}

if (!$conn) {
    $conn = new ThamaniPolyfillConn();
}

if (!function_exists('mysqli_connect')) {
    function mysqli_connect($h = null, $u = null, $p = null, $db = null) {
        global $conn;
        return $conn;
    }
    function mysqli_connect_error() { return null; }
    function mysqli_connect_errno() { return 0; }
    function mysqli_error($c = null) {
        global $conn;
        if (!($c instanceof ThamaniPolyfillConn)) $c = $conn;
        return $c->error ?? '';
    }
    function mysqli_insert_id($c = null) {
        global $conn;
        if (!($c instanceof ThamaniPolyfillConn)) $c = $conn;
        if ($c instanceof ThamaniPolyfillConn && $c->pdo) {
            try {
                return (int)$c->pdo->lastInsertId();
            } catch (Exception $e) {
                return 0;
            }
        }
        return 0;
    }
    function mysqli_prepare($c, $sql = null) {
        global $conn;
        if (is_string($c) && $sql === null) {
            $sql = $c;
            $c = $conn;
        }
        if (!($c instanceof ThamaniPolyfillConn)) $c = $conn;
        return ($c && $c instanceof ThamaniPolyfillConn) ? $c->prepare($sql) : false;
    }
    function mysqli_stmt_bind_param($stmt, $types, ...$vars) {
        return $stmt ? $stmt->bind_param($types, ...$vars) : false;
    }
    function mysqli_stmt_execute($stmt) {
        return $stmt ? $stmt->execute() : false;
    }
    function mysqli_stmt_store_result($stmt) {
        return $stmt ? $stmt->store_result() : false;
    }
    function mysqli_stmt_num_rows($stmt) {
        return $stmt ? $stmt->num_rows() : 0;
    }
    function mysqli_stmt_affected_rows($stmt) {
        return $stmt ? ($stmt->affectedRows ?? 0) : 0;
    }
    function mysqli_stmt_get_result($stmt) {
        return $stmt ? $stmt->get_result() : false;
    }
    function mysqli_stmt_close($stmt) {
        return $stmt ? $stmt->close() : true;
    }
    function mysqli_stmt_error($stmt) {
        return $stmt ? ($stmt->errorMsg ?? '') : '';
    }
    function mysqli_fetch_assoc($res) {
        if ($res instanceof ThamaniPolyfillResult) {
            return $res->fetch_assoc();
        }
        return null;
    }
    function mysqli_fetch_row($res) {
        if ($res instanceof ThamaniPolyfillResult) {
            return $res->fetch_row();
        }
        return null;
    }
    function mysqli_fetch_array($res) {
        if ($res instanceof ThamaniPolyfillResult) {
            return $res->fetch_array();
        }
        return null;
    }
    function mysqli_num_rows($res) {
        if ($res instanceof ThamaniPolyfillResult) {
            return count($res->rows);
        }
        return 0;
    }
    function mysqli_query($c, $sql = null) {
        global $conn;
        if (is_string($c) && $sql === null) {
            $sql = $c;
            $c = $conn;
        }
        if (!($c instanceof ThamaniPolyfillConn) || !$c->pdo) {
            $c = $conn;
        }
        if (!($c instanceof ThamaniPolyfillConn) || !$c->pdo) {
            global $conn;
            $conn = new ThamaniPolyfillConn();
            $c = $conn;
        }
        if (!($c instanceof ThamaniPolyfillConn) || !$c->pdo) {
            return false;
        }
        try {
            $adjustedSql = $sql;
            if (isset($c->driver) && $c->driver === 'pgsql') {
                $adjustedSql = str_ireplace("datetime('now')", "CURRENT_TIMESTAMP", $adjustedSql);
                $adjustedSql = str_ireplace("NOW()", "CURRENT_TIMESTAMP", $adjustedSql);
            } else {
                $adjustedSql = str_ireplace("NOW()", "datetime('now')", $adjustedSql);
            }
            $stmt = $c->pdo->query($adjustedSql);
            if ($stmt && str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
                $res = new ThamaniPolyfillResult();
                $res->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $res->num_rows = count($res->rows);
                return $res;
            }
            return true;
        } catch (Exception $e) {
            error_log("[mysqli_query Error] " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }
    function mysqli_real_escape_string($c, $str = null) {
        if ($str === null) {
            $str = $c;
        }
        return addslashes($str);
    }
    function mysqli_set_charset($c, $charset = 'utf8') { return true; }
    function mysqli_close($c = null) { return true; }
}

// Keep application queries compatible with native mysqli and the PDO polyfill.
if (!function_exists('thamani_db_prepare')) {
    function thamani_db_error($connection = null) {
        global $conn;
        $connection = $connection ?: $conn;
        if (class_exists('mysqli') && $connection instanceof mysqli) {
            return mysqli_error($connection);
        }
        return $connection->error ?? '';
    }

    function thamani_db_real_escape_string($connection = null, $str = '') {
        if (is_string($connection) && $str === '') {
            $str = $connection;
            $connection = null;
        }
        if (function_exists('mysqli_real_escape_string')) {
            return mysqli_real_escape_string($connection, $str);
        }
        return addslashes($str);
    }

    function thamani_db_insert_id($connection = null) {
        global $conn;
        $connection = $connection ?: $conn;
        if (class_exists('mysqli') && $connection instanceof mysqli) {
            return mysqli_insert_id($connection);
        }
        return (int)($connection->insert_id ?? 0);
    }

    function thamani_db_prepare($connection, $sql = null) {
        global $conn;
        if (is_string($connection) && $sql === null) {
            $sql = $connection;
            $connection = $conn;
        }
        if (class_exists('mysqli') && $connection instanceof mysqli) {
            return mysqli_prepare($connection, $sql);
        }
        return $connection instanceof ThamaniPolyfillConn ? $connection->prepare($sql) : false;
    }

    function thamani_db_query($connection, $sql = null) {
        global $conn;
        if (is_string($connection) && $sql === null) {
            $sql = $connection;
            $connection = $conn;
        }
        if (class_exists('mysqli') && $connection instanceof mysqli) {
            return mysqli_query($connection, $sql);
        }
        if (!($connection instanceof ThamaniPolyfillConn) || !$connection->pdo) {
            return false;
        }
        try {
            $adjustedSql = $sql;
            if ($connection->driver === 'pgsql') {
                $adjustedSql = str_ireplace("datetime('now')", 'CURRENT_TIMESTAMP', $adjustedSql);
                $adjustedSql = str_ireplace('NOW()', 'CURRENT_TIMESTAMP', $adjustedSql);
            } else {
                $adjustedSql = str_ireplace('NOW()', "datetime('now')", $adjustedSql);
            }
            $statement = $connection->pdo->query($adjustedSql);
            if ($statement && str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
                $result = new ThamaniPolyfillResult();
                $result->rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $result->num_rows = count($result->rows);
                return $result;
            }
            return true;
        } catch (Exception $e) {
            $connection->error = $e->getMessage();
            error_log('[Thamani DB Query Error] ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }

    function thamani_db_stmt_bind_param($stmt, $types, &...$vars) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_bind_param($stmt, $types, ...$vars);
        }
        return $stmt ? $stmt->bind_param($types, ...$vars) : false;
    }

    function thamani_db_stmt_bind_result($stmt, &...$vars) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_bind_result($stmt, ...$vars);
        }
        return false;
    }

    function thamani_db_stmt_execute($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_execute($stmt);
        }
        return $stmt ? $stmt->execute() : false;
    }

    function thamani_db_stmt_store_result($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_store_result($stmt);
        }
        return $stmt ? $stmt->store_result() : false;
    }

    function thamani_db_stmt_num_rows($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_num_rows($stmt);
        }
        return $stmt ? $stmt->num_rows() : 0;
    }

    function thamani_db_stmt_affected_rows($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_affected_rows($stmt);
        }
        return $stmt ? ($stmt->affectedRows ?? 0) : 0;
    }

    function thamani_db_stmt_get_result($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_get_result($stmt);
        }
        return $stmt ? $stmt->get_result() : false;
    }

    function thamani_db_stmt_fetch($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_fetch($stmt);
        }
        return false;
    }

    function thamani_db_stmt_close($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_close($stmt);
        }
        return $stmt ? $stmt->close() : true;
    }

    function thamani_db_stmt_error($stmt) {
        if (class_exists('mysqli_stmt') && $stmt instanceof mysqli_stmt) {
            return mysqli_stmt_error($stmt);
        }
        return $stmt ? ($stmt->errorMsg ?? '') : '';
    }

    function thamani_db_fetch_assoc($result) {
        if (class_exists('mysqli_result') && $result instanceof mysqli_result) {
            return mysqli_fetch_assoc($result);
        }
        return $result instanceof ThamaniPolyfillResult ? $result->fetch_assoc() : null;
    }

    function get_system_streams($conn = null) {
        if (!$conn) $conn = $GLOBALS['conn'];
        $streams = [];
        $res = thamani_db_query($conn, "SELECT stream_name FROM school_streams WHERE is_active = 1 ORDER BY id ASC");
        if ($res) {
            while ($r = thamani_db_fetch_assoc($res)) {
                $streams[] = $r['stream_name'];
            }
        }
        if (empty($streams)) {
            $streams = ['Stream A', 'Stream B', 'Stream C', 'Stream D', 'North', 'South', 'East', 'West'];
        }
        return $streams;
    }

    function get_all_system_streams_details($conn = null) {
        if (!$conn) $conn = $GLOBALS['conn'];
        $streams = [];
        $res = thamani_db_query($conn, "SELECT id, stream_name, stream_code, description, is_active, created_at FROM school_streams ORDER BY id ASC");
        if ($res) {
            while ($r = thamani_db_fetch_assoc($res)) {
                $streams[] = $r;
            }
        }
        return $streams;
    }

    if (!function_exists('isPointsEnabledForLevel')) {
        function isPointsEnabledForLevel($windowRow, $classLevel) {
            if (!$windowRow) return true;
            
            $globalShowPoints = isset($windowRow['show_points']) ? (int)$windowRow['show_points'] : 1;
            if ($globalShowPoints === 0) {
                return false;
            }
            
            $disabledStr = isset($windowRow['disabled_points_levels']) ? trim($windowRow['disabled_points_levels']) : '';
            if ($disabledStr === '') {
                return true;
            }
            
            $disabledList = array_map('trim', explode(',', $disabledStr));
            $classLevelTrimmed = trim($classLevel);
            
            if (in_array($classLevelTrimmed, $disabledList, true)) {
                return false;
            }
            
            $isOLevel = in_array($classLevelTrimmed, ['Senior 1', 'Senior 2', 'Senior 3', 'Senior 4'], true);
            $isALevel = in_array($classLevelTrimmed, ['Senior 5', 'Senior 6'], true);
            
            if ($isOLevel && in_array('O-Level', $disabledList, true)) {
                return false;
            }
            if ($isALevel && in_array('A-Level', $disabledList, true)) {
                return false;
            }
            
            return true;
        }
    }

    if (!function_exists('thamani_get_setting')) {
        function thamani_get_setting($conn, string $key, string $default = ''): string {
            $stmt = thamani_db_prepare($conn, "SELECT setting_value FROM school_settings WHERE setting_key = ? LIMIT 1");
            if ($stmt) {
                thamani_db_stmt_bind_param($stmt, "s", $key);
                thamani_db_stmt_execute($stmt);
                $res = thamani_db_stmt_get_result($stmt);
                if ($res && $row = thamani_db_fetch_assoc($res)) {
                    thamani_db_stmt_close($stmt);
                    return (string)$row['setting_value'];
                }
                thamani_db_stmt_close($stmt);
            }
            return $default;
        }
    }

    if (!function_exists('thamani_set_setting')) {
        function thamani_set_setting($conn, string $key, string $value): bool {
            $existing = thamani_get_setting($conn, $key, '__NOT_SET__');
            if ($existing !== '__NOT_SET__') {
                $stmt = thamani_db_prepare($conn, "UPDATE school_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
                if ($stmt) {
                    thamani_db_stmt_bind_param($stmt, "ss", $value, $key);
                    $ok = thamani_db_stmt_execute($stmt);
                    thamani_db_stmt_close($stmt);
                    return $ok;
                }
            } else {
                $stmt = thamani_db_prepare($conn, "INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?)");
                if ($stmt) {
                    thamani_db_stmt_bind_param($stmt, "ss", $key, $value);
                    $ok = thamani_db_stmt_execute($stmt);
                    thamani_db_stmt_close($stmt);
                    return $ok;
                }
            }
            return false;
        }
    }

    if (!function_exists('thamani_get_class_fee')) {
        function thamani_get_class_fee($conn, string $classLevel): float {
            $stmt = thamani_db_prepare($conn, "SELECT fee_amount FROM class_fee_rates WHERE class_level = ? LIMIT 1");
            if ($stmt) {
                thamani_db_stmt_bind_param($stmt, "s", $classLevel);
                thamani_db_stmt_execute($stmt);
                $res = thamani_db_stmt_get_result($stmt);
                if ($res && $row = thamani_db_fetch_assoc($res)) {
                    thamani_db_stmt_close($stmt);
                    return (float)$row['fee_amount'];
                }
                thamani_db_stmt_close($stmt);
            }
            return (str_contains($classLevel, 'A-Level') || str_contains($classLevel, 'Senior 5') || str_contains($classLevel, 'Senior 6')) ? 950000.0 : 850000.0;
        }
    }

    if (!function_exists('thamani_set_class_fee')) {
        function thamani_set_class_fee($conn, string $classLevel, float $amount): bool {
            $stmtCheck = thamani_db_prepare($conn, "SELECT id FROM class_fee_rates WHERE class_level = ? LIMIT 1");
            $exists = false;
            if ($stmtCheck) {
                thamani_db_stmt_bind_param($stmtCheck, "s", $classLevel);
                thamani_db_stmt_execute($stmtCheck);
                $res = thamani_db_stmt_get_result($stmtCheck);
                if ($res && thamani_db_fetch_assoc($res)) {
                    $exists = true;
                }
                thamani_db_stmt_close($stmtCheck);
            }

            if ($exists) {
                $stmt = thamani_db_prepare($conn, "UPDATE class_fee_rates SET fee_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE class_level = ?");
                if ($stmt) {
                    thamani_db_stmt_bind_param($stmt, "ds", $amount, $classLevel);
                    $ok = thamani_db_stmt_execute($stmt);
                    thamani_db_stmt_close($stmt);
                    return $ok;
                }
            } else {
                $stmt = thamani_db_prepare($conn, "INSERT INTO class_fee_rates (class_level, fee_amount) VALUES (?, ?)");
                if ($stmt) {
                    thamani_db_stmt_bind_param($stmt, "sd", $classLevel, $amount);
                    $ok = thamani_db_stmt_execute($stmt);
                    thamani_db_stmt_close($stmt);
                    return $ok;
                }
            }
        }
    }

    if (!function_exists('generate_student_pay_code')) {
        function generate_student_pay_code($conn, $studentId, $classLevel = '', $stream = '', $registeredAt = '') {
            if (!$conn) $conn = $GLOBALS['conn'];

            $yearStr = !empty($registeredAt) ? date('y', strtotime($registeredAt)) : date('y');

            $streamCode = 'hmg';
            if (!empty($stream)) {
                $stRes = thamani_db_query($conn, "SELECT stream_code FROM school_streams WHERE stream_name = '" . thamani_db_real_escape_string($conn, $stream) . "' LIMIT 1");
                if ($stRes && ($stRow = thamani_db_fetch_assoc($stRes)) && !empty($stRow['stream_code'])) {
                    $streamCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $stRow['stream_code']));
                } else {
                    $streamCode = strtolower(substr(preg_replace('/[^a-zA-Z0-9]/', '', $stream), 0, 3)) ?: 'hmg';
                }
            }

            $letter = chr(97 + (($studentId - 1) % 26));
            $seqCode = (int)$studentId . $letter;

            return strtolower($seqCode . '/' . $streamCode . '/' . $yearStr);
        }
    }

    if (!function_exists('ensure_student_pay_codes')) {
        function ensure_student_pay_codes($conn = null) {
            if (!$conn) $conn = $GLOBALS['conn'];

            @thamani_db_query($conn, "ALTER TABLE students ADD COLUMN pay_code TEXT");
            @thamani_db_query($conn, "ALTER TABLE student_fees ADD COLUMN pay_code TEXT");

            $res = thamani_db_query($conn, "SELECT id, full_name, class_level, stream, registered_at, pay_code FROM students WHERE pay_code IS NULL OR pay_code = '' ORDER BY id ASC");
            if ($res) {
                while ($st = thamani_db_fetch_assoc($res)) {
                    $payCode = generate_student_pay_code($conn, $st['id'], $st['class_level'], $st['stream'], $st['registered_at']);
                    $sId = (int)$st['id'];
                    $escapedPc = thamani_db_real_escape_string($conn, $payCode);

                    thamani_db_query($conn, "UPDATE students SET pay_code = '{$escapedPc}' WHERE id = {$sId}");
                    thamani_db_query($conn, "UPDATE student_fees SET pay_code = '{$escapedPc}' WHERE student_id = {$sId}");
                }
            }
        }
    }

    ensure_student_pay_codes($conn);
}

return $conn;
?>