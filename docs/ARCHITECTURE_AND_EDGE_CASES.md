# Architecture decisions, threat model, and what-if cases

## Scope boundary

ID Studio is a **separate deployment**. It uses Laravel 13 / PHP 8.3+, Livewire 4 compatibility, Tailwind 4, Vite 8 and MySQL 8.4 in alignment with Attendance & Payroll. It does **not** alter payroll tax computation, BIR forms or attendance rules. Integration will be through explicit identities and APIs; neither app reads the other app's DB directly.

## Workflows

1. Trusted administrator provisions company and admin user using terminal command.
2. HR creates employee, including optionally pending government numbers, encrypted emergency contacts and private photo.
3. HR creates a template (card dimensions mm), customizes front/back, uploads JPG/PNG/WebP backgrounds/logo and places fields; saves draft.
4. HR reviews and publishes an immutable version.
5. HR chooses employee and published template, optional expiry, and issues card; system snapshots printable employee data/photo path, version and random token.
6. Print and save to PDF from browser at 100% scale. Print card backgrounds and disable headers/footers; calibrate physical printer before batches.
7. Public QR verifier shows limited identification, current validity. Revoke lost card (reason encrypted), and issue another. Inactive employees invalidate active QR automatically.

## Sensitive fields and privacy

- Internal registry: SSS, PhilHealth, Pag-IBIG MID, BIR TIN and emergency name/relationship/contact. All **stored encrypted**, not plain text.
- Public ID field binding allowlist does **not** include government numbers, emergency details, address, birthdays, medical information, or payroll details. Background/logo images may still *visually contain* sensitive text, so privacy review must address uploaded artwork before publication.
- Public verification displays only a limited set of fields and must never be indexed; QR encodes a random opaque HTTPS URL, not actual employee identifiers.
- Retention, deletion rights, labor-record preservation, privacy notice and access approvals require documented policy and legal review. Collect and print only what is necessary for the company's legitimate purpose.
- Employee photos are decoded and rewritten as private, bounded JPEGs (stripping source metadata); however background/logo assets are still uploaded without re-encoding. These assets, malware scanning, file lifecycle and parser exhaustion still require production review.
- App encryption keys must be backed up separately and rotated carefully; losing keys makes encrypted employee fields unreadable. Use an enterprise secrets manager.

## What if / edge cases

| Event | Present design / next action |
|---|---|
| Change layout tomorrow | Edit draft → save → publish new immutable version; previously issued cards keep their old version. |
| Employee transfers department or changes surname | Change employee data; old card snapshot remains unchanged. Revoke and issue a new card to show updated details. |
| Employee photo changes | Old photo path retained and pinned for already-issued cards; future issues use new photo. Needs lifecycle cleanup/retention policy. |
| Employee resigns | Change status to inactive; public QR becomes invalid immediately. Historical card remains available to authorized HR. |
| ID lost or stolen | HR revokes with reason and issues a new random token. Scanner returns invalid for old QR. |
| Card expires | Verification becomes invalid after configured date; HR can revoke/reissue. Date boundary must be aligned with company timezone policy before live use. |
| Two HR users edit same template | Compare draft revision; stale save fails with 409, preventing silent overwrite. |
| HR edits an existing published template | Draft edit is allowed, but publishing creates another version; it does not change existing versions. |
| Different physical ID sizes | Create another template with custom width/height in mm. After creation, card dimensions are fixed for that template in MVP. |
| Printer misalignment | Calibrate actual scale, background printing, trim and crop; no automated duplex registration or crop marks yet. |
| Logo/background replaced | Upload new immutable asset and link in new draft; published versions continue referencing old asset ID. |
| QR scanned offline | Verification requires internet and running server. A printed QR is not a cryptographic offline credential. |
| Many branches / companies | Every query restricts to authenticated company's ID. Manager is read-only in MVP. |
| Employee numbers reused | A compound unique index (`company_id`, `employee_code`) rejects duplicate codes within a company. |
| Two concurrent card issuances | Employee row locked, then active-issue query checked. Requires MySQL/SQLite concurrency test to certify race freedom. |
| Bad file uploads | Only JPEG/PNG/WebP, image dimensions and max size constrained; private storage and authorization required. Malware scanning/re-encoding is additional deployment gate. |
| User tries to print government IDs | The template schema rejects this binding even if request bypasses UI. Uploaded artwork also requires an organizational content review. |
| Template references another company's asset | Server checks the asset's company, template and type before draft save/publish. |
| Sensitive employee data leaked in logs | Audit only includes changed field names. Infra logs and backups must still be redacted and access-controlled. |
| Integration loses connectivity | Local registry remains independently usable, but sync conflict/queue/retry are not implemented yet. No silent overwrite. |

## Technical debt / production gates

1. Privileged MFA and verified invite/recovery flows; password change flows and secure account deprovisioning.
2. Real request authorization tests beyond included starter feature tests, CSRF/XSS/upload fuzzing, security reviews.
3. Malware-safe image re-encoding, EXIF removal, antivirus scanning, CSP, image retention/purge rules.
4. Verified MySQL 8.4 and SQLite migrations, PHP unit tests, frontend build, browser E2E, concurrency tests, QR decoding tests and printer calibration.
5. Formal privacy notice, retention schedule, protection impact assessment, encryption-key rotation process and backup restore drills.
6. Hosted HTTPS domain, secure-cookie config, trusted proxies, QR URLs with log-token redaction and rate limiting at edge.
7. Company timezone and template approval workflow before live issue; an independent reviewer may be needed under company policy.
8. Native batch PDF and high-resolution raster export, crop guides, calibrated duplex sheet imposition, batch issue/reporting, accessibility testing. The current bulk print page offers browser-based front/back cutouts for up to 24 already-issued, active IDs only.
9. Scanned ID is **not** sufficient authentication for attendance or payroll actions; any future attendance link must require an independent identity factor.

## Standards and references

- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- Laravel 13 Deployment: https://laravel.com/docs/13.x/deployment
- Laravel 13 Encrypted Casts: https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting
- NPC Advisory Opinion No. 2017-066 on company-ID contents: https://privacy.gov.ph/wp-content/uploads/2022/01/NPCAONo.2017-066.pdf

## Performance budgets (design estimates, not benchmark results)

Let **N** be the number of company card issues, **B** the number selected for batch print (max 24), **E** the number of visible template elements per card, and **A** the number of assets associated with selected templates.

- The active issue list uses tenant/status filters with pagination and eager-loaded employees/versions. Index-assisted page retrieval is approximately **O(log N + page size)** on a suitable composite index; query plans should be verified with `EXPLAIN`. Avoid offset pagination on very large datasets in favor of cursor pagination.
- Batch selection validates at most 24 IDs and retrieves card issues, template versions and assets in a bounded number of queries instead of one query per ID. Lookup and field rendering scale approximately **O(B · E + A)**, excluding QR/image encoding costs.
- HTML render memory is **O(B · E + A)**, bounded by the 24-card request limit; each QR is generated at a fixed resolution. Template geometry normalization is linear in the number of elements **O(E)**.
- Image decoding/re-encoding is **O(P)** time and space in pixel count **P**, bounded by the 2048×2048 input limit and the 1200-pixel maximum output side. Concurrency/load tests are required for production sizing.

## Privacy and revocation edge cases

- An empty protected-number edit means **retain existing**. Explicit clear toggles are validated and never mass-assigned; on create they are ignored.
- New employee photos are rewritten to new private JPEG paths, not replacing prior bytes, so already-issued card snapshots keep their prior photo. Retention or erasure rules must reconcile printed card records and employee privacy obligations.
- Batch selection excludes expired/revoked card issues and inactive employees, and rejects foreign-company selections atomically. It does not generate new tokens or mutate issuance records.
- Historical single-card printing marks invalid cards visually, but QR verification remains the authoritative live status. Printed output should not be treated as proof of employment or as attendance authentication.
- Duplex printing requires printer- and paper-specific alignment. This browser print preview **does not** guarantee back-side alignment.
