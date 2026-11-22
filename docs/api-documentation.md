# REST API contract

Use JSON under `/api/v1` and HTTPS. Keep Sanctum's CSRF bootstrap at `GET /sanctum/csrf-cookie`, then use `POST /login` and `POST /logout` for first-party session authentication. Registration, email verification, and password reset use Laravel authentication flows. Axios sends same-origin cookies and the XSRF header. Send `Accept: application/json` and use `Accept-Language` with French as fallback for unsupported locales. Use Sanctum and Policies for protected routes. [Laravel Sanctum documentation](https://laravel.com/framework/docs/sanctum)

## Conventions

- Resources return `data`. Paginated collections add `links` and `meta`. Use opaque cursors, stable sort order, and bounded page sizes.
- Use UUIDs for IDs, UTC ISO 8601 instants for timestamps, and `YYYY-MM-DD` for debt dates. Represent money as base-10 integer strings in MAD minor units and include `currency: "MAD"` in financial resources. Accept bounded positive integer strings for requested amounts.
- Errors return `code`, localized `message`, optional field `errors`, and `request_id`. Use 401 for missing authentication, 403 for denied actions, 404 for absent or concealed resources, 409 for state or idempotency conflicts, 419 for expired CSRF, 422 for validation, and 429 for rate limits.
- Require `Idempotency-Key` on financial commands. Bind it to the actor, operation including target, and canonical payload. Return the same accepted outcome on equivalent retries. Use a fresh key for a different operation. State transitions without a financial command take explicit actions and report conflicts for invalid states.
- Return 201 for created resources, 202 for accepted asynchronous commands, 200 for reads or synchronous updates, and 204 for successful commands without a response body.

## Endpoints

The foundation exposes `GET /api/health` for liveness and `GET /api/v1/me` for the current active session. `POST /register` accepts name, email, a confirmed password, and locale. It normalizes the email, assigns the donor role, starts a session, and returns a 201 user resource. `POST /login` accepts email and password and returns a 200 user resource. `POST /logout` invalidates the session and returns 204. Fetch the Sanctum CSRF cookie before these mutations. The remaining endpoints and email verification and reset flows are planned.

Paths in the table are relative to `/api/v1`.

| Method and path | Access | Input and behavior |
| --- | --- | --- |
| `GET /me` | Signed-in user | Own account, locale, roles, and UI abilities |
| `GET /regions`, `GET /cities`, `GET /localities` | Public | Localized reference data, with parent filters on cities and localities |
| `GET /donation-policies/current` | Public | Published policy version, excess and pool handling, and localized text |
| `GET /cases`, `GET /cases/{id}` | Public | Published and redacted cases from verified shops, with region/city filters for the collection. Show cap, available capacity, and settled total |
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

`POST /api/v1/donations` with an idempotency key accepts the following body. Use `urgent_pool` without `debt_case_id` for pooled giving.

```json
{
  "scope": "specific",
  "debt_case_id": "df006b8f-3b0f-4a84-85ab-8468a20432c8",
  "amount_minor": "25000",
  "currency": "MAD",
  "policy_version": "mvp-1"
}
```

A 201 response wraps the new resource in `data` and includes its identifier, `pending` status, amount, scope, policy version, and simulated checkout action with the payment attempt ID. Reject stale policy versions with 409 so the donor can review the current policy. A checkout return can navigate the donor to the detail view. Only the signed callback or verified reconciliation can confirm payment.

The donation detail returns intent status `pending`, `confirmed`, or `expired`; captured, allocated, settled, and returned amounts; and redacted allocation entries with case ID, aggregate funded amount, and confirmed shop settlement attributable to that donation. Return details may show `requested`, `submitted`, `unknown`, `confirmed`, `failed`, or `canceled`. Amounts reflect different stages and should not be added together. Historical settlements remain visible after reversals. Keep internal payment references, evidence, and beneficiary identity out of donor responses.

## Callback and contract boundaries

For simulation, use a signed envelope carrying adapter event ID, operation reference, event type, amount, currency, and occurrence timestamp. Verify the signed raw payload and timestamp before writing to the inbox. Duplicate submissions with mismatched payloads enter reconciliation and return 409. Resolve late and out-of-order events using verified operation outcomes. Payout, allocation, and retry jobs have no public trigger. See the [state and money rules](business-rules.md), [database statuses](database-design.md), and [security requirements](security.md).

Document the exact DTOs, error codes, limits, and simulation envelope as OpenAPI when implementing this contract. Live payment contracts and real-provider actions are deliberately pending provider approval and capability verification.
