# Security design

The trust boundaries are the browser, Laravel, PostgreSQL, private evidence storage, and the simulated payment adapter. Treat browser data, uploaded documents, queued messages, and incoming callbacks as untrusted. Apply Laravel Form Requests and Policies at the API boundary and recheck relevant invariants in services and jobs.

## Identity and access

| Role | Resource access |
| --- | --- |
| Visitor | Geography, approved public policy text, and redacted published cases |
| Donor | Own intentions, receipts, redacted allocation impact, returns, and notifications |
| Beneficiary | Own profile, cases, and authorized evidence. May submit cases and check review status |
| Shopkeeper | Cases and payouts for shops where the user has active membership. Access only evidence specifically required for confirmation |
| Administrator | Explicit abilities for review, publication, settlement, authorization administration, and reconciliation. Restrict actions and evidence to operational need |

Use Sanctum first-party session cookies and CSRF protection. Use TLS, secure session cookies with HttpOnly and an appropriate SameSite setting, session rotation on login, and session invalidation on logout, suspension, or security-sensitive privilege changes. The CSRF token cookie remains readable for Axios. Disable cross-origin credentialed access by default. Rate-limit login, registration, reset, document, donation, and payment routes. Require email verification for sensitive user actions and MFA and recent authentication for privileged financial and role changes. Audit grants and revocations. Block self-approval of shop payouts. [Laravel Sanctum documentation](https://laravel.com/framework/docs/sanctum)

Use parameterized queries and allowlist sort and filter inputs. Prevent mass assignment with explicit input-to-field mappings. Build API Resources with allowlisted fields per audience. Use React's escaped rendering, avoid raw HTML, deploy a restrictive CSP, and set security headers. Derive prices, balances, roles, and ownership from trusted server data. Keep secrets in environment variables supplied through secure deployment configuration and never commit credentials or .env files.

In Phase 1, login limits apply per source IP and normalized account, and registration uses a separate source-IP limit. The current-account API requires Sanctum and rejects inactive users through shared middleware. The foundation exposes no account suspension or role-change workflow. Revoking every existing session at the time of an administrative change belongs with those workflows.

## Evidence and privacy

Quarantine uploads under random private object keys. Validate size, extension, detected media type, and malware scan results before marking documents available. Serve authorized downloads using a checked stream or a short-lived signed URL and record access. Use attachment disposition and avoid active inline rendering. Restrict service accounts, database roles, and bucket permissions. Encrypt transport and storage, including backups. Avoid logging credentials, personal data, raw callback bodies, or signed URLs.

Use redacted localized case summaries, coarse geography, and opaque public IDs. Restrict addresses, identifying information, settlement destination details, and original evidence. Review publication for indirect identification, especially in small communities. Establish retention and deletion requirements before onboarding real people. Purge eligible evidence objects and minimize personal fields without modifying financial facts or destroying audit links.

Authorize settlement evidence through the specific payout and bind the document to it at upload. A file alone cannot confirm a transfer. Require an independently verified transfer and available evidence bound to that payout before recording evidenced settlement.

## Financial integrity and operations

Authenticate simulator callbacks over the raw bytes with a timestamped signature and constant-time comparison. Enforce replay windows, deduplicate by event ID plus payload digest, and check business-level capture and operation identities separately. Quarantine conflicting event IDs. Keep callback secrets separate from user credentials and support rotation. API idempotency binds a key to the actor, operation, and canonical request hash. Replaying a key with a changed payload yields a conflict. Recheck current authorization before returning stored results.

Protect the immutable journal with a separate migration role, limited application grants, append-only enforcement, and audit-log export to restricted storage. Reconcile receipts, payout holds, shop settlement evidence, and returns with simulator records. Alert on anomalous changes, unmatched money movements, repeated signature failures, stuck operations, and freezes. Test backup restoration and administrative incident response.

The MVP assumes authenticated simulation and synthetic fixtures. Insider abuse, document re-identification, and provider outages remain risks. Approval of the legal structure, privacy and retention rules, and payment provider is required before live use. Provider signature formats, card-data handling, settlement controls, and recovery semantics require separate validation.
