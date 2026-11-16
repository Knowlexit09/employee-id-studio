# Attendance & Payroll ↔ ID Studio: proposed contract (NOT IMPLEMENTED)

Both systems stay separate and use **different databases**. Their existing Laravel 13/PHP/Blade/Tailwind architecture stays consistent.

## Ownership and mapping

- Payroll owns attendance, compensation, payroll tax data and supervisor/branch authorization.
- Decide whether payroll or ID Studio owns master employee demographic details before enabling sync. Prefer payroll as authoritative when the company already registers staff in payroll.
- ID Studio stores `studio_companies.uuid`, `studio_employees.uuid`, and nullable `studio_employees.external_employee_uuid` to map identities without sharing auto-increment internal IDs.
- Never sync SSS / TIN / PhilHealth / Pag-IBIG or emergency contacts by default; restrict to explicitly approved fields.

## Proposed API endpoints (not shipped)

- `GET /api/v1/companies/{company_uuid}/employees?updated_since=...` — paginated sync feed, company-scoped token.
- `POST /api/v1/employee-links` — idempotent mapping/upsert with external UUID, external_version and changed_at.
- `GET /api/v1/issued-cards/{employee_uuid}/status` — minimal employee ID status without QR secret or government IDs.
- `POST /api/v1/events/card-revoked` — signed webhook with unique event UUID, replay window and retries.

Require OAuth2 confidential-client or signed service-to-service credentials, least-privilege scopes, TLS, short token lifetimes, rotation, audit and idempotency.

Example safe payload (synthetic):

```json
{
  "event_id": "68cb1d90-ccdd-4182-97e4-50da9e7d6ad0",
  "company_uuid": "9911b16e-513a-4599-9db0-df5e1226aa17",
  "external_employee_uuid": "7eaf4750-86c1-440f-8142-75a9f90cbb67",
  "employee_code": "EMP-001",
  "display_name": "Example Employee",
  "job_title": "Operations Associate",
  "status": "active",
  "source_version": 14
}
```

Version records, webhook attempts, conflict resolution and sync queues are next-phase work, not present in the current ZIP. Never treat ID QR possession as proof of attendance check-in.
