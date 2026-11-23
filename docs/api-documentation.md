# REST API contract

Use JSON under `/api/v1` and HTTPS. Keep Sanctum's CSRF bootstrap at `GET /sanctum/csrf-cookie`, then use `POST /login` and `POST /logout` for first-party session authentication. Registration, email verification, and password reset use Laravel authentication flows. Axios sends same-origin cookies and the XSRF header. Send `Accept: application/json` and use `Accept-Language` with French as fallback for unsupported locales. Use Sanctum and Policies for protected routes. [Laravel Sanctum documentation](https://laravel.com/framework/docs/sanctum)

## External receipts for WhatsApp pledges

Authenticated shopkeepers use `GET /api/v1/shop/external-payments` to find pledges for their shops. They submit one receipt per pledge with `POST /api/v1/shop/external-payments/{pledge}` as multipart form data containing `amount_minor` (a positive MAD amount in centimes), `reference` and `evidence` (PDF, JPEG or PNG, up to 5 MiB).

Successful shop confirmation credits the debt in the same transaction. A duplicate reference or unsafe reduction of debt capacity returns HTTP 409. Authorized finance administrators can list confirmations with `GET /api/v1/admin/external-payments` and download the attachment from `GET /api/v1/admin/external-payments/{pledge}/evidence`. Previously pending declarations can still be decided using `PATCH /api/v1/admin/external-payments/{pledge}` with `decision`, `reason` and the administrator's current `password`. The donor detail exposes the receipt status and amount.

## Conventions

- Resources return `data`. Paginated collections add `links` and `meta`. Use opaque cursors, stable sort order, and bounded page sizes.
- Use UUIDs for IDs, UTC ISO 8601 instants for timestamps, and `YYYY-MM-DD` for debt dates. Represent money as base-10 integer strings in MAD minor units and include `currency: "MAD"` in financial resources. Accept bounded positive integer strings for requested amounts.
- Errors return `code`, localized `message`, optional field `errors`, and `request_id`. Use 401 for missing authentication, 403 for denied actions, 404 for absent or concealed resources, 409 for state or idempotency conflicts, 419 for expired CSRF, 422 for validation, and 429 for rate limits.
- Require `Idempotency-Key` on financial commands. Bind it to the actor, operation including target, and canonical payload. Return the same accepted outcome on equivalent retries. Use a fresh key for a different operation. State transitions without a financial command take explicit actions and report conflicts for invalid states.
- Return 201 for created resources, 202 for accepted asynchronous commands, 200 for reads or synchronous updates, and 204 for successful commands without a response body.

## Endpoints

The current release exposes `GET /api/health` for liveness and the following account endpoints. Fetch the Sanctum CSRF cookie before mutations. A user resource includes `id`, `name`, `email`, `locale`, `roles`, `status`, `email_verified`, and `abilities`.

| Method and path | Behavior |
| --- | --- |
| `POST /register` | Accepts name, email, password, password confirmation, locale and optional role. Public roles are donor, beneficiary, and shopkeeper. Defaults to donor. Starts a session and returns 201 |
| `POST /login`, `POST /logout` | Sign in an active account with email and password for a 200 user resource. Logout invalidates the current session and returns 204 |
| `GET /api/v1/me`, `PATCH /api/v1/me` | Read or update the signed-in active user's profile. The only writable fields are name and locale |
| `POST /forgot-password` | Accepts email and returns 204 for both known and unknown addresses |
| `POST /reset-password` | Accepts email, token, password, and password confirmation. A successful one-time reset revokes prior sessions and returns 204 |
| `POST /api/v1/email/verification-notification` | Resend to the active account's own email. Returns 204 on success |
| `GET /email/verify/{id}/{hash}` | Follow a time-limited signed link to verify the address and redirect to the frontend |
| `GET /api/v1/admin/users` | Cursor-paginated account review for verified administrators |
| `PATCH /api/v1/admin/users/{user}/status` | Verified administrator submits status and their own current password. Admin targets are protected. Status changes revoke prior sessions and emit an audit event. Closed accounts are terminal |

### Geography and shop API

| Method and path | Access and behavior |
| --- | --- |
| `GET /api/v1/regions`, `GET /api/v1/cities?region_id=…`, `GET /api/v1/localities?city_id=…` | Public active geography, with French and Arabic names |
| `GET, POST /api/v1/admin/geography/{level}`, `PATCH /api/v1/admin/geography/{level}/{id}` | Verified administrator. Levels are regions, cities, and localities. Child reads require the corresponding parent filter. Creation requires a source URL, source date, code, names, active flag, and parent for children. Edits preserve IDs, codes and parents |
| `GET, POST /api/v1/shop/shops`, `PATCH /api/v1/shop/shops/{shop}` | Verified shopkeeper. List owned shops or save a profile with name, location, and contact information |
| `GET /api/v1/admin/shops`, `GET /api/v1/shops/{shop}` | Administrator review list. Shop detail is restricted to its owner or a verified administrator |
| `POST /api/v1/shop/shops/{shop}/documents`, `GET /api/v1/shops/{shop}/documents/{document}` | Owners upload to quarantine. Owners and administrators may download scanned evidence as an attachment |
| `POST /api/v1/admin/shops/{shop}/review` | Administrator submits decision, current revision, and a reason for rejection or suspension |
| `GET /api/v1/shops/{shop}/debt-requests` | Owner or administrator lists the shop's requests |
| `POST /api/v1/admin/shops/{shop}/debt-requests` | Administrator submits reference and integer-string `amount_minor` for a verified shop |
| `POST /api/v1/shop/shops/{shop}/debt-requests/{debt}/decision` | Owner of a verified shop confirms the stated amount or disputes it with a reason |

Shop and debt inbox lists are cursor-paginated. Debt application submission and multipart evidence uploads require the displayed case revision. Stale revisions return 409.

### Debt applications

| Path under `/api/v1` | Behavior |
| --- | --- |
| `GET /cases` | Public catalog, 12 cases per numbered page. Filters are `q` (public UUID, region or city names), `region_id`, `city_id`, `min_minor`, `max_minor`, `urgent=1`, `status=open\|funded\|settled`, and `page`. Returns public identifiers, bilingual city and region names, story key, verification and funding data |
| `GET /cases/{reference}` | Public detail by anonymous UUID. Adds dated, allowlisted verification and funding updates without private review notes |
| `GET /discovery` | Homepage totals, up to three recently published and three urgent cases, and active urgency settings |
| `POST /admin/cases/{case}/discovery` | Verified reviewer selects a predefined story, records vulnerability and access-risk grades between zero and four, and submits a case revision and internal rationale. The public API omits both grades and rationale |
| `GET /beneficiary/shops?city_id=…` | List selectable verified shops by city for an eligible beneficiary |
| `GET, POST /beneficiary/cases`, `GET, PATCH /beneficiary/cases/{case}` | Owner list and detail, create or edit a draft. Supply city, shop, debt reference/date, amount string, situation, and optional household size |
| `POST /beneficiary/cases/{case}/submit`, `POST /beneficiary/cases/{case}/cancel` | Submit with scanned current evidence and `consent: true`, `consent_version: "1"`, or cancel an eligible case |
| `GET /shop/cases`, `GET /shop/cases/{case}`, `POST /shop/cases/{case}/verification` | Assigned verified-shop owners see limited data. Post current revision and decision `confirm`, `dispute`, or `reject`, with confirmed amount or appropriate reason |
| `GET /admin/cases`, `GET /admin/cases/{case}`, `POST /admin/cases/{case}/review` | Review submitted cases and history. Post current revision and decision `route`, `approve`, `reject`, `request_info`, `flag`, `clear_flag`, or `publish` |
| `POST /{audience}/cases/{case}/documents`, `GET /{audience}/cases/{case}/documents/{document}` | Multipart uploads for beneficiary drafts or shop verification. Clean attachments only. Beneficiaries and shopkeepers access evidence from their own audience. Administrators can download both audiences after submission |

The remaining case and financial endpoints below describe the planned contract.

Paths in the table are relative to `/api/v1`.

| Method and path | Access | Input and behavior |
| --- | --- | --- |
| `GET /me` | Signed-in user | Own account, locale, roles, and UI abilities |
| `GET /regions`, `GET /cities`, `GET /localities` | Public | Localized reference data, with parent filters on cities and localities |
| `GET /donation-policies/current` | Public | Published policy version, excess and pool handling, and localized text |
| `GET /beneficiary/profile`, `PUT /beneficiary/profile` | Beneficiary | Own profile. Upsert approved profile fields |
| `GET /beneficiary/shops` | Beneficiary | Search verified shops by locality using public display fields |
| `GET /beneficiary/cases`, `POST /beneficiary/cases` | Beneficiary | Own case list and draft creation. Supply shop ID, debt reference, and origination date |
| `GET /beneficiary/cases/{id}`, `PATCH /beneficiary/cases/{id}` | Case owner | Own private case detail and allowed draft edits |
| `POST /beneficiary/cases/{id}/documents` | Case owner | Multipart evidence upload. Returns quarantined document resource |
| `POST /beneficiary/cases/{id}/submit` | Case owner | Submit a complete draft for shop confirmation |
| `GET /documents/{id}/download` | Policy-authorized user | Checked download or short-lived redirect |
| `GET /shop/cases`, `GET /shop/cases/{id}`, `GET /shop/payments` | Active shop member | Assigned cases and payout progress. Filter by authorized shop |
| `POST /shop/cases/{id}/verification` | Active shop member | Confirm or dispute. Supply reviewed balance for confirmation and supporting reason/evidence |
| `GET /admin/cases`, `GET /admin/cases/{id}` | Case reviewer | Scoped review queue and private case detail |
| `POST /admin/cases/{id}/review` | Case reviewer | Approve, reject, pause, request reverification, or close. Supply reason, cap and evidence as applicable |
| `POST /admin/cases/{id}/publish` | Publisher | Publish approved case with reviewed localized summaries |
| `POST /admin/cases/{id}/assessments` | Case assessor | Record human-reviewed grades and rationale |
| `POST /admin/urgency-policies` | Policy administrator | Publish weights, thresholds, and effective time |
| `POST /admin/donation-policies` | Policy administrator | Publish approved French and Arabic text and effective excess and pool rules |
| `POST /admin/shops`, `POST /admin/shops/{id}/review` | Shop reviewer | Create a shop or record verification, suspension, closure, or destination change with evidence |
| `POST /admin/shops/{id}/memberships`, `DELETE /admin/shops/{id}/memberships/{userId}` | Access administrator | Grant or revoke a shop association and audit the actor |
| `POST /donations`, `GET /donations`, `GET /donations/{id}` | Donor | Create intent and first simulated payment attempt, or fetch own status and impact |
| `POST /webhooks/payments/simulator` | Authenticated simulator | Accept a signed event durably. Respond 202 for accepted or already received identical events. No browser session |
| `POST /admin/cases/{id}/shop-payments` | Settlement initiator | Request payout for amount using server-validated allocation lines |
| `GET /admin/shop-payments/{id}` | Settlement operator | Transferred amount, evidence status, and outstanding action. `evidence_pending` indicates a verified transfer awaiting evidence |
| `POST /admin/shop-payments/{id}/approve` | Distinct settlement approver | Approve an existing request and enqueue submission |
| `POST /admin/shop-payments/{id}/evidence` | Settlement operator | Upload private evidence bound to this payout. Evidenced settlement requires a verified transfer and available evidence |
| `POST /admin/shop-payments/{id}/cancel` | Settlement operator | Cancel only before the worker claims submission. After a claim, use reconciliation |
| `POST /admin/payment-transactions/{id}/refunds` | Refund operator | Create voluntary return with amount and policy reason. No arbitrary destination |
| `GET /admin/reconciliation` | Reconciliation operator | List capture, payout, return, and balance exceptions |
| `POST /admin/reconciliation/reviews` | Reconciliation operator | Record evidence and request reprocessing or resolution through the corresponding domain service |
| `GET /notifications`, `POST /notifications/{id}/read` | Recipient | Own notification list and read state |

## Donation example

Phase 6 exposes `GET /donation-policies/current`, `GET /donations`, `POST /donations`, and `GET /donations/{donation}` under `/api/v1`. The authenticated donor creates an intent with `scope`, `amount_minor`, `currency`, `policy_version`, and `case_reference` (the public case UUID, only for specific donations). POST creation and administrator return simulation require `Idempotency-Key`. A repeat with identical parameters returns the existing intent. Changed parameters with the same key return 409. History is cursor-paginated and scoped to the donor. Detail separates captured, allocated, unallocated, reserved return, and confirmed return amounts, and shows per-allocation shop settlement states.

In enabled local or test environments, `POST /donations/{donation}/simulation` accepts `success`, `pending`, `failed`, or `cancelled`. It always uses the server-recorded amount and MAD. Verified administrators can use `POST /admin/donations/{donation}/simulate-return` with `kind=refund|reversal` and a positive integer-string `amount_minor`. There is no shop payout dispatch endpoint in Phase 6.

The simulator webhook at `POST /webhooks/payments/simulator` accepts `event_id`, `reference`, `outcome`, `amount_minor`, and `currency`. Sign the exact raw body as HMAC-SHA256 over the Unix timestamp, a period, and the body, using `FUNDING_SIMULATOR_SECRET`. Send the timestamp and hex signature via `X-Simulator-Timestamp` and `X-Simulator-Signature`. Requests outside a five-minute window are denied. Replaying the same event is harmless, and conflicting event payloads return 409.

`POST /api/v1/donations` with an idempotency key accepts the following body. Use `urgent_pool` without `debt_case_id` for pooled giving.

```json
{
  "scope": "specific",
  "case_reference": "df006b8f-3b0f-4a84-85ab-8468a20432c8",
  "amount_minor": "25000",
  "currency": "MAD",
  "policy_version": "mvp-1"
}
```

A 201 response wraps the new intent in `data` and includes its identifier, `payment_status`, `allocation_status`, amount, scope, policy version, and simulator marker. Stale policy versions return 409. Browser navigation to a result page does not confirm payment.

The donation detail returns payment status `pending`, `confirmed`, `failed`, or `cancelled` and a separate allocation status. It includes the captured, allocated, and returned amounts and redacted allocation entries with public case references and shop settlement lines. Simulated returns can be `requested`, `confirmed`, or `canceled`. These amounts describe different stages. Historical settlements remain visible after reversals. Internal payment references, evidence, and beneficiary identity stay private.

## Callback and contract boundaries

For simulation, use a signed envelope carrying adapter event ID, operation reference, event type, amount, currency, and occurrence timestamp. Verify the signed raw payload and timestamp before writing to the inbox. Duplicate submissions with mismatched payloads enter reconciliation and return 409. Resolve late and out-of-order events using verified operation outcomes. Payout, allocation, and retry jobs have no public trigger. See the [state and money rules](business-rules.md), [database statuses](database-design.md), and [security requirements](security.md).

Document the exact DTOs, error codes, limits, and simulation envelope as OpenAPI when implementing this contract. Live payment contracts and real-provider actions are deliberately pending provider approval and capability verification.

## Phase 7 settlement and transparency API

All paths below are relative to `/api/v1`. The public `GET /transparency` returns aggregate money as decimal strings in MAD minor units, counts, a simulation flag, and regional settled amounts and counts. It exposes no household, donor, shop or payout identifiers.

| Method and path | Contract |
| --- | --- |
| GET /admin/settlements | Paginated queued cases and payout attempts. Optional status filter includes `dispute`. |
| GET /shop/settlements | Paginated approved or published debts for actively owned shops, with funding and settlement progress. |
| POST /admin/settlements/{case} | `initiate`, returning 204 and reserving the case's complete allocation. |
| POST /admin/settlements/{case}/{payment} | `submit`, `transfer`, `fail`, `reconcile`, or `recover`. |
| POST /shop/settlements/{case}/{payment} | `confirm` or `dispute` for the assigned shop. |
| GET /admin/finance | Journal, incoming payment attempts, and returns with independent `journal` `returns`, and `receipts` cursors, freeze state, and up to 25 deficit rows. |
| GET /admin/finance/journal.csv | All monetary events with receipt, allocation, return, and payout keys. |
| GET /admin/finance/reconciliation.csv | Per-receipt balances computed from those same monetary events. |

Private endpoints use the existing authenticated session and are available when simulation is enabled. Mutations require an `Idempotency-Key` and a JSON `action`. Administrator commands require the current `password`. A `reason` is required for failures and disputes. Authorized repeats of the same command return 204. Mismatched replays and disallowed transitions return 409. Payout confirmation leaves the case unsettled until administrator reconciliation.

## Phase 8 administrator workspace

The following verified-administrator endpoints are under `/api/v1/admin`. The overview and financial search also require the existing financial review ability.

| Endpoint | Query and response |
| --- | --- |
| GET /overview | Account, case, shop and settlement counts, and confirmed donation minor units. The response indicates simulation mode. |
| GET /financial-search/{kind} | Paged donations, allocations, transactions, or refunds. Optional exact UUID filters `id` and `related_id`. The related ID is the case for donations and the receipt for other types. |
| GET /regions/activity | Regional totals and paginated city activity. Filter by `region_id` and `coverage=uncovered`. |
| GET /audit | Paged audit events, optionally filtered by `actor_id` and `target_id`. Only approved metadata fields are returned. |

The user list accepts `q`, `role` and `status`. Case lists accept `region_id`, `city_id` and `status`, and shop lists accept `city_id` and `status`. Account status changes, geography changes, and manual simulated returns require a reason. Returns also require current-password verification and an idempotency key. The existing case review endpoint accepts `unpublish`, `suspend` and `reopen_dispute`, each with the current revision and a reason.

## Account contact details

Public registration accepts a required phone and optional whatsapp_contact consent. Profile updates accept both fields, and clearing the phone disables WhatsApp contact. Existing accounts can add a number in their profile. The API normalizes Moroccan local numbers and international numbers into a plus-prefixed format. Only the account owner and authorized administrators can see this contact data. The administrator interface shows a chat link only for opted-in accounts.

The shopkeeper dashboard opens the shops returned by GET /api/v1/shop/shops directly. This paginated endpoint returns the signed-in owner’s shops, most recently created first, with location and evidence metadata.

## Donor access to shop evidence

Administrators download originals through the existing authenticated shop endpoint. An administrator can POST a separate file to /api/v1/admin/shops/{shop}/documents/{document}/redacted and PATCH /api/v1/admin/shops/{shop}/documents/{document}/sharing with a boolean share. Only scanned redacted copies for the current approved shop revision can be shared. Signed-in donors can list and download copies from /api/v1/donor/cases/{reference}/shop-evidence and its document subroute for a published eligible case.
