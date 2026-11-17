# Employee ID Studio

Standalone Laravel 13 employee ID designer and employee registry. **Portfolio/development use only — not production certified.** Separate from the Attendance & Payroll application; integration is designed but not implemented.

## Features

Employee records with encrypted statutory IDs and emergency contacts, customizable front/back ID design, draggable/resizable text/image/QR, millimeter geometry, immutable template versions, card issuance/revocation, QR verification, audit records, and bounded batch printing of up to 24 existing valid IDs. The browser demo in `demo/` uses fictional data and shows an illustrative QR only.

## Precision design tools

The production template editor supports drag/resize, 0.5 mm grid snapping, visual alignment guides for card edges/centers and neighboring fields, six align-to-card actions, locked layers, and four layer ordering commands. For finer positioning, focus the selected field and use **arrow keys for 0.1 mm** or **Shift + arrow keys for 1 mm**. Choose **Preview design** to temporarily hide editing handles (preview uses fictional placeholders rather than actual private employee information). Coordinates, font settings and z-order are saved with the template draft and become immutable when published.

From single or bulk print views, open **Print size calibration** to print an A4 gauge containing a **50 × 50 mm square** and a **100 mm bar**. Compare physical measurements with a ruler at 100% scale before printing cards. It is a manual calibration aid, **not** automatic duplex alignment or printer-driver control.

## Designer regression tests

```bash
npm install
npm run test:designer
npm run build
```

The Node tests cover movement bounds, alignments, layer ordering, and smart guides without requiring a browser. Laravel feature tests cover authorization and access to the printable gauge. Browser pointer/keyboard accessibility and printer alignment still require manual and end-to-end verification.

## Local installation

Requirements: PHP 8.3+, Composer 2, Node.js 22+, npm and PDO SQLite/MySQL, mbstring, XML, fileinfo and **GD** PHP extensions.

```bash
git clone https://github.com/Knowlexit09/employee-id-studio.git
cd employee-id-studio
composer install
cp .env.example .env
php artisan key:generate
# Create database/database.sqlite (or configure MySQL in .env)
php artisan migrate
php artisan studio:bootstrap "Portfolio Company" admin@example.com
npm install
npm run build
php artisan serve
```

Windows PowerShell uses `Copy-Item .env.example .env` and `New-Item database/database.sqlite -ItemType File -Force`. The bootstrap command prompts for a password; no default login is shipped. Visit http://127.0.0.1:8000/login after installation.

Check with `php artisan test` and `npm run build`. **GitHub CI executes migrations, feature tests and frontend builds against SQLite and MySQL; real-browser print calibration, security review and deployment have not yet been completed.** Commit dependency lock files after generating them in a connected development environment. Do not use actual employee or payroll data until security hardening, MFA and deployment verification are completed.

See `docs/ARCHITECTURE_AND_EDGE_CASES.md` and `docs/INTEGRATION_CONTRACT.md`. No direct database sharing or live payroll sync exists.

## Sensitive values and photos

SSS, PhilHealth, Pag-IBIG and TIN are encrypted at rest and never echoed back into the employee edit form. A blank update **retains** an existing number; use the dedicated clear checkbox to delete it. Employee photo uploads are bounded, decoded and re-encoded as JPEG (EXIF and original metadata removed) on private local storage. Prior employee photo files remain pinned to already-issued ID snapshots; implement and document retention rules before production use. Background and logo uploads are separate assets and still require content-security review.

## Printing existing IDs in batches

Navigate to **Bulk Print**, select up to 24 active, unexpired cards for the same company, then preview the card fronts and backs at physical millimeter dimensions. Bulk print reuses the originally issued QR verification links and immutable template versions; it does not issue any new credential. **The front/back cutouts are not automatically duplex-aligned.** Print at 100% with headers/footers disabled, calibrate your printer, and use larger paper for oversized templates. Historical revoked/invalid single-card reprints carry a visible INVALID overlay.
