# Product Requirements Document (PRD)

## Finance & Capital Management System

| Field | Value |
| --- | --- |
| Product | Finance & Capital Management System |
| Version | 1.0 |
| Status | Live / iterating |
| Last updated | 04 November 2026 |
| Platform | Laravel 13, Livewire 4, Flux UI 2, Tailwind CSS 4, PostgreSQL |
| Currency / locale | INR (₹), `Asia/Kolkata`, `en-IN` |
| Audience | Super Admin, Admin, engineering, operations |

---

## 1. Overview

The Finance & Capital Management System is an internal lending operations platform. It manages the complete money cycle:

1. A **borrower** is onboarded with KYC and applies for a loan.
2. An **offer** is calculated with flat or reducing interest and accepted by the borrower.
3. **Investor capital** verification is run against the common available capital pool (cleared deposits net of active allocations) and capital is reserved from that pool — without selecting a specific investor or linking investors to borrowers.
4. The loan is **finally approved and disbursed** to the borrower.
5. **EMIs** are collected, reconciled and posted to the ledger.
6. **Investors** receive monthly interest payouts after management fee and TDS.
7. Every material action is **audited** and reported.

The platform is designed for a small, high-trust lending operation where the same team handles borrowers, investors and accounting. Every rupee must be traceable from investor deposit to borrower disbursement to EMI collection to investor payout.

### 1.1 Problem being solved

Spreadsheet-based lending operations fail in four predictable ways:

- **Capital is assumed, not verified.** Loans get disbursed before investor money has actually cleared.
- **Interest math is inconsistent.** Flat and reducing methods are mixed up, and rounding errors accumulate.
- **Investor payouts are manual.** Management fees, TDS and net amounts are calculated by hand.
- **There is no audit trail.** Nobody can prove who approved what, when, or against which bank reference.

This system replaces those spreadsheets with one workflow, one ledger and one source of truth.

### 1.2 Product principles

- **Money is exact.** All amounts use decimal/bcmath arithmetic and are stored with 2 decimal places. Floats are never used for money.
- **Capital is real.** Only cleared investor deposits count as available capital. Pending deposits are excluded.
- **Nothing skips a step.** A loan cannot be disbursed without a fully funded capital allocation and explicit final approval.
- **Every action is attributable.** Material changes write an audit log entry with actor, before/after values and reference.
- **One ledger.** Journals are double-entry and must balance before posting.
- **Operate, don't administer.** Admin users run the business; Super Admin users control access and audit.

---

## 2. Goals and success criteria

### 2.1 Goals

- Run the full borrower and investor lifecycle in one application.
- Guarantee that a loan is only funded with verified, cleared investor capital.
- Produce accurate EMI schedules and investor payouts automatically.
- Keep a complete, queryable audit trail.
- Give both roles a clear daily operating surface.

### 2.2 Success criteria

- 100% of new loans follow the 10-step processing workflow.
- 0 disbursements against partially funded loans.
- 0 investor payouts generated from uncollected interest.
- Every journal entry balances to the paisa.
- Every loan, investor and payout is reachable from the sidebar in at most two clicks.
- The full test suite passes on SQLite and the schema remains PostgreSQL-compatible.

### 2.3 Out of scope (current version)

- Borrower or investor self-service portals.
- Public marketing website or online loan applications.
- Payment gateway integration for automated collections.
- Multi-currency and multi-company accounting.
- Automated credit bureau or bank statement analysis.
- Native mobile applications.

---

## 3. Users and roles

There are two authenticated roles. Registration is disabled; users are created by a Super Admin. Login accepts **email or mobile number** with a password.

### 3.1 Super Admin

Full control of the platform.

- Everything an Admin can do.
- Create, edit and deactivate team members and assign roles.
- View audit logs across all modules.
- Manage settings and financial configuration.
- Review dashboards covering portfolio, capital and compliance.

### 3.2 Admin

Daily lending operations.

- Onboard borrowers and maintain KYC documents.
- Create loan applications, calculate offers and record acceptance.
- Verify investor capital, allocate funding and record disbursement.
- Record EMI collections and follow up on overdue accounts.
- Onboard investors, record deposits and manage payouts.
- Generate reports and CSV exports.
- Work the daily operations queue.

### 3.3 Permission matrix

| Capability | Super Admin | Admin |
| --- | --- | --- |
| Dashboard | Yes | Yes |
| Borrowers and KYC | Yes | Yes |
| Loans and offers | Yes | Yes |
| Capital verification and allocation | Yes | Yes |
| Disbursement and collections | Yes | Yes |
| Investors, deposits and payouts | Yes | Yes |
| Reports and exports | Yes | Yes |
| Daily operations | Yes | Yes |
| Audit logs | Yes | No |
| Team and access | Yes | No |
| System settings | Yes | No |

---

## 4. Core domain model

| Entity | Purpose |
| --- | --- |
| `User` | Authenticated staff member with a role |
| `Borrower` | Loan applicant with KYC, contact, income and bank details |
| `Guarantor` | Additional party attached to a borrower/loan |
| `KycDocument` | Uploaded identity and address documents (polymorphic) |
| `Loan` | Loan application and lifecycle record |
| `LoanOffer` | Immutable snapshot of accepted terms |
| `LoanFundingCommitment` | Investor commitment of capital to a specific loan |
| `Investor` | Capital provider with KYC and bank details |
| `InvestorDeposit` | Money deposited by an investor (pending/cleared/rejected) |
| `InvestorAllocation` | Reserved investor principal against a loan |
| `EmiSchedule` | One row per instalment with principal/interest split |
| `EmiPayment` | Collection recorded against a loan and its schedules |
| `InvestorPayout` | Monthly investor interest payout with fee and TDS |
| `Account` | Chart-of-accounts entry |
| `JournalEntry` / `JournalLine` | Balanced double-entry accounting records |
| `DailyTask` | Generated or manual operational to-do |
| `MessageLog` | Outbound WhatsApp/SMS/email record |
| `AuditLog` | Immutable record of material actions |
| `Setting` | Configurable business settings |

### 4.1 Key statuses

| Entity | Statuses |
| --- | --- |
| Loan | `pending`, `active`, `closed`, `foreclosed`, `rejected` |
| Application | `submitted`, `under_review`, `offer_generated`, `accepted`, `rejected`, `cancelled` |
| Funding | `unfunded`, `partially_funded`, `fully_funded` |
| Commitment | `confirmed`, `withdrawn`, `allocated` |
| Deposit | `pending`, `cleared`, `rejected` |
| Allocation | `active`, `released` |
| EMI | `pending`, `partial`, `paid`, `overdue`, `waived` |
| Payout | `pending`, `paid` |
| KYC | `pending`, `verified`, `rejected` |

---

## 5. Loan lifecycle

Every loan follows the same 10-step workflow. The loan detail page shows the current step, the checklist, required documents, the valid action and what happens next.

### Step 1 — Loan application

- The borrower must already exist with KYC documents on file.
- The application records requested principal, tenure, purpose and income.
- The application starts as `submitted` and moves to `under_review`.
- **Gate:** active borrower, valid amount, rate and tenure.

### Step 2 — Loan offer

- Flat and reducing balance options are calculated side by side.
- The offer records principal, rate, method, tenure, EMI, total interest, total repayment, processing fee and other charges.
- **Gate:** an interest method must be chosen and the calculation must succeed.

### Step 3 — Borrower acceptance

- The borrower accepts the offer.
- The accepted terms are frozen in `LoanOffer` and copied to the loan.
- **Gate:** acceptance must be recorded before any capital work begins.

### Step 4 — Investor capital verification

- Investor deposits that are `cleared`, minus active allocations platform-wide, form the **common available capital pool**.
- The system totals cleared deposits, subtracts active allocations, and compares the available pool with the approved amount.
- Pending or rejected deposits never count.
- **Gate:** the verification report must exist before funding is reserved.
- **Output:** the available capital pool balance and any shortage.

### Step 5 — Funding confirmation

- Reaching 100% marks the loan `fully_funded` and sets `funding_locked_at`.
- Required capital is automatically drawn from the **common available capital pool** and attributed to loan capital records — no investor selection and no investor-to-borrower linking.
- Further funding changes are blocked. Allocation beyond the available pool is rejected.
- **Gate:** approved amount reserved exactly, funding locked.

### Step 6 — Final approval

- An authorised admin reviews borrower details, investor allocations, agreement and bank account.
- Final approval is an explicit, audited decision.
- **Gate:** funding must be locked.

### Step 7 — Disbursement

- Payment is executed to the borrower's bank account.
- The disbursement date, amount and transaction reference are recorded.
- The loan becomes `active` and the EMI schedule is generated.
- **Gate:** final approval, funding locked, valid date and reference.

### Step 8 — EMI collection

- EMIs are collected against the schedule.
- The system supports full, partial and late payments.
- Each payment is allocated principal-first, then interest, and posted to the ledger.
- The loan closes automatically when every instalment is settled.
- **Gate:** active loan, valid payment method, reference required for non-cash payments, no future-dated collections.

### Step 9 — Reconciliation

- Collected principal and interest are matched to investor allocations.
- Investor payouts are based only on **interest actually collected**, never on scheduled or hypothetical interest.
- Borrowers with overdue amounts remain in the daily operations queue.

### Step 10 — Loan closure

- Outstanding principal must be zero and all EMIs paid or waived.
- Final borrower and investor statements are produced.
- Investor exposure is released and the loan is archived with full history.

---

## 6. Capital workflow

### 6.1 Investor onboarding

An investor record requires:

- Name, address and mobile number.
- PAN in valid format and unique.
- Aadhaar in valid 12-digit format and unique.
- Bank account number, IFSC and account holder name.
- Optional email.
- KYC documents: Aadhaar and PAN only.

Existing investor records that were created before these rules must be completed on their next save.

### 6.2 Deposits

- Admins record deposits with amount, date, status and reference.
- A deposit is `pending`, `cleared` or `rejected`.
- Only `cleared` deposits increase available capital.
- Deposits cannot be future-dated.
- Clearing a deposit records who cleared it and when.

### 6.3 Available capital formula

```
Available pool = Total cleared deposits − Total active allocations
Available per investor = Investor cleared deposits − Investor active allocations
```

Pending deposits, rejected deposits and released allocations are excluded. Capital is drawn from the common pool regardless of which investor contributed the funds.

### 6.4 Commitments

- Commitments are retained for backward compatibility and historical reporting.
- Once funding is reserved from the common pool, funding is locked and loan capital records are in place.

### 6.5 Allocation rules

- Capital is reserved **after** verification and **before** final approval.
- The reserved total must match the approved principal exactly.
- Allocation is drawn from the **common available capital pool**; no specific investor is selected and no investor-to-borrower linking is required.
- Over-allocation beyond the available pool is rejected; a loan cannot be reserved more than once.
- Concurrency is controlled with database locks to prevent double allocation.

### 6.6 Investor payouts

Payouts are generated monthly for every investor with an active allocation on an active loan.

```
Gross interest   = investor share of interest due in the month
Management fee   = gross × management_fee_percent / 100   (default 10%)
TDS              = gross × tds_percent / 100              (default 10%)
Net payout       = gross − management fee − TDS
```

Rules:

- Payouts are generated only from real scheduled interest on active loans.
- A paid payout is never regenerated.
- Marking a payout paid requires a payment reference and a non-future date.
- Payout journals are balanced:

```
Dr  Interest Expense        gross
    Cr  TDS Payable                TDS
    Cr  Management Fee Income      management fee
    Cr  Cash & Bank                net payout
```

---

## 7. Interest and money rules

### 7.1 Methods

- **Flat interest:** interest is calculated on the original principal for the full tenure.
- **Reducing balance:** interest is calculated on the outstanding principal each period.

### 7.2 Reference example

For ₹1,00,000 at 12% per annum for 12 months:

| Method | EMI | Total interest | Total repayment |
| --- | --- | --- | --- |
| Flat | ₹9,333.33 | ₹12,000.00 | ₹1,12,000.00 |
| Reducing | ₹8,884.88 | ₹6,618.53 | ₹1,06,618.53 |

These figures are regression-tested and must not drift.

### 7.3 Money handling

- All money values are strings/decimals, rounded to 2 decimal places.
- Arithmetic uses bcmath-backed helpers, never native floats.
- Display uses the Indian numbering system (`en-IN`) with the ₹ symbol.
- Dates and reporting use `Asia/Kolkata`.
- Journals must balance exactly before they are posted.

---

## 8. Accounting

### 8.1 Principles

- Double-entry only.
- Every journal references its source record where applicable.
- Entries are immutable once posted; corrections are new entries.
- Accounts are identified by code from a seeded chart of accounts.

### 8.2 Core postings

| Event | Debit | Credit |
| --- | --- | --- |
| Loan disbursement | Loans Receivable | Cash & Bank |
| EMI collection | Cash & Bank | Loans Receivable + Interest Income |
| Investor payout | Interest Expense | TDS Payable + Management Fee Income + Cash & Bank |
| Processing fee | Cash & Bank | Fee Income |

### 8.3 Reports

- Collections by period.
- Portfolio outstanding and health.
- Investor exposure and allocations.
- Payout register with gross, fee, TDS and net.
- CSV export of collections and payouts.

---

## 9. KYC and compliance

### 9.1 Borrower KYC

- Identity proof, address proof and electricity bill.
- Documents are stored against the borrower and downloadable by authorised users.
- KYC status is tracked as pending, verified or rejected.

### 9.2 Investor KYC

- Aadhaar and PAN are mandatory, format-validated and unique.
- Electricity bill is not collected from investors.
- Bank details are mandatory before capital can be allocated.

### 9.3 Audit

- Borrower, loan, investor, payout and funding actions are logged.
- Audit entries record actor, action, before/after values and timestamp.
- Only Super Admin can view audit logs.
- Login, user and role changes are recorded through the same audit mechanism.

---

## 10. Notifications and daily operations

### 10.1 Daily operations

The operations page is the team's daily queue:

- EMI reminders due today.
- Overdue follow-ups.
- KYC reviews.
- Payout tasks.
- Manual tasks with priority, assignee and due date.

Tasks can be completed, skipped, deleted (manual only) or generated from system state.

### 10.2 Notifications

- WhatsApp and SMS messages are dispatched through loggable channels.
- Every outbound message is stored in `MessageLog` with channel, recipient, body and status.
- Bulk reminder sending is supported from the operations page.
- Providers are disabled by default until configured.

---

## 11. Dashboards and reporting

### Super Admin dashboard

- Portfolio size, active loans and collections.
- Capital deployed vs available.
- Investor exposure and payout obligations.
- Overdue and compliance signals.
- Team and audit shortcuts.

### Admin dashboard

- Today's collections and pending EMIs.
- Active loans and applications in progress.
- Tasks needing action.
- Recent borrowers and investors.

### Reports

- Date-range filters with presets.
- Collections, portfolio, investor and payout views.
- CSV export for offline reconciliation.

---

## 12. Non-functional requirements

### 12.1 Security

- Authentication via Laravel Fortify.
- Role middleware on every protected route.
- CSRF protection on all state-changing requests.
- Password hashing with modern defaults.
- Document downloads authorised per record.
- No public registration.

### 12.2 Performance

- Paginated list views.
- Eager loading to avoid N+1 queries.
- Database transactions and row locks for funding and allocation.
- Reports bounded by date range.

### 12.3 Reliability

- Funding, allocation, disbursement, collection and payout operations are transactional.
- Failed operations roll back completely.
- Money invariants are validated in services, not only in forms.

### 12.4 Accessibility and responsiveness

- Keyboard-navigable forms and modals.
- Visible focus states and labelled controls.
- Responsive layout from 375px upward.
- Chart and diagram alternatives in text where practical.
- Motion respects `prefers-reduced-motion`.

### 12.5 Compatibility

- PostgreSQL in development and production.
- SQLite in the automated test suite.
- Migrations must remain portable between both.

---

## 13. Validation rules

| Area | Rules |
| --- | --- |
| Borrower | Name, mobile and address required; electricity bill upload allowed |
| Investor | Name, address, mobile, PAN, Aadhaar, bank details required; PAN/Aadhaar unique; Aadhaar and PAN uploads only |
| Loan | Active borrower; amount, rate and tenure within bounds; first EMI on or after disbursement; distinct guarantors |
| Offer | Valid interest method; positive principal and rate; tenure bounds |
| Allocation | Verified capital; active investor; no over-allocation; exact total |
| Collection | Active loan; positive amount; no future date; reference required for non-cash |
| Payout | Reference required to mark paid; no future paid date |
| User | Valid role; unique email and mobile; normalised input |

---

## 14. Acceptance criteria

- [ ] A Super Admin and an Admin can both log in and see their dashboard.
- [ ] Both roles can open the product guide from the sidebar.
- [ ] A borrower can be created with KYC documents.
- [ ] A loan can be created, offered, accepted, verified, allocated, approved, disbursed and collected.
- [ ] A loan cannot be disbursed while partially funded.
- [ ] Allocation totals must equal the approved principal.
- [ ] An investor cannot be allocated more than their cleared available capital.
- [ ] EMI schedules match the flat and reducing reference figures.
- [ ] Investor payouts apply management fee and TDS correctly.
- [ ] Every posted journal balances.
- [ ] Paid payouts require a reference.
- [ ] Returns by loan close automatically after all EMIs are settled.
- [ ] Audit logs are visible to Super Admin only.
- [ ] All lists and forms work at 375px width.
- [ ] The automated test suite passes.

---

## 15. Roadmap

### Phase 1 — Core operations (done)

- Auth, roles and dashboards.
- Borrowers, KYC and guarantors.
- Loans, offers, schedules and collections.
- Investors, deposits, allocations and payouts.
- Accounting and reports.
- Notifications, operations queue and audit logs.

### Phase 2 — Capital discipline (done)

- Funding status and commitments.
- Cleared-capital verification.
- Exact-match allocation and funding lock.
- Final approval and disbursement gates.
- Product guide and PRD.

### Phase 3 — Next

- Borrower and investor statements as PDF.
- Automated overdue penalty accrual.
- Bank reconciliation import.
- Scheduled payout generation.
- Optional borrower self-service and payment links.

---

## 16. Glossary

| Term | Meaning |
| --- | --- |
| **EMI** | Equated monthly instalment: principal + interest |
| **Flat interest** | Interest charged on the original principal for the full tenure |
| **Reducing interest** | Interest charged on the outstanding principal |
| **Cleared deposit** | Investor money confirmed in the company bank account |
| **Commitment** | An investor's confirmed intent to fund a specific loan |
| **Allocation** | Capital reserved from an investor against a loan |
| **Funding lock** | Point at which a loan reaches 100% funding and can no longer change |
| **TDS** | Tax deducted at source on investor interest |
| **Management fee** | Platform fee deducted from gross investor interest |
| **Journal** | Balanced double-entry accounting record |
| **KYC** | Know Your Customer identity and address verification |
