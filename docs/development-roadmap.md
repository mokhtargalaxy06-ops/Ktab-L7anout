# Development roadmap

Each phase requires explicit instruction before implementation. Use the [architecture](architecture.md) and [database design](database-design.md) as the baseline. Update the relevant design and contract together if an implementation decision changes the model.

| Phase | Dependencies and scope | Exit evidence |
| --- | --- | --- |
| 0. Architecture | Agree on boundaries, terminology, money flows, privacy, and contracts | Review these six documents as a set. Track proposed policies separately from approved operating decisions |
| 1. Foundation | Set up separate React/Vite and Laravel projects, PostgreSQL, migrations, test environments, linting, CI, Sanctum, role controls, French/Arabic catalogs, and RTL layout | Builds and tests pass. Session, CSRF, authorization, migration, and locale smoke tests pass. Document setup and deployment |
| 2. Cases and verification | Add 12-region reference data, profiles, shops and memberships, evidence quarantine, human verification, redacted publication, and audit history | Exercise every role boundary and case transition. Confirm private evidence is inaccessible without authorization |
| 3. Simulated donations and allocation | Publish approved policy text for the intended environment. Add simulated checkout, inbox/outbox, financial journal, donor impact, specific allocation, and urgency ranking | Replay and concurrent allocation tests pass in PostgreSQL. Verify late captures, duplicate captures, cap races, deterministic ties, pool splits, and journal rebuild |
| 4. Simulated settlement and returns | Add payout approvals, holds, evidence, refunds, reversals, reconciliation, and notifications | Test ambiguous outcomes, crashes around side effects, return and payout races, reversals after settlement, freeze recovery, and shop-only destinations |
| 5. Operational readiness | Run restore and incident exercises, review privacy and accessibility, measure transaction contention, and verify end-to-end simulation using synthetic data | Demonstrate restoration and reconciliation. Review audit trails, accessibility, operational ownership, and outstanding policy decisions |
| 6. Live processing decision | Requires approved legal structure and payment provider. Confirm approved excess/return policy, data retention, settlement destinations, and actual provider behavior | Separate approval and implementation instruction. Verify provider-specific idempotency, signatures, reconciliation, and settlement before release |

## Quality gates and dependencies

Use PHPUnit or Pest for Laravel and Vitest with React Testing Library for the SPA. Use concurrent PostgreSQL connections for locking regressions. At minimum, exercise French and Arabic text and direction, screen-reader and keyboard flows for core screens, resource scope across roles, malicious uploads, signature failures, duplicate callbacks, and payout evidence controls. Retain sanitized fixtures and use synthetic profiles and credentials.

Use the confirmed simulated MVP policy in [business-rules.md](business-rules.md). Obtain operating approval for the scoring rubric, caps, pool holding period, excess policy, and evidence retention before handling real cases or donations. Monitor failure rates, reconciliation age, and lock contention during simulation. Settlement uncertainty and unavailable case capacity are expected scenarios in the pilot. Train operators to resolve them before release.

After explicit implementation authorization, verify financial constraints against PostgreSQL and run integration tests for dispatch claim and cancellation races, cross-role resource access, callback replay, and reconciliation after ambiguous transfers. Test pool expiration immediately before and at the deadline, including a release after expiry and a refund delayed by a freeze.

Follow [AGENTS.md](../AGENTS.md) for each phase. Maintain the README and add deployment documentation with foundation and operational work. The current deliverable is design documentation.
