# TUCU Website — Laravel + MySQL Backend (Migration from Node.js/SQLite)

This is the migrated backend for the **Tharaka University Christian Union** website.
It replaces the previous Node.js/Express + SQLite backend with **Laravel 11 + MySQL**,
and adds a **personal member portal** (`member.html`) that every member gets
automatically upon registration.

---

## 📁 Project Structure

```
tucu-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── MemberAuthController.php      → register / login / logout (members)
│   │   │   ├── MemberPortalController.php    → profile, events, prayers, newsletter (own data only)
│   │   │   ├── AdminAuthController.php       → admin login / logout
│   │   │   ├── AdminController.php           → admin dashboard endpoints
│   │   │   ├── PrayerRequestController.php   → public prayer request submission
│   │   │   ├── EventController.php           → public event list + registration
│   │   │   ├── NewsletterController.php       → newsletter subscribe
│   │   │   └── ContactController.php          → contact form
│   │   ├── Middleware/
│   │   │   └── OptionalSanctumAuth.php        → allows guest OR logged-in member on public routes
│   │   └── Requests/
│   │       └── RegisterMemberRequest.php      → registration validation rules
│   ├── Models/
│   │   ├── Member.php           (Sanctum-auth model)
│   │   ├── Admin.php            (Sanctum-auth model)
│   │   ├── PrayerRequest.php
│   │   ├── Event.php
│   │   ├── EventRegistration.php
│   │   ├── ContactMessage.php
│   │   └── NewsletterSubscriber.php
│   └── Providers/AppServiceProvider.php
├── bootstrap/app.php             → Laravel 11 app bootstrap (routes, middleware)
├── config/
│   ├── app.php, auth.php (dual guards), cors.php, database.php (MySQL), sanctum.php
├── database/
│   ├── migrations/                → all 7 tables incl. Sanctum tokens
│   └── seeders/                   → default admin + sample events
├── routes/
│   ├── api.php   → all REST API endpoints
│   └── web.php   → serves index.html / member.html / admin.html
└── public/
    ├── index.html   → main TUCU website (connected to API)
    ├── member.html  → 🆕 personal member portal
    ├── admin.html   → admin dashboard
    ├── index.php    → Laravel front controller
    └── .htaccess    → routes /api/* to Laravel, everything else static
```

---

## 🗄️ Database Schema (MySQL)

| Table | Purpose |
|---|---|
| `members` | Full member info — name, email, password (hashed), phone, course, year, ministry, status, role, admin_notes |
| `prayer_requests` | Linked to `member_id` (nullable for guests) |
| `events` | Event catalogue (title, category, location, dates) |
| `event_registrations` | Linked to `member_id` (nullable for guests) and `event_id` |
| `contact_messages` | Contact form submissions |
| `newsletter_subscribers` | Linked to `member_id` (nullable), tracks subscription status |
| `admins` | Admin accounts (separate from members) |
| `personal_access_tokens` | Sanctum auth tokens for both members and admins |

---

## ⚙️ Setup Instructions

### 1. Requirements
- PHP ≥ 8.2 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer
- MySQL ≥ 8.0 (or MariaDB ≥ 10.3)

### 2. Install dependencies
```bash
cd tucu-laravel
composer install
python -m pip install -r scripts/requirements-library.txt
```

The library import command uses PyMuPDF to generate a cover from the first page of
each valid PDF that has no usable uploaded cover. Set `LIBRARY_PYTHON_BINARY` in
`.env` to the Python executable that has these requirements installed if `python`
is not available on the PHP process's PATH.

For a larger PDF collection, prepare and review a metadata manifest before importing:

```bash
python scripts/prepare-library-directory.py \
  --source "D:/E_books" \
  --output "storage/app/library-import/manifest.json" \
  --covers "storage/app/library-import/covers"
php artisan library:import-directory storage/app/library-import/manifest.json --dry-run
php artisan library:import-directory storage/app/library-import/manifest.json --apply
```

The preparation step extracts PDF metadata, suggests categories, and generates
first-page covers. The import is idempotent by file checksum, creates missing
categories, stores PDFs and covers privately, and adds new titles as drafts.
Convert Word and PowerPoint files to PDF before preparing them. Keep the source
files unchanged and review generated metadata and categories before publishing.

The authenticated admin console includes a **Books & Categories** section for
creating categories, adding titles and PDF editions, updating metadata/covers,
and publishing or archiving books. Uploaded PDFs and covers are kept in private
Laravel storage; a first-page cover is generated automatically when no cover is
uploaded. The configured PDF limit is 64 MiB, so PHP's `upload_max_filesize` and
`post_max_size` must be set to at least 64M and 65M respectively for the full
limit to be usable.

### 3. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your MySQL credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tucu_db
DB_USERNAME=tucu_user
DB_PASSWORD=your_password
```

### 4. Create the database
```sql
CREATE DATABASE tucu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tucu_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON tucu_db.* TO 'tucu_user'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Run migrations + seed default data
```bash
php artisan migrate --seed
```

This creates all 7 tables and seeds:
- **Default admin** → username: `admin` / password: `tucu2026admin`
- **Sample upcoming events** (Friday Fellowship, Prayer Vigil, Missions Day)

⚠️ **Change the admin password immediately** after first login.

### 6. Start the server
```bash
php artisan serve
```

The app will run at **http://localhost:8000**

| URL | Page |
|---|---|
| `http://localhost:8000/` | Main TUCU website |
| `http://localhost:8000/member.html` | Member portal (login / register) |
| `http://localhost:8000/admin.html` | Admin dashboard |
| `http://localhost:8000/api/...` | REST API |

---

## 🔑 Authentication Model

Two **separate Sanctum guards** are configured so member tokens and admin
tokens can never cross-authenticate:

| Guard | Provider | Used by |
|---|---|---|
| `sanctum` | `members` table | Member portal (`member.html`) |
| `admin-sanctum` | `admins` table | Admin dashboard (`admin.html`) |

Both issue **bearer tokens** via `Authorization: Bearer <token>` headers.

### Member Registration Flow (the core new feature)
1. Visitor fills out the **Join CU** form on the homepage (now includes password fields) **or** registers directly via `member.html`.
2. `POST /api/members/register` creates the member record **and immediately issues a Sanctum token**.
3. The frontend stores this token in `localStorage` and redirects to `/member.html`.
4. `member.html` auto-detects the token and loads the member's **personal portal** — no separate login step needed.

### Access Control
- `GET /api/members/{id}/profile` (and `/events`, `/prayers`, `/newsletter`) are protected by `MemberPortalController::authorizeMember()`, which checks `$request->user()->id === $id`. A member can **never** view another member's data — any mismatch returns `403 Forbidden`.
- Admin routes (`/api/admin/*`) require the `admin-sanctum` guard — a member token cannot access them, and vice versa.

---

## 🔌 API Endpoints

### Public
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/members/register` | Register + auto-create portal (returns token) |
| POST | `/api/members/login` | Member login (returns token) |
| GET | `/api/events` | List upcoming events |
| POST | `/api/events/register` | Register for event (links to member if token present) |
| POST | `/api/prayer` | Submit prayer request (links to member if token present) |
| POST | `/api/newsletter/subscribe` | Subscribe to newsletter |
| POST | `/api/contact` | Contact form |
| POST | `/api/admin/login` | Admin login (returns token) |

### Member Portal (requires member Sanctum token)
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/members/{id}/profile` | Full profile + newsletter status + summary counts |
| PUT | `/api/members/{id}/profile` | Update own profile / password |
| GET | `/api/members/{id}/events` | Own event registration history |
| GET | `/api/members/{id}/prayers` | Own prayer request history |
| PUT | `/api/members/{id}/newsletter` | Toggle own newsletter subscription |
| POST | `/api/members/logout` | Revoke current token |

### Admin Dashboard (requires admin Sanctum token)
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/admin/stats` | Dashboard counters |
| GET | `/api/admin/members` | All members with nested events/prayers/newsletter |
| GET | `/api/admin/members/{id}` | Single member full profile |
| PUT | `/api/admin/members/{id}` | Update member info, role, status, admin notes |
| PUT | `/api/admin/members/{id}/status` | Quick active/inactive toggle |
| DELETE | `/api/admin/members/{id}` | Remove member |
| GET | `/api/admin/prayers` | All prayer requests |
| PUT | `/api/admin/prayers/{id}/status` | Mark prayed/pending |
| GET | `/api/admin/registrations` | All event registrations |
| PUT | `/api/admin/registrations/{id}/status` | registered/attended/cancelled |
| GET | `/api/admin/messages` | Contact messages |
| PUT | `/api/admin/messages/{id}/read` | Mark message read |
| GET | `/api/admin/subscribers` | Newsletter subscribers |
| POST | `/api/admin/logout` | Revoke current token |

---

## 🌐 Frontend ↔ Backend Connection

Each HTML file has an `API` constant near the top of its `<script>` block:

```javascript
const API = 'http://localhost:8000/api';
```

**Update this to your production domain** when deploying, e.g.:
```javascript
const API = 'https://api.tucu.ac.ke/api';
```

Also update `config/cors.php` → `allowed_origins` to include your frontend's
production domain so the browser allows cross-origin requests.

---

## 👤 member.html — What It Does

| Tab | Content |
|---|---|
| **Profile** | Editable name, phone, course, year, ministry. Read-only email/reg-number. Password change form. Displays any admin notes. |
| **My Events** | Full history of event registrations with status badges |
| **Prayer Requests** | Full history of submitted prayer requests with status |
| **Newsletter** | Toggle subscription on/off, shows subscribed-since date |

The portal opens straight to login/register screens if no valid token is
found in `localStorage`, and auto-redirects to the dashboard if one is.

---

## 🛠️ Admin Dashboard Additions

- **View** button on each member row opens a detail modal showing:
  - Editable profile fields
  - Admin-only controls: **status** (active/inactive), **role** (member/leader/committee), **admin notes** (visible to the member in their portal)
  - That member's full **event registration** and **prayer request** history
  - Newsletter subscription status

---

## 🚀 Production Deployment Notes

1. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
2. Run `php artisan config:cache && php artisan route:cache`
3. Point your web server's document root to `public/`
4. Ensure `storage/` and `bootstrap/cache/` are writable by the web server user:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
5. Use HTTPS — update `SANCTUM_STATEFUL_DOMAINS` and `config/cors.php` accordingly
6. Consider a process manager (Supervisor) if adding queues later

### Nginx example (Laravel + static frontend together)
```nginx
server {
    listen 80;
    server_name tucu.ac.ke;
    root /var/www/tucu-laravel/public;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ ^/api {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🔄 Migration Summary (What Changed)

| Before (Node.js) | After (Laravel) |
|---|---|
| Express + SQLite (`tucu.db`) | Laravel 11 + MySQL |
| Custom JWT signing | Laravel Sanctum (2 guards: member / admin) |
| No member login | Members register with password → instant portal access |
| Flat `members` table, no auth | `members` table is now a full Authenticatable model |
| No personal portal | `member.html` — profile, events, prayers, newsletter, all scoped to `auth()->id()` |
| Admin saw flat lists | Admin can now drill into any member's full profile + history via modal |

---

## ✅ Default Credentials (change immediately)

| Role | Username/Email | Password |
|---|---|---|
| Admin | `admin` | `tucu2026admin` |

---

Built for Tharaka University Christian Union, 2026.
