# Phase 9 financial integrity report

## Verified behavior

| Invariant | Executed evidence |
| --- | --- |
| An intention creates no confirmed receipt | Pending, failure and cancellation fixtures leave captured totals zero. The confirmed receipt follows a recorded simulator success. |
| Confirmations cannot duplicate funds | Signed webhook tests verify replay deduplication, conflicting payload rejection and independent repeated notifications. |
| Allocations stay within available receipt funding and case capacity | PostgreSQL multi-process tests exercise concurrent capture/allocation and idempotent intent creation. Direct-donation tests confirm capacity again at funding time and queue excess. |
| Specific donations stay restricted | The existing test verifies excess never funds another case. The original case remains tied to the intention. |
| Urgent pool decisions are traceable | Tests split one receipt across ranked cases and return unused funds at expiry. Allocation runs store the scoring inputs and linked funding lines. |
| Returns preserve historical facts | Refund, reversal, hold release, recovery and replay tests exercise compensating journal entries. Immutable records are protected against mutation. |
| Settlement requires evidence and distinct approval | Tests verify separate administrators, shop receipt confirmation, a dispute preventing reconciliation, and server-generated evidence before paid status. |
| Financial reports reconcile | Fixtures compare receipt, allocation and transfer totals, shop confirmation and final settlement. CSV assertions verify journal event entries and per-receipt balances. |

## Findings

| Severity | Finding | Disposition |
| --- | --- | --- |
| High | Reconciliation previously inferred the latest shop response by UUID sort order across workers. | Fixed. The current response is changed in the same locked transaction as the immutable action, and a composite foreign key ties it to the same payout. |
| Medium | Historical responses with equal stored timestamps have limited ordering evidence. | For tied timestamps, the migration retains the dispute for unsettled payouts and the confirmation for paid payouts. An assigned shopkeeper can submit a fresh confirmation to resolve an unsettled tie. |
| High | Live cash movement, external transfer evidence, provider reconciliation and completed-payout shortfall recovery are unverified. | Release gate. The tests exercise simulations and do not authorize live settlement. |

PostgreSQL ran 44 tests and 690 assertions. SQLite ran 43 tests and 675 assertions, with one PostgreSQL-only test skipped. Amounts remain MAD minor units. The calculated available balance subtracts net allocation and pending refund holds once. Transferred amounts are already part of the allocation and are not deducted twice.
