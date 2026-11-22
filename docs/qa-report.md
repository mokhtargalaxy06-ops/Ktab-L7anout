# Phase 9 QA report

Reviewed the Laravel API, React client, policies, workflows, schema, deployment configuration, and existing automated suites against the repository instructions. Test data was synthetic. Financial checks used the local simulator.

## Executed checks

| Check | Result |
| --- | --- |
| PostgreSQL PHPUnit and migration | 44 passed, 690 assertions, with the multi-process funding test. The response-head migration applied on the dedicated PostgreSQL fixture database. |
| SQLite PHPUnit | 43 passed, 675 assertions. The PostgreSQL-only concurrency test was skipped in this run and executed in the PostgreSQL run. |
| PHP formatting and Composer manifest | Pint and strict Composer validation passed. The repository has no PHPStan or Psalm configuration. |
| Frontend static checks and build | Oxlint and TypeScript checks passed. The Vite production build completed with a chunk-size warning. |
| Frontend Vitest | 21 passed across 7 files. |
| Headless Edge viewport checks | 24 passed. Checked the production build at 360, 768 and 1440 CSS pixels on the homepage, sign-in, registration and case listing, in French and Arabic. Used loopback API fixtures and asserted heading rendering, language, direction and absence of horizontal overflow. |
| Dependency advisories | Composer and npm reported zero advisories during the runs. |

## Coverage

- Backend feature tests exercised registration, login, logout, password reset, email verification, session revocation, role permissions, case applications, scanned evidence access, shop verification, case approval and publication, public search, donation intent and funding restrictions, signed payment confirmation, webhook replay, refunds, reversals, settlement approvals and disputes, exports, and administrative reporting.
- PostgreSQL used separate processes to exercise concurrent donation creation and allocation. Simulated settlement approval and disputes were covered by integration tests.
- Frontend component tests exercise French and Arabic RTL, registration validation and role choices, sign-in and protected routes, discovery filtering and pagination, case detail, donation form and payment failure, donor history, administrator navigation, and shop settlement display. API responses in these tests are controlled fixtures.
- See the [security audit](security-audit-report.md) and [financial integrity report](financial-integrity-report.md) for the scope and environment limits.

## Findings

| Severity | Finding | Disposition |
| --- | --- | --- |
| Medium | The French homepage overflowed horizontally at a 768 px viewport. | Hero columns now allow their contents to shrink. The browser rerun passed. |
| Low | The main frontend chunk crosses Vite's default 500 kB warning threshold. | Build succeeds. Track bundle size during performance work. |

Hosted browser-to-API journeys, physical devices, assistive technology, and performance testing remain outside these checks.
