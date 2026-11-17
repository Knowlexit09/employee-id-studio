# Employee ID Studio

Standalone Laravel 13 employee ID designer and employee registry. **Portfolio/development use only — not production certified.** Separate from the Attendance & Payroll application; integration is designed but not implemented.

## Features

Employee records with encrypted statutory IDs and emergency contacts, customizable front/back ID design, draggable/resizable text/image/QR, millimeter geometry, immutable template versions, card issuance/revocation, QR verification, and audit records. The browser demo in `demo/` uses fictional data and shows an illustrative QR only.

## Local installation

Requirements: PHP 8.3+, Composer 2, Node.js 22+, npm and PDO SQLite/MySQL, mbstring, XML and fileinfo PHP extensions.

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

Check with `php artisan test` and `npm run build`. **Full dependency install, Laravel migrations and end-to-end tests have not yet been executed here.** Commit dependency lock files after generating them in a connected development environment. Do not use actual employee or payroll data until security hardening, MFA and deployment verification are completed.

See `docs/ARCHITECTURE_AND_EDGE_CASES.md` and `docs/INTEGRATION_CONTRACT.md`. No direct database sharing or live payroll sync exists.
