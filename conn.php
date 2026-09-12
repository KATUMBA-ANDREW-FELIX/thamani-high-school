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
                        status TEXT DEFAULT 'Pending',
                        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS teachers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        staff_id TEXT UNIQUE,
                        full_name TEXT,
                        email TEXT UNIQUE,
                        department TEXT,
                        password_hash TEXT,
                        must_change_password INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1,
                        last_login DATETIME,
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
                ");

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
    function mysqli_error($c) { return $c->error ?? ''; }
    function mysqli_insert_id($c) {
        if ($c instanceof ThamaniPolyfillConn && $c->pdo) {
            try {
                return (int)$c->pdo->lastInsertId();
            } catch (Exception $e) {
                return 0;
            }
        }
        return 0;
    }
    function mysqli_prepare($c, $sql) {
        return $c ? $c->prepare($sql) : false;
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
    function mysqli_query($c, $sql) {
        if (!$c) return false;
        try {
            $adjustedSql = $sql;
            if (isset($c->driver) && $c->driver === 'pgsql') {
                $adjustedSql = str_ireplace("datetime('now')", "CURRENT_TIMESTAMP", $adjustedSql);
                $adjustedSql = str_ireplace("NOW()", "CURRENT_TIMESTAMP", $adjustedSql);
            } else {
                $adjustedSql = str_ireplace("NOW()", "datetime('now')", $adjustedSql);
            }
            $stmt = $c->pdo->query($adjustedSql);
            if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
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
    function mysqli_real_escape_string($c, $str) { return addslashes($str); }
    function mysqli_set_charset($c, $charset) { return true; }
    function mysqli_close($c) { return true; }
}
?>