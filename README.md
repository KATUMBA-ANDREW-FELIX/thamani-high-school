# Thamani High School — School Management Portal

A full-featured PHP school management system for Ugandan secondary schools (Senior 1–6). Works on any PHP 8.0+ server with either **SQLite** (zero-config, great for local dev) or **PostgreSQL** (production).

---

## ✅ Requirements

| Requirement | Notes |
|---|---|
| **PHP 8.0+** | 8.1+ recommended |
| **PHP extensions** | `pdo`, `pdo_sqlite` (built in), `curl` (for SMS), optionally `pdo_pgsql` |
| **Web server** | Apache (with `mod_rewrite`) or Nginx or PHP built-in server |
| **No Composer needed** | Pure PHP, no external dependencies |

---

## 🚀 Local Quick-Start (SQLite — zero config)

```bash
# 1. Clone the repo
git clone git@github.com:KATUMBA-ANDREW-FELIX/thamani-high-school.git
cd thamani-high-school

# 2. Start the PHP dev server
php -S localhost:8000

# 3. Open in browser
open http://localhost:8000
```

The app auto-creates `thamani_database.sqlite` in the project root on first load. No database setup needed.

### Seed demo data (optional)

```bash
php seed_mock_data.php
```

This creates 3 teachers, 9 students, sample marks, grading scales, and reporting windows.

**Demo credentials (password for all: `Admin@2026`)**

| Role | Username / ID |
|---|---|
| Admin | `admin@thamani.ac.ug` |
| Teacher | `teacher@thamani.ac.ug` |
| Student | `LIN-2026-001` |

---

## 🐘 Production Setup (PostgreSQL)

Set these environment variables before starting PHP:

```bash
export DB_CONNECTION=pgsql
export POSTGRES_HOST=localhost
export POSTGRES_PORT=5432
export POSTGRES_DB=thamani_db
export POSTGRES_USER=postgres
export POSTGRES_PASSWORD=your_password
```

The app runs the schema from `schema_pg.sql` automatically on first boot.

---

## 📁 Project Structure

```
├── conn.php                  # DB connection engine (SQLite + PostgreSQL polyfill)
├── schema_pg.sql             # PostgreSQL schema (auto-applied on boot)
├── seed_mock_data.php        # Demo data seeder
├── login.php                 # Login (admin / teacher / student)
├── admin_dashboard.php       # Admin control panel
├── teacher_dashboard.php     # Teacher workspace
├── student-dashboard.php     # Student portal
│
├── sms_helper.php            # Ego SMS API helper functions
├── admin_sms_actions.php     # SMS JSON endpoint (all SMS actions)
├── sms.php                   # SMS workspace for authorised teachers
│
├── uploads/                  # File uploads (enrollment docs, gallery, etc.)
│   ├── enrollment_docs/
│   ├── gallery/
│   └── library/
└── router.php                # Optional single-entry-point router
```

---

## 📲 Ego SMS Integration (Bulk SMS)

The SMS Centre lets admins (and SMS-authorised teachers) send bulk SMS to saved contacts.

### Setup
1. Log in to [comms.egosms.co](https://comms.egosms.co) → **Settings → API Settings**
2. Copy your **API Username** and **API Password**
3. In the admin dashboard, open the **SMS Centre** tab → **SMS Settings**
4. Enter your username, password, and sender ID (max 11 chars, e.g. `THAMANI`)
5. Click **Save & Test** to send a test SMS to your number

### API Details (for reference)
- **Endpoint:** `POST https://comms.egosms.co/api/v1/json/`
- **Auth:** `userdata.username` / `userdata.password`  
- **Method:** `SendSms`
- **Numbers:** Ugandan format `256XXXXXXXXX` (no `+`, no `0` prefix)

---

## 🔒 Security Features

- CSRF tokens on all POST forms and AJAX endpoints  
- `password_hash()` / `password_verify()` for all passwords  
- Prepared statements throughout (no raw SQL concatenation with user data)  
- Session guards on every protected page (`require_admin_login()`, `require_teacher_login()`, `require_student_login()`)  
- File uploads validated by MIME type + extension whitelist  
- `htmlspecialchars()` on all output  

---

## 🌐 Apache `.htaccess` (for clean URLs / router)

```apache
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ router.php [QSA,L]
```

---

## 📄 License

Private — Thamani High School, Uganda. All rights reserved.
