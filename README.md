# ID Studio — Employee ID Management & Custom Template Designer

Standalone **Laravel 13 / PHP 8.3+ / Livewire 4 / Blade / Tailwind CSS 4 / MySQL 8.4** portfolio project, designed to be independently deployed and **later integrated** with `Knowlexit09/attendance-payroll-system` without sharing a database. The existing payroll repository is **not modified**.

## What is included

| Component | State | Description |
|---|---|---|
| Browser portfolio demo | ✅ Interactive, offline | `demo/index.html`: drag, resize, exact mm, front/back, styling, background/logo upload, draft save in local storage, JSON export and print mockup; uses **fictional data only**. |
| Laravel backend source overlay | ✅ Authored; installation needed | Company-scoped employees, SSS / PhilHealth / Pag-IBIG / TIN encrypted, emergency contact, photo upload, roles, audit entries, templates, versions, issues, QR verification, private assets. |
| Laravel ID designer | ✅ Authored; installation needed | Front/back design, saved draft, publish immutable versions, upload background/logo, draggable/resizable text/image/QR elements. |
| Laravel print workflow | ✅ Authored; installation needed | Issue/revoke card, actual-mm print preview front/back, server-generated QR and verification endpoint. Browser printing to PDF is available; native batch PDF output is NOT included. |
| GitHub CI sample | ✅ Authored; not run | `.github/workflows/ci.yml` runs SQLite/MySQL tests and frontend build after installation. |
| Payroll system API sync | ⏳ Not implemented | UUID mapping/schema provided. No API credentials, endpoints, or OAuth pairing have been installed. |
| Production hardening | ⏳ Not complete | MFA, invitation identity verification, image re-encoding, DPIA, backups/restores, monitoring, performance and pen-test required before real employee information. |

### Safety: development/portfolio stage only

Do **not** publish real employee photos, government numbers or emergency contacts in the demo, GitHub, logs, or issues. Before deploying with real PII, add MFA, encryption key management, email-verification/invitation workflows, retention policy, privacy impact assessment, tested backups and third-party security review. The QR endpoint deliberately does **not** grant attendance access. Full ID templates and government IDs are not made public.

## Quick demo (no installation)

Open **`demo/index.html`** in a modern desktop browser. Drag and resize an element, switch sides, change field binding, font and color. Save design stores only design metadata in this browser's `localStorage`. Export JSON and print preview are optional. The QR shown in the canvas is an **illustrative placeholder**, not a working QR. No backend services are called from the demo.

To serve the demo over localhost instead of `file://` (recommended for local storage and browser APIs):

```bash
cd demo
python -m http.server 8080
# Browse http://localhost:8080
```

## Create the real standalone Laravel app

This package contains the **application overlay** rather than re-distributing Laravel framework dependencies. Install into a *fresh* Laravel 13 project; `laravel-overlay/routes/web.php`, `laravel-overlay/app/Models/User.php`, `laravel-overlay/resources/css/app.css` and `laravel-overlay/vite.config.js` replace the fresh defaults. **Never apply this overlay to your existing attendance/payroll project**: it has its own users/routes and must stay independent.

Requirements: PHP 8.3+, Composer 2, PHP extensions including XMLWriter / PDO / mbstring / fileinfo, Node.js 22+, npm, MySQL 8.4 (SQLite for development/testing).

### Windows PowerShell

```powershell
composer create-project laravel/laravel employee-id-studio "^13.0"
cd employee-id-studio
# From the unpacked ZIP: adjust this path to your actual location
Copy-Item "C:\path\to\employee-id-studio\laravel-overlay\*" . -Recurse -Force
composer require "livewire/livewire:^4.0" "bacon/bacon-qr-code:^3.0"
npm install -D "vite@^8" "laravel-vite-plugin@^3.1" "@tailwindcss/vite@^4" "tailwindcss@^4"
Copy-Item .env.example .env -ErrorAction SilentlyContinue
php artisan key:generate
# Edit .env DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate
php artisan studio:bootstrap "Example Company" admin@example.com
npm run build
php artisan serve
```

### Linux / macOS

```bash
composer create-project laravel/laravel employee-id-studio '^13.0'
cd employee-id-studio
cp -R /path/to/unpacked/employee-id-studio/laravel-overlay/. .
composer require 'livewire/livewire:^4.0' 'bacon/bacon-qr-code:^3.0'
npm install -D 'vite@^8' 'laravel-vite-plugin@^3.1' '@tailwindcss/vite@^4' 'tailwindcss@^4'
cp -n .env.example .env || true
php artisan key:generate
# Edit .env for your MySQL connection or use SQLite
php artisan migrate
php artisan studio:bootstrap 'Example Company' admin@example.com
npm run build
php artisan serve
```

The bootstrap command **prompts for a strong password on the server terminal**; no public registration and no default password are shipped.

Open `http://127.0.0.1:8000/login`, sign in, create employees/templates, publish a version, issue an ID and scan its QR to verify it. The app is only usable after installation of dependencies and migrations.

### Tests

```bash
php artisan test
npm run build
```

**Validation conducted while preparing this package:** PHP syntax checks of all PHP files and Node JavaScript syntax checks. **Not conducted:** Laravel application tests, live database migrations, dependency installation, browser E2E or GitHub Actions CI, because Composer/network dependency retrieval was unavailable in the authoring environment. These must pass before claiming deployed/production ready.

## Architecture

```text
Browser / Blade + Tailwind + Livewire-ready JS editor
           │ HTTPS + auth + CSRF + throttle
           ▼
      HTTP controllers
           │ Validation + scoped roles
           ▼
   TemplateLayout / CardFieldResolver / StudioAudit services
           │ DB transaction / optimistic draft revision / append-only audit
           ▼
       Eloquent → MySQL (company_id boundaries)
           │
        Local private file storage (photos, logos, backgrounds)

Future integration (NOT CONNECTED):
Attendance & Payroll ←→ versioned scoped HTTPS APIs ←→ ID Studio
                   company_uuid + external_employee_uuid
```

Important principles:

- Employee records are company-scoped; HR/admin writes, manager reads, viewer/employee have no management access.
- Government identifiers and emergency details are encrypted at rest via Laravel encrypted casts and never permitted in an ID design template by default.
- Template draft saves require the current revision; concurrent changes return HTTP 409. Publishing records immutable `studio_template_versions` snapshots.
- Each issued card pins a template version, field snapshot, photo path, expiry date, revocable 256-bit QR token. Existing card designs do not change after republishing a template.
- Public QR verification only returns validity, company name, employee name and code. An inactive employee, expired or revoked card becomes invalid.
- Assets are stored on the non-public `local` disk; access is authenticated and company-filtered. Old image files must be retained while issued-card snapshots reference them.
- No hard deletion of employee records, templates or card issues via UI. Audit stores actor/action/field **names only**, not values.
- `company_id` must always be derived from the **authenticated user**, never a browser-supplied parameter.

## System performance / Big-O

Let **N** = employees or issued cards per company; **K** = fields per ID side (bounded to 60); **P** = page size (15); **S** = bytes in an image; **Q** = QR grid dimension.

| Operation | Typical time | Additional application space | Why |
|---|---|---|---|
| Drag a selected field | **O(1)** per pointer movement | **O(1)** | Only the selected DOM element/style is updated; layers not rerendered until pointer-up. |
| Full editor redraw | **O(K log K)** | **O(K)** | Elements and layer list sort by z-index. |
| Validate and save a draft | **O(K)** | **O(K)** | All fields and boundaries validated, then JSON persisted. |
| Employee listing with indexed prefix/filter | **~O(log N + P)** | **O(P)** | B-tree index entry then 15-row pagination; actual plans depend on MySQL statistics and sorting. |
| Verify issued QR token | **~O(log N)** | **O(1)** | Unique indexed token hash lookup, plus employee/company lookups. |
| Render QR | **O(Q²)** | **O(Q²)** | QR modules are assembled; bounded by practical QR size. |
| Read uploaded background/photo | **O(S)** | streaming | Storage/HTTP response is proportional to file bytes. |

These are **algorithmic characterizations**, not benchmark results. Use `EXPLAIN`, load tests and production-like data for measured performance.

## What-if / operational decisions

See [docs/ARCHITECTURE_AND_EDGE_CASES.md](docs/ARCHITECTURE_AND_EDGE_CASES.md) and [docs/INTEGRATION_CONTRACT.md](docs/INTEGRATION_CONTRACT.md) for details on lost cards, edits, revocations, privacy and integration.

## GitHub portfolio

This is the independent `Knowlexit09/employee-id-studio` portfolio repository. The existing Attendance & Payroll repository is not modified. Source files in `laravel-overlay/` must be applied to a fresh Laravel project; the root of this GitHub repository is not a bootable Laravel installation. The browser demo in `demo/` runs separately and uses sample data only.

Suggested portfolio README disclaimer: **“Interactive template designer demo uses synthetic data. Backend security and integration are in development; not yet production certified.”**
