# Morocco Debt Relief engineering instructions

These instructions apply throughout the repository.

## 1. Project mission

Morocco Debt Relief is a nationwide charitable platform that helps financially vulnerable Moroccan families settle verified unpaid grocery debts at local grocery shops (Moul Hanout).

Donors can:

- Select a specific verified family and contribute to its eligible debt case.
- Support the most urgent eligible debts through a transparent pooled donation allocation system.
- Track their contributions and see their impact.

Account for every donation. In the MVP, beneficiaries must never receive funds directly. Settle verified debts with the verified grocery shop.

## 2. Technology stack

| Layer | Required technologies |
| --- | --- |
| Frontend | React.js, TypeScript, Vite, Tailwind CSS, React Router, Axios, React Hook Form, and Zod |
| Backend | PHP and the latest stable Laravel version compatible with the installed PHP version. Expose a Laravel REST API and use Laravel Sanctum, Laravel validation, and Laravel Policies. |
| Database | PostgreSQL |
| Backend testing | PHPUnit or Pest |
| Frontend testing | Vitest and React Testing Library |

## 3. Architecture rules

- Keep the frontend and backend separate and use the REST API as their integration boundary.
- Follow Laravel conventions and use migrations for every database schema change.
- Use Form Requests for input validation and Policies for authorization.
- Keep controllers focused on coordinating requests and responses. Use Services for complex business logic.
- Use database transactions for financial operations. Enforce financial invariants when requests run concurrently or are retried.
- Use DTOs or other structured data objects where they clarify boundaries or data contracts.
- Use Laravel API Resources for consistent JSON responses.
- Keep frontend components reusable and maintainable.

## 4. User roles

- Donor
- Beneficiary
- Shopkeeper
- Administrator

A charity partner role may be added in a future phase. Enforce role-based authorization and least-privilege access. Check permissions against the specific resource where access depends on ownership or assignment.

## 5. Business rules

- Publish only approved and verified debt cases. Require shopkeepers to confirm outstanding debts.
- Cap collections for each case at its eligible outstanding balance, including when direct and pooled contributions apply to the same case.
- Allow donors to contribute to a specific case or to the urgent debt pool.
- Allocate urgent pool donations according to documented, auditable rules. Retain enough information to explain each allocation.
- Record a donation as successful only after payment confirmation is received.
- Authenticate payment webhooks and process them idempotently.
- Record refunds and reversals as traceable adjustments linked to the original financial records. Never silently overwrite or delete financial records.
- Retain evidence for every payment to a verified grocery shop.
- Handle excess contributions according to a published policy.
- Protect beneficiaries' personal information. Limit public and donor-facing information to what is needed to understand the debt case and its impact.

## 6. Moroccan localization

- Support all 12 Moroccan administrative regions, including cities and localities.
- Use Moroccan dirhams (MAD).
- Store and calculate monetary amounts using integer minor units or precise decimal types. Never use floating-point arithmetic for money.
- Support French and Arabic. Provide right-to-left layouts for Arabic.
- Display dates in a format appropriate to the selected Moroccan locale.
- Keep translation strings separate from application logic.

## 7. Security requirements

- Never expose passwords, tokens, or private documents in logs, API responses, or public storage.
- Validate every request server-side and enforce authorization for every protected resource.
- Store private files securely and authorize access before serving them.
- Prevent SQL injection, XSS, CSRF, and mass assignment vulnerabilities.
- Protect financial endpoints against replay and duplicate requests.
- Rate-limit sensitive endpoints.
- Keep secrets in environment variables. Never commit `.env` files or credentials.
- Calculate and validate authoritative financial amounts on the server. Never trust frontend calculations for financial amounts.

## 8. Development workflow

### Before implementing a task

1. Inspect the existing codebase.
2. Read this file and relevant project documentation.
3. Understand the existing architecture.
4. Identify the files that need to change.
5. Briefly explain the implementation plan.

### While implementing

1. Make focused changes.
2. Follow existing conventions.
3. Avoid unnecessary dependencies.
4. Write appropriate tests.
5. Avoid unrelated refactoring.

### After implementing

1. Run relevant tests.
2. Run formatting and static checks.
3. Fix errors discovered during verification.
4. Explain which files changed and why.
5. Report the tests and checks performed and their results.
6. Report any remaining limitations.

## 9. Documentation

Maintain these files as the corresponding parts of the project are introduced and changed.

| File | Content |
| --- | --- |
| `README.md` | Project overview, local setup, and development commands |
| `docs/architecture.md` | System boundaries and architecture decisions |
| `docs/database-design.md` | Data model, relationships, and invariants |
| `docs/api-documentation.md` | Endpoints, authentication, and request and response contracts |
| `docs/business-rules.md` | Debt verification, eligibility, allocation, settlement, refunds, and excess contributions |
| `docs/security.md` | Authorization, data protection, and payment security |
| `docs/deployment.md` | Deployment procedures and environment configuration |

## 10. Development phases and payment approval

- Work step by step. Implement multiple major development phases only when explicitly instructed.
- Never invent legal authorization, payment-provider capabilities, real beneficiaries, or production credentials.
- Use simulated payment processing until both the legal structure and the payment provider have been approved.
- The initial phase is limited to establishing this file. Application implementation requires a subsequent instruction.
