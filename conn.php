<?php
// THAMANI HIGH SCHOOL - Database Connection Engine & SQLite Polyfill
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "themani";

// If native mysqli extension exists, use it:
if (function_exists('mysqli_connect')) {
    $conn = @mysqli_connect($host, $user, $pass, $dbname);
    if (!$conn) {
        error_log("Database Connection Notice: MySQL at {$host} not accessible.");
    }
} else {
    // Polyfill mysqli using PDO SQLite so standalone PHP CLI binary runs seamlessly
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
            public $sql;
            public $params = [];
            public $resultRows = [];
            public $affectedRows = 0;
            public $lastInsertId = 0;
            public $errorMsg = '';

            public function __construct($pdo, $sql) {
                $this->pdo = $pdo;
                // Convert MySQL specific SQL syntax to SQLite standard if needed
                $this->sql = str_ireplace('NOW()', "datetime('now')", $sql);
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
                            $this->lastInsertId = $this->pdo->lastInsertId();
                        }
                        return true;
                    }
                } catch (Exception $e) {
                    $this->errorMsg = $e->getMessage();
                }
                return false;
            }

            public function get_result() {
                $res = new ThamaniPolyfillResult();
                $res->rows = $this->resultRows;
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
            public $insert_id = 0;
            public $error = '';
            public function __construct() {
                $dbPath = __DIR__ . '/thamani_database.sqlite';
                $this->pdo = new PDO('sqlite:' . $dbPath);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->initTables();
            }

            private function initTables() {
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

                // Check if default admin exists
                $stmtAdmin = $this->pdo->query("SELECT COUNT(*) FROM admins");
                if ($stmtAdmin->fetchColumn() == 0) {
                    $adminHash = password_hash('Admin@2026', PASSWORD_BCRYPT);
                    $insAdmin = $this->pdo->prepare("INSERT INTO admins (admin_id, full_name, email, password_hash, must_change_password, is_active) VALUES ('ADM-2026-001', 'System Administrator', 'admin@thamani.ac.ug', ?, 0, 1)");
                    $insAdmin->execute([$adminHash]);
                }

                // Auto-migrate missing columns for existing SQLite databases
                try { @$this->pdo->exec("ALTER TABLE teachers ADD COLUMN created_at DATETIME"); } catch (\Throwable $e) {}
                try { @$this->pdo->exec("ALTER TABLE admins ADD COLUMN created_at DATETIME"); } catch (\Throwable $e) {}

                // Check if default teacher exists
                $stmtTeacher = $this->pdo->query("SELECT COUNT(*) FROM teachers");
                if ($stmtTeacher->fetchColumn() == 0) {
                    $teacherHash = password_hash('Admin@2026', PASSWORD_BCRYPT);
                    $insTeacher = $this->pdo->prepare("INSERT INTO teachers (staff_id, full_name, email, department, password_hash, must_change_password, is_active) VALUES ('TSC-2026-001', 'Mr. Denis Mukasa', 'teacher@thamani.ac.ug', 'Science & Technology', ?, 0, 1)");
                    $insTeacher->execute([$teacherHash]);
                }

                // Seed sample gallery photos if empty
                $stmtGal = $this->pdo->query("SELECT COUNT(*) FROM gallery_photos");
                if ($stmtGal->fetchColumn() == 0) {
                    $insGal = $this->pdo->prepare("INSERT INTO gallery_photos (title, caption, category, file_name, stored_name, file_path, file_size, mime_type, uploaded_by, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
                    $insGal->execute(['School Main Campus', 'Overview of Thamani High School Kakiri Main Campus.', 'campus', 'school.JPG', 'school.JPG', 'school.JPG', 11538752, 'image/jpeg']);
                    $insGal->execute(['Athletics Competition', 'Students competing in track and field event.', 'sports', 'athletics.jpeg', 'athletics.jpeg', 'athletics.jpeg', 425798, 'image/jpeg']);
                    $insGal->execute(['Football Championship', 'Inter-house football tournament final match.', 'sports', 'football.jpeg', 'football.jpeg', 'football.jpeg', 491767, 'image/jpeg']);
                    $insGal->execute(['Netball Tournament', 'Girls netball team in action during regional finals.', 'sports', 'netball.jpeg', 'netball.jpeg', 'netball.jpeg', 430995, 'image/jpeg']);
                    $insGal->execute(['Lawn Tennis Court', 'Tennis practice during afternoon co-curricular activities.', 'sports', 'tennis.jpeg', 'tennis.jpeg', 'tennis.jpeg', 402872, 'image/jpeg']);
                    $insGal->execute(['Volleyball Finals', 'School volleyball team celebrating victory.', 'sports', 'volleyball.jpeg', 'volleyball.jpeg', 'volleyball.jpeg', 473643, 'image/jpeg']);
                }

                // Seed sample calendar document if empty
                $stmtCal = $this->pdo->query("SELECT COUNT(*) FROM calendar_documents");
                if ($stmtCal->fetchColumn() == 0) {
                    $insCal = $this->pdo->prepare("INSERT INTO calendar_documents (title, doc_type, description, file_name, stored_name, file_path, file_size, mime_type, uploaded_by, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
                    $insCal->execute(['Term III 2026 Academic Calendar & Fee Structure', 'calendar', 'Official term schedules, visitation dates, national examination dates, and tuition breakdown for Term III 2026.', 'Term_III_2026_School_Circular.pdf', 'Term_III_2026_School_Circular.pdf', 'Term_III_2026_School_Circular.pdf', 749, 'application/pdf']);
                }
            }

            public function prepare($sql) {
                return new ThamaniPolyfillStmt($this->pdo, $sql);
            }

            public function __get($name) {
                if ($name === 'insert_id' && $this->pdo) {
                    return (int)$this->pdo->lastInsertId();
                }
                return null;
            }
        }
    }

    global $conn;
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
                return (int)$c->pdo->lastInsertId();
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
                $stmt = $c->pdo->query(str_ireplace('NOW()', "datetime('now')", $sql));
                if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
                    $res = new ThamaniPolyfillResult();
                    $res->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $res->num_rows = count($res->rows);
                    return $res;
                }
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        function mysqli_real_escape_string($c, $str) { return addslashes($str); }
        function mysqli_set_charset($c, $charset) { return true; }
        function mysqli_close($c) { return true; }
    }
}
?>