# Phase 9 security audit report

This was a source review and local defensive verification of the repository. Production readiness remains unverified.

## Checks and evidence

| Area | Reviewed or executed evidence |
| --- | --- |
| Authentication, CSRF and rate limits | Feature tests cover signed email verification, single-use reset, session invalidation, suspended accounts, normalized account login throttling, and CSRF rejection for an authentication mutation. CORS allowlist behavior was exercised. |
| Authorization and record ownership | Feature tests exercise cross-account donation and case denial, shop membership and verification, protected administrator finance and CSV, private evidence access, and separate payout approval. |
| SQL injection, mass assignment and XSS | Reviewed bound search predicates, server-owned role and financial fields, request validation, and React text rendering. The user search test covers literal wildcard filtering. Registration and profile tests cover attempts to set protected attributes. |
| Uploads and private evidence | Reviewed MIME and size validation, private storage, scan-gated availability, attachment delivery and case/shop policy checks. Automated scanner assertions used process fixtures. |
| Webhook authenticity and duplicate handling | Exercised unsigned and stale request rejection, amount validation, authenticated replay, and one receipt for repeat confirmations. Reviewed local/testing simulator gating. |
| Financial state and audit | PostgreSQL allocation races, settlement role restrictions, ledger/audit protections, and compensation flows were exercised. See the [financial integrity report](financial-integrity-report.md). |
| Dependencies | Composer and npm advisory checks reported no known advisories for the installed dependency versions at execution time. |

## Findings and release gates

| Severity | Finding | Status |
| --- | --- | --- |
| Critical | No critical defect was confirmed by the executed local checks. | This result is limited to the tested scope. |
| High | Shop response ordering could select an older response when workers generated UUIDs within the same clock interval. | Fixed. Reconciliation and reporting read an explicit response pointer written under the financial lock. |
| High | Privileged finance currently uses current-password confirmation. Deployment-level MFA assurance, least-privilege runtime database grants and independent review are required before live operation. | Open release gate. |
| Medium | Real scanner execution, signature freshness, hostile-file handling at the deployed reverse proxy and private-store isolation were unverified. | Open. Run staging validation with the configured scanner and storage. |
| Medium | Edge, TLS, headers and cookie settings were reviewed as configuration. The deployment environment, backups, and alert delivery were untested. | Open. Verify in staging. |

The audit used local PHPUnit, frontend component tests, source inspection and dependency checks. It did not include a hosted penetration test, a live provider, production secrets or production data.
