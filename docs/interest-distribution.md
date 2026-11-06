# Interest Distribution & Profit Sharing

This document describes the implemented interest distribution module and records the
places where it deliberately changes earlier rules in `docs/PRD.md`. The PRD remains the
product-level intent document; this file is the engineering source of truth for the
distribution behaviour.

## Source of truth: collected interest only

Distribution is created **only** from `emi_payments.interest_component` on verified,
non-reversed EMI receipts. Scheduled interest, uncollected interest and full EMI amounts
are never used.

- A receipt is eligible when its `interest_component` is greater than zero.
- Reversing a receipt does not delete anything. It creates compensating
  `interest_distribution_ledgers` rows with `entry_type = reversal` and reverse journal
  lines.
- Distributing the same payment twice is a no-op: the ledger is keyed by
  `entitlement_key` and the service returns existing rows.

## Loan snapshot

On disbursement (and manually by a Super Admin), `InterestDistributionService::ensureSnapshot()`
writes an immutable loan-wise snapshot:

- selected `InterestConfiguration`
- borrower monthly rate (annual / 12)
- investor, admin and company allocation percentages
- investor-wise principal, monthly rate and principal share
- responsible admins and their commission weights

`loan_distribution_configs`, `loan_investor_distributions` and `loan_admin_assignments`
are created once. Later changes to global settings, individual investor rates or admin
profiles never rewrite an existing snapshot.

## Calculation model

For a receipt with collected interest `I`:

1. Investor pool = `I × investor_allocation_percent / 100`.
2. Each investor target = `principal × investor_monthly_rate / 100`.
3. If the sum of targets exceeds the pool, all targets are scaled proportionally and the
   last investor absorbs the rounding remainder. If targets are below the pool, the
   unused investor pool flows to the company.
4. Admin pool = `I × admin_allocation_percent / 100`, split among the loan's responsible
   admins by `admin_commission_profiles.commission_rate` (equal split only if all weights
   are zero).
5. Company share = `I − investor amounts − admin amounts` (residual, never negative).

Because the company share is the residual, the sum of all ledger entries always equals
the collected interest exactly, to two decimals.

Example: `I = 1,000`, split `60 / 10 / 30`, one investor with `60,000` at `1.5%`
(target `900`) and one with `40,000` at `1.0%` (target `400`). Targets `1,300` exceed the
investor pool `600`, so the investors receive `415.38` and `184.62`; the admin receives
`100.00`; the company receives `300.00`.

## Ledger and journals

Every beneficiary gets a separate `interest_distribution_ledgers` row with
`status = earned`. The distribution journal is:

```
Dr 4000 Interest Income          I
  Cr 2200 Investor Interest Payable   investor total
  Cr 2300 Admin Commission Payable    admin total
  Cr 4300 Company Interest Share      company total
```

Reversal posts the mirror image and marks the original entries as reversed through
compensating rows; no original row is edited or deleted.

Investor payout settlement:

```
Dr 2200 Investor Interest Payable   gross
  Cr 2100 TDS Payable               tds
  Cr 4200 Management Fee Income     fee
  Cr 1000 Cash & Bank               net
```

Admin commission settlement:

```
Dr 2300 Admin Commission Payable    gross
  Cr 1000 Cash & Bank               net
```

## Payout workflow

Investor payouts and admin commission payouts both use:

`pending → approved → paid`

with `rejected` and `cancelled` as terminal alternatives. Only a Super Admin may approve,
reject, process or cancel. Generation is idempotent and only links ledger rows that are not
already linked to a payout item.

Payouts carry a `batch_no`, so a second payout can be generated in the same month after an
earlier batch has already been paid. This replaces the old one-payout-per-investor-per-month
assumption.

## Conflicts with `docs/PRD.md` and resolution

The PRD contains contradictory wording and several rules that the implemented workflow
intentionally supersedes. They are recorded here as requested.

| # | Existing PRD rule | Conflict / impact | Resolution |
|---|---|---|---|
| 1 | §6.6: “Payouts are generated only from real scheduled interest on active loans.” | Contradicts §5: “Investor payouts are based only on interest actually collected.” Scheduled interest may never be collected, so paying it creates unfunded liabilities. | Actual collected `interest_component` is the only source. Scheduled interest never creates a ledger entry or payout. |
| 2 | Payout status is limited to `pending` and `paid`. | No way to represent Super Admin review, rejection, cancellation or a supplementary batch. | Added `approved`, `rejected`, `cancelled` and a `batch_no`; `pending → approved → paid` is the normal path. |
| 3 | §6.6 journal: `Dr Interest Expense / Cr TDS + Management Fee + Cash`. | The platform already recognises borrower interest income (`4000`) on collection. Debiting a separate interest expense and paying from it double counts the economics. | Distribution reclassifies recognised interest income: `Dr 4000` against payable/company accounts. Payout settlement clears the payable liability. |
| 4 | Unique payout per investor per month. | Blocks a supplementary payout after a batch is already paid, and blocks correction batches. | Unique key is now `(investor_id, period_month, batch_no)`. Generation reuses an existing pending batch and creates a new batch only when required. |
| 5 | `InvestorPayoutService::generate()` derived gross from scheduled interest for every active allocation. | Would create entitlements for months with no collection and would ignore reversals, late or partial payments. | Rewritten to aggregate unlinked investor ledger entries for the month. Net is signed, so a reversal made before payout generation offsets the earning and cannot create a negative payout. Reversals are blocked once entries are linked to a payout. |
| 6 | Admin commission had no first-class ledger or payout workflow. | Commission was previously implicit in the allocation percentage. | Added admin commission profiles, snapshot assignments, admin ledger entries, an admin payout workflow, and dedicated payable/earning accounts. |
| 7 | Company share was not represented as a payable/income account. | Company residual could not be reconciled against collected interest. | Company share is the residual of collected interest and is posted to `4300 Company Interest Share`. |

## Database objects added

| Object | Purpose |
|---|---|
| `interest_configurations` | Versioned global monthly rates and allocation percentages. |
| `investor_interest_rates` | Per-investor overrides with effective dates. |
| `admin_commission_profiles` | Per-admin designation and commission weight. |
| `loan_distribution_configs` | Immutable loan snapshot header and rules JSON. |
| `loan_investor_distributions` | Immutable investor principal/rate/share rows. |
| `loan_admin_assignments` | Immutable responsible-admin rows. |
| `interest_distribution_ledgers` | Immutable earning and reversal entries. |
| `emi_payment_allocations` | Per-schedule split of each receipt, required for accurate reversal. |
| `investor_payout_items` / `admin_commission_payout_items` | Link payout rows to ledger entries exactly once. |

Accounts `2200`, `2300` and `4300` are inserted by migration. A default active
configuration (1.5% monthly, `60 / 10 / 30`) is inserted only when the table is empty, so
existing installations keep working immediately.

## Operational notes

- Run `php artisan migrate` after pulling the module.
- Review the bootstrap configuration in **Interest Settings** before production use.
- `EmiCollectionService::reversePayment()` refuses legacy payments that have no schedule
  allocation detail; such receipts must be corrected manually through accounting.
- A Super Admin can reverse a receipt from **EMI Collections**. Reversal is blocked while
  the receipt's ledger entries are linked to an investor or admin payout. Reject or cancel
  the pending/approved payout first; once a payout is `paid` the receipt cannot be reversed
  without a separate cash-recovery process.
- Receipts created before the module was deployed have no distribution entries. Open the
  loan's **Distribution** page, lock the snapshot if needed, then use **Distribute
  collections** to backfill every un-distributed interest receipt. The action is idempotent.
