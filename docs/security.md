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

Login limits apply per source IP and normalized account. Registration and password recovery have separate limits. Private account APIs require a current, active Sanctum session. Password reset and account status changes delete stored sessions and increment the account session version, which also rejects a stale session recreated by an in-flight request. Signed email verification links expire after 60 minutes. Password recovery requests enqueue encrypted jobs so mail delivery time stays outside the HTTP response. Reset links use a frontend URL fragment so the token stays out of the initial page request. Use SMTP for authentication messages.

The administrator provisioning command is available to trusted server operators. It provisions new accounts only, prompts for the password without echo, and writes an audit record. Account administration requires a verified administrator, and status changes also require the administrator's current password and are rate-limited per administrator. Administrator targets are excluded from web status management. Verification redirects suppress the referrer and are excluded from caching. Financial and role-change administration require additional controls when implemented.

## Evidence and privacy

Quarantine uploads under random private object keys. Validate size, extension, detected media type, and malware scan results before marking documents available. Serve authorized downloads using a checked stream or a short-lived signed URL and record access. Use attachment disposition and avoid active inline rendering. Restrict service accounts, database roles, and bucket permissions. Encrypt transport and storage, including backups. Avoid logging credentials, personal data, raw callback bodies, or signed URLs.

Use redacted localized case summaries, coarse geography, and opaque public IDs. Restrict addresses, identifying information, settlement destination details, and original evidence. Review publication for indirect identification, especially in small communities. Establish retention and deletion requirements before onboarding real people. Purge eligible evidence objects and minimize personal fields without modifying financial facts or destroying audit links.

Authorize settlement evidence through the specific payout and bind the document to it at upload. A file alone cannot confirm a transfer. Require an independently verified transfer and available evidence bound to that payout before recording evidenced settlement.

Shop evidence uses private storage, random file names, allowlisted detected media types, and a 5 MiB upload limit. The ClamAV scan command checks the digest and rejects files that trigger encryption or scan-limit alerts. Unreadable files and incomplete scans stay quarantined while the batch continues. Verified account owners of the shop and verified administrators can download clean files through shop-scoped authorization. Administrator review requires clean evidence from the profile revision being reviewed. Profile changes restart verification. [ClamAV scanning documentation](https://docs.clamav.net/manual/Usage/Scanning.html)

Debt application drafts are visible only to their applicants. Assigned shop owners receive the debt reference, date, and amount. Beneficiary narratives, household information, and beneficiary evidence stay private to applicants and authorized reviewers. Public discovery uses separate random references, city and region, reviewer-selected generic story templates, funding totals, and allowlisted dated updates. It omits shop details, private narratives, grades, and review reasons. Administrator views, review decisions, and evidence downloads write audit entries without document contents or private narratives.

## Financial integrity and operations

Authenticate simulator callbacks over the raw bytes with a timestamped signature and constant-time comparison. Enforce replay windows, deduplicate by event ID plus payload digest, and check business-level capture and operation identities separately. Quarantine conflicting event IDs. Keep callback secrets separate from user credentials and support rotation. API idempotency binds a key to the actor, operation, and canonical request hash. Replaying a key with a changed payload yields a conflict. Recheck current authorization before returning stored results.

Protect the immutable journal with a separate migration role, limited application grants, append-only enforcement, and audit-log export to restricted storage. Reconcile receipts, payout holds, shop settlement evidence, and returns with simulator records. Alert on anomalous changes, unmatched money movements, repeated signature failures, stuck operations, and freezes. Test backup restoration and administrative incident response.

The MVP assumes authenticated simulation and synthetic fixtures. Insider abuse, document re-identification, and provider outages remain risks. Approval of the legal structure, privacy and retention rules, and payment provider is required before live use. Provider signature formats, card-data handling, settlement controls, and recovery semantics require separate validation.

## Phase 7 simulator controls

Settlement mutations require an active, verified account. Administrator commands require current-password verification, and a different administrator must submit and reconcile a payout from the one who initiated it. Only the current active owner of the assigned shop can confirm or dispute receipt. Shop owners cannot exercise administrator settlement commands on their own shops. Payout initiators and approvers remain ineligible to respond as shopkeepers if they later become owners. The server generates private simulator evidence and the transfer reference. Public reporting exposes aggregates. Administrator CSVs omit free-text reasons, credentials, evidence content and personal details. Setup for production MFA, evidence intake, and provider reconciliation remains subject to the approved implementation.

## Phase 8 administrative audit and authorization

Administrator pages rely on Laravel authorization at each endpoint. Financial searches expose operational IDs and amounts. Audit responses expose the recorded actor, affected entity, timestamps, and an allowlist of change details. Account status changes and manually simulated returns require current-password verification and a reason. Case changes, shop reviews, settlement commands, and geography changes carry their state changes into the append-only audit history. Geography writes require a reason and commit with their audit event under the financial lock. Document access continues to use the existing private evidence workflows.
