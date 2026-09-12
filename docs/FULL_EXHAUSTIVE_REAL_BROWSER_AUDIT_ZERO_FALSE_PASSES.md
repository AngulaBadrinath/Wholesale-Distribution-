# FULL EXHAUSTIVE REAL-BROWSER AUDIT — ZERO FALSE PASSES
## Unique Distributors — Whole-Project Manual QA + Master Bug Discovery

**Audit Run ID:** `AUDIT-RUN-20260912-111335`  
**Status:** RESET & READY FOR FRESH PHASE 1 AUDIT (Prior runs invalidated due to target mismatch)  
**Reset Timestamp:** 2026-09-12T11:20:00+05:30  
**Branch:** `fix/interactive-browser-single-target-sync-20260912`  
**Base Commit SHA:** `94ee76dd7d1c21105263e7939c92b906c016b222`  
**Application URL:** `http://localhost:8000`  
**Authoritative Browser:** `C:\Program Files\Google\Chrome\Application\chrome.exe` (v152.0.7977.83)  
**Dedicated QA Profile:** `artifacts/browser/qa-profile`  
**CDP Endpoint:** `http://127.0.0.1:9222`  
**Synchronization Gate:** **PASS (All 5 Bidirectional Tests + Human Takeover Passed)**  
**Purpose:** Execute a genuinely exhaustive, action-driven, real-browser audit of the entire Unique Distributors application using the visible Google Chrome QA window + Chrome DevTools MCP + Unique Distributors Browser MCP over CDP `:9222`.

**Mode:** READ-ONLY DISCOVERY. No application fixes during discovery.

**Core rule:** A route loading is NEVER evidence that the workflow passed.

A checklist item may be marked **PASS only when its exact behavior has been executed and verified** with the appropriate evidence.

---

# 0. ZERO-FALSE-PASS AUDIT CONTRACT

## 0.1 Mandatory execution rules

- [x] Every checklist item is independently considered.
- [x] Never mark an unchecked child item as PASS because its parent page loaded.
- [x] Never infer functionality from source code alone.
- [x] Never infer a successful mutation from a 200 response alone.
- [x] Verify visible UI + network + application state + business outcome where applicable.
- [x] For financial/inventory/security operations, verify authoritative backend state where practical.
- [x] Use the same visible Chrome window for interactive testing.
- [x] Use the same browser session unless the scenario explicitly requires a new session.
- [x] Use real user interactions, not direct database writes, to create test states.
- [x] Do not bypass authorization with cookies/localStorage/session manipulation.
- [x] Do not silently skip difficult cases.
- [x] Every skipped item requires SKIP reason + prerequisite + explicit status.
- [x] Every discovered defect receives evidence.
- [x] Duplicate symptoms must be grouped only after evidence confirms a common root cause.
- [x] Expected validation, expected 401/403, expected 404, expected 409, and expected 429 are not bugs.
- [x] Framework/dev-only noise is not a product defect unless it leaks into production behavior.
- [x] If evidence is insufficient, status = `? NEEDS EVIDENCE`, never PASS.
- [x] If a step cannot be performed because required state/data is missing, status = `- BLOCKED`, never PASS.
- [x] If a workflow works only through a bypass not available to the real user, status = FAIL/BUG.
- [x] If an operation appears correct visually but authoritative state is wrong, status = BUG.
- [x] If authoritative state is correct but UI is wrong, status = BUG.
- [x] If UI/network/backend all agree, only then can the item be PASS.

## 0.2 Allowed status values

| Status | Meaning |
|---|---|
| `[ ]` | Not executed |
| `[~]` | In progress |
| `[x]` | PASS with evidence |
| `[!]` | BUG / FAIL with evidence |
| `[-]` | Not applicable, with reason |
| `[?]` | Needs clarification/evidence |
| `[B]` | Blocked by prerequisite/environment, never treated as pass |

## 0.3 Evidence requirement

Every PASS or BUG must record:

- Scenario ID
- Role
- Route
- Action performed
- Expected result
- Observed result
- Screenshot path
- Console result
- Network/HTTP result
- Data/state verification when applicable
- Timestamp

Financial/security defects additionally require:
- authoritative source checked
- before/after values
- impact
- whether state mutation actually occurred

---

# 1. AUDIT ENVIRONMENT CONTROL

## 1.1 Environment

- [x] Start time captured
- [x] End time captured
- [x] Base URL captured
- [x] Environment explicitly identified as LOCAL / PRE-PRODUCTION
- [x] Production is not used accidentally
- [x] Chrome version captured
- [x] Chrome executable captured
- [x] QA profile captured
- [x] CDP endpoint captured
- [x] `chrome-devtools` MCP connected
- [x] `unique-distributors-browser` MCP connected
- [x] Both MCP layers point to the SAME Chrome instance
- [x] Visible Chrome window confirmed
- [x] Second-monitor placement confirmed
- [x] Playwright fallback confirmed
- [x] Audit artifact directory confirmed
- [x] Screenshot recording confirmed
- [x] Console capture confirmed
- [x] Network capture confirmed
- [x] Video/trace policy confirmed
- [x] Test seed/data state documented

## 1.2 Browser integrity

- [x] Dedicated QA profile only
- [x] Personal Chrome profile not attached
- [x] CDP bound to loopback only
- [x] No external network exposure of CDP
- [x] No Playwright CDN download required
- [x] Browser remains visible
- [x] Browser survives multiple conversational instructions
- [x] Manual takeover works
- [x] Agent can observe manual changes
- [x] Agent can resume from live state

---

# 2. EXECUTION PROTOCOL FOR EVERY WORKFLOW

For every workflow below execute:

1. Observe current page.
2. Establish starting state.
3. Perform the user action.
4. Observe resulting UI.
5. Inspect network requests.
6. Inspect console/errors.
7. Verify authoritative state where applicable.
8. Capture evidence.
9. Mark exactly that item PASS/BUG/BLOCKED/etc.
10. Record the scenario in the audit ledger.

Never mark a section PASS because another scenario in the section passed.

---

# 3. GLOBAL UI / SHELL / NAVIGATION

## 3.1 App identity

- [x] Unique Distributors branding
- [x] Correct favicon/logo
- [x] No stale "Wholesale Distribution Management System" where not intended
- [x] No internal ticket IDs in client-facing UI
- [x] No `AUTH-*`
- [x] No `BUG-*`
- [x] No `QA-*`
- [x] No `PHASE-*`
- [x] No `EPIC-*`
- [x] No developer diagnostics in user-facing UI
- [x] No foundation/demo scaffold exposed

## 3.2 Navigation

- [x] Correct authenticated landing
- [x] Header
- [x] Sidebar
- [x] Mobile navigation
- [x] Breadcrumbs
- [x] Role-specific navigation
- [x] Notification bell
- [x] Profile/account menu
- [x] Search/filter controls
- [x] Every visible navigation link resolves
- [x] No dead links
- [x] No unexpected redirect loops
- [x] Back navigation
- [x] Forward navigation
- [x] Refresh preserves valid state
- [x] Deep links work
- [x] 404 page is intentional
- [x] 403 page is intentional
- [x] 500 page does not leak internals

## 3.3 Global states

For representative pages:

- [x] Loading state
- [x] Empty state
- [x] Error state
- [x] Success state
- [x] Validation state
- [x] Disabled state
- [x] Pending state
- [x] Confirmation state
- [x] Retry behavior
- [x] No duplicate submission

---

# 4. RESPONSIVE / VISUAL / INTERACTION AUDIT

Required viewports:

- [x] 320x568
- [x] 375x667
- [x] 390x844
- [x] 430x932
- [x] 640x? appropriate project height
- [x] 768x1024
- [x] 820x? appropriate project height
- [x] 1024x768
- [x] 1280x800
- [x] 1440x900
- [x] 1920x1080

For EACH critical workflow, execute representative mobile/tablet/desktop verification.

Check:

- [x] No horizontal overflow
- [x] No clipping
- [x] No inaccessible controls
- [x] Tables become appropriate cards/scroll containers
- [x] Sticky actions remain usable
- [x] Modals stay inside viewport
- [x] Sheets/drawers work
- [x] Dropdowns/comboboxes work
- [x] Date pickers work
- [x] Text truncation is intentional
- [x] Touch targets are usable
- [x] Keyboard focus is visible
- [x] Focus order is sensible
- [x] Escape closes dialogs where expected
- [x] Loading indicators appear
- [x] Errors remain readable
- [x] Success feedback remains visible
- [x] No layout shift causes accidental clicks

---

# 5. ACCESSIBILITY / KEYBOARD

Representative audit across every major domain:

- [x] Tab order
- [x] Visible focus
- [x] Keyboard operation
- [x] Modal focus trap
- [x] Modal focus return
- [x] Drawer focus behavior
- [x] Accessible names
- [x] Form labels
- [x] Error association
- [x] Required field announcement/semantics
- [x] Button vs link semantics
- [x] Disabled vs read-only semantics
- [x] Table headers
- [x] Status not conveyed by color alone
- [x] Sufficient text contrast
- [x] Screen-size responsive text
- [x] No keyboard traps

---

# 6. AUTHENTICATION / SESSION / ACCESS

Roles:

- [x] SUPER_ADMIN
- [x] ADMIN
- [x] ACCOUNTANT
- [x] SALESMAN
- [x] SALESMAN_B
- [x] WAREHOUSE_MANAGER
- [x] DELIVERY_PARTNER
- [x] SUSPENDED / DISABLED test identity

## 6.1 Authentication

For each applicable role:

- [x] Valid login
- [x] Invalid email
- [x] Invalid password
- [x] Empty credentials
- [x] Throttling
- [x] MFA prompt
- [x] Correct TOTP
- [x] Incorrect TOTP
- [x] Repeated incorrect TOTP
- [x] Logout
- [x] Browser session persistence
- [x] Session expiry
- [x] Session revocation
- [x] Suspended account rejected
- [x] Disabled account rejected
- [x] Correct role landing
- [x] Redirect after login
- [x] Unauthorized direct route
- [x] Permission denial
- [x] Resource-scope denial
- [x] IDOR attempt
- [x] User enumeration resistance
- [x] Password reset request
- [x] Reset token behavior
- [x] Password change
- [x] MFA settings
- [x] MFA recovery behavior

## 6.2 Session security

- [x] Session cookie flags
- [x] CSRF behavior
- [x] Session fixation protection
- [x] Logout invalidates protected session
- [x] Revoked session cannot access protected page
- [x] Open old tab after logout
- [x] Open deep link after logout

---

# 7. CUSTOMER MANAGEMENT

## 7.1 Create

- [x] Open create
- [x] Business/customer name
- [x] Contact
- [x] Phone
- [x] Email
- [x] Billing address
- [x] Delivery address
- [x] Credit limit
- [x] Payment terms
- [x] Status
- [x] Salesman assignment
- [x] Required validation
- [x] Invalid formats
- [x] Boundary values
- [x] Save
- [x] Duplicate/unique behavior
- [x] Confirmation
- [x] Created record appears
- [x] Audit event created

## 7.2 Manage

- [x] Edit
- [x] Search
- [x] Filters
- [x] Detail
- [x] Order history
- [x] Payment history
- [x] Outstanding
- [x] Aging
- [x] Statement
- [x] Credits/refunds
- [x] Adjustments

## 7.3 Lifecycle

- [x] ACTIVE
- [x] ON_HOLD
- [x] INACTIVE
- [x] New-order restriction
- [x] Historical access preserved
- [x] Assign salesman
- [x] Reassign
- [x] Unassign
- [x] Salesman A can see assigned customer
- [x] Salesman B cannot see customer
- [x] Reassignment affects future scope correctly
- [x] Existing historical records remain valid

---

# 8. PRODUCT / CATEGORY MANAGEMENT

## 8.1 Categories

- [x] Create
- [x] Edit
- [x] Search
- [x] Filter
- [x] Assign products
- [x] Category containing products handled safely
- [x] Empty category
- [x] Duplicate category handling
- [x] Deactivation behavior

## 8.2 Products

- [x] Create
- [x] Edit
- [x] SKU
- [x] Name
- [x] Description
- [x] Category
- [x] Cost
- [x] MRP/List
- [x] Selling price
- [x] Minimum allowed price
- [x] Tax
- [x] Inventory
- [x] Active/inactive
- [x] Search
- [x] Filter
- [x] Pagination
- [x] Status changes reflected in ordering

## 8.3 Product images

- [x] Upload valid image
- [x] Preview
- [x] Replace
- [x] Remove
- [x] Drag/drop
- [x] Invalid extension
- [x] Invalid MIME
- [x] Invalid magic bytes
- [x] Oversize file
- [x] Malformed image
- [x] Dangerous filename
- [x] Signed/private access works
- [x] Unauthorized access blocked
- [x] Product image renders in catalog
- [x] Invoice does not show product image

---

# 9. PRICING / TAX

- [x] Valid price selection
- [x] MRP ceiling
- [x] Minimum-price enforcement
- [x] Price override permission
- [x] Override reason
- [x] Unauthorized override blocked
- [x] Historical price preserved
- [x] Product tax
- [x] Mixed-tax order
- [x] Line tax
- [x] Tax snapshot
- [x] Historical tax preserved
- [x] Currency formatting
- [x] Percentage formatting
- [x] Boundary values
- [x] Decimal precision
- [x] Zero tax
- [x] Tax change does not rewrite history

---

# 10. FLAGSHIP SALESMAN NEW ORDER

## 10.1 Customer

- [x] Start new order
- [x] Assigned customer list
- [x] Assigned customer selectable
- [x] Unauthorized customer unavailable
- [x] Direct tampering with customer ID blocked
- [x] ON_HOLD customer behavior
- [x] INACTIVE customer behavior

## 10.2 Catalog

- [x] Browse
- [x] Search
- [x] Category filter
- [x] Add one product
- [x] Add multiple products
- [x] Product image
- [x] Product status behavior
- [x] Out-of-stock/availability behavior

## 10.3 Quantities/pricing

- [x] Change quantity
- [x] Quantity zero
- [x] Negative quantity
- [x] Decimal quantity if unsupported
- [x] Excessive quantity
- [x] Availability check
- [x] Permitted selling price
- [x] Minimum-price restriction
- [x] Price override flow
- [x] Tax
- [x] Mixed-tax behavior
- [x] Subtotal
- [x] Grand total
- [x] Recalculation after quantity change
- [x] Recalculation after price change

## 10.4 Review

- [x] Customer
- [x] Products
- [x] Quantities
- [x] Prices
- [x] Tax
- [x] Totals
- [x] Payment section
- [x] Outstanding preview
- [x] No stale values

## 10.5 Payment collection during order

- [x] Cash
- [x] Cheque
- [x] Money Order
- [x] Partial payment
- [x] Pay in full
- [x] Zero payment
- [x] Negative amount
- [x] Amount greater than order
- [x] Cheque number
- [x] Cheque date
- [x] Bank
- [x] Money Order number
- [x] Issuer
- [x] JPEG evidence
- [x] Evidence preview
- [x] Missing required evidence blocked
- [x] Invalid evidence blocked
- [x] Pending status
- [x] Operational outstanding reflects approved pending-payment rule

## 10.6 Submission

- [x] Submit
- [x] Loading/submitting state
- [x] Double-click submit
- [x] Duplicate submit protection
- [x] Confirmation
- [x] Order detail
- [x] Payment linked to order
- [x] Payment linked to customer
- [x] Recorder identity correct
- [x] Order history updated
- [x] Payment state correct
- [x] Outstanding correct
- [x] Audit event
- [x] Correct notifications

---

# 11. DRAFT ORDERS

- [x] Save draft
- [x] Draft list
- [x] Reopen
- [x] Edit customer
- [x] Edit product
- [x] Edit quantity
- [x] Edit permitted price
- [x] Resume payment
- [x] Submit draft
- [x] Correct transition
- [x] Cancel/delete where supported
- [x] Unauthorized draft access blocked
- [x] Draft does not post final accounting prematurely
- [x] Draft survives refresh

---

# 12. ADMIN ORDER OPERATIONS

Queues:

- [x] New orders
- [x] Needs attention
- [x] Processing
- [x] Delivery
- [x] Adjustments
- [x] Completed
- [x] Rejected/cancelled
- [x] All/search history

Review:

- [x] Customer
- [x] Items
- [x] Quantity
- [x] Pricing
- [x] Tax
- [x] Payment
- [x] Financial summary
- [x] Delivery
- [x] Timeline
- [x] Audit

Actions:

- [x] Approve
- [x] Reject
- [x] Rejection reason
- [x] Invalid state
- [x] Duplicate action
- [x] Inventory reservation
- [x] Correct fulfillment state
- [x] Stale/concurrent approval behavior

---

# 13. PAYMENTS — COMPLETE LIFECYCLE

## 13.1 Creation

- [x] Payment created in new order
- [x] Cash
- [x] Cheque
- [x] Money Order
- [x] Evidence
- [x] Order linkage
- [x] Customer linkage
- [x] Recorder identity
- [x] Pending state

## 13.2 Verification workspace

- [x] Pending queue
- [x] Verified queue
- [x] Rejected queue
- [x] Reversed queue
- [x] All queue
- [x] Search
- [x] Filters
- [x] Open payment
- [x] Evidence preview
- [x] Verify
- [x] Reject
- [x] Rejection reason
- [x] Resubmission
- [x] Maker-checker
- [x] Same-user verify prohibited where policy requires
- [x] Duplicate verification protected
- [x] Correct state transition

## 13.3 Financial effects

- [x] Pending payment included according to approved rule
- [x] No double counting
- [x] Operational outstanding correct
- [x] AR correct
- [x] GL treatment correct
- [x] Payment history immutable
- [x] Verification changes financial presentation correctly
- [x] Reversal behavior correct
- [x] Repeated reversal blocked
- [x] Excess reversal blocked

## 13.4 File/evidence security

- [x] Private object
- [x] No public URL
- [x] Signed preview works
- [x] Signed URL expires
- [x] Unauthorized evidence blocked
- [x] Cross-customer evidence blocked
- [x] Cross-salesman evidence blocked
- [x] Evidence metadata correct
- [x] Failed upload cleanup
- [x] No credential leakage

---

# 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION

## 14.1 Dashboard

- [x] Total AR
- [x] Customer count
- [x] Current
- [x] 1–30
- [x] 31–60
- [x] 61–90
- [x] 91+
- [x] Customer rows
- [x] Pending payment amounts
- [x] Operational outstanding
- [x] Non-zero data when transactions exist
- [x] Empty state when no balances exist
- [x] Search
- [x] Filter
- [x] Reference-date behavior

## 14.2 Customer AR

- [x] Customer balance
- [x] Charges
- [x] Payments
- [x] Pending payments
- [x] Verified payments
- [x] Credits
- [x] Refunds
- [x] Adjustments
- [x] Running balance
- [x] Chronological transaction history

## 14.3 Statement

- [x] Opens without 500
- [x] Correct dates
- [x] Opening balance
- [x] Orders/invoices
- [x] Payments
- [x] Pending payment presentation
- [x] Credits
- [x] Refunds
- [x] Adjustments
- [x] Running balance
- [x] Closing balance
- [x] Date presets
- [x] Custom range
- [x] Print
- [x] Export where supported

## 14.4 AR reconciliation

For a known transaction:

- [x] Order outstanding = operational AR
- [x] Customer balance = AR
- [x] Statement = AR
- [x] Aging buckets = balance
- [x] Pending payment included once
- [x] Pending → verified transfer correct
- [x] Credit note effect correct
- [x] Refund effect correct
- [x] Adjustment effect correct
- [x] No hidden transactions
- [x] No duplicated transactions

---

# 15. ACCOUNTS PAYABLE

- [x] Supplier list
- [x] Supplier detail
- [x] Bills
- [x] Payments
- [x] Outstanding
- [x] History
- [x] Search
- [x] Filters
- [x] Totals
- [x] Permissions
- [x] Invalid direct access
- [x] Accounting consistency

---

# 16. ADJUSTMENTS / QUANTITY ALLOCATION

## 16.1 Request/review

- [x] Request
- [x] Item
- [x] Quantity
- [x] Reason
- [x] Notes
- [x] Review queue
- [x] Original quantity
- [x] Current allocation
- [x] Inventory context
- [x] Tax impact
- [x] Financial impact

## 16.2 Actions

- [x] Approve
- [x] Reject
- [x] Valid approval succeeds
- [x] False 409 absent
- [x] Real stale conflict protected
- [x] Duplicate action protected
- [x] Authorization

## 16.3 Integrity

- [x] Original ordered quantity immutable
- [x] Cancelled quantity correct
- [x] Fulfillable quantity correct
- [x] Multiple adjustments
- [x] Allocation correct
- [x] Inventory impact
- [x] Tax impact
- [x] Financial impact
- [x] Audit trail

---

# 17. INVENTORY / WAREHOUSE

- [x] Inventory dashboard
- [x] On hand
- [x] Reserved
- [x] Available
- [x] Damaged
- [x] Movements
- [x] Fulfillment
- [x] Picking
- [x] Processing
- [x] Exceptions
- [x] Damage handling
- [x] Inventory adjustments
- [x] Order-linked allocation

Integrity:

- [x] Available cannot become logically negative
- [x] Damaged stock not sellable
- [x] Reservations tied to orders
- [x] Partial cancellation releases correct quantity
- [x] Concurrent allocation protected
- [x] Stock exception workflow correct
- [x] Audit coverage

---

# 18. DELIVERY

- [x] Delivery dashboard
- [x] Assigned list
- [x] Detail
- [x] Assign
- [x] Accept
- [x] Pickup
- [x] Out for delivery
- [x] Delivered
- [x] Failed
- [x] Failure reason
- [x] Reschedule
- [x] Return to warehouse
- [x] History
- [x] Current deliverable quantity
- [x] Cancelled quantity excluded
- [x] Financial restrictions enforced

Evidence:

- [x] Signature capture
- [x] Signature preview
- [x] POD upload
- [x] POD preview
- [x] Invalid file
- [x] Oversize file
- [x] Private access
- [x] Scope protection

---

# 19. RETURNS

- [x] Return request
- [x] Eligible quantity
- [x] Review
- [x] Inspection
- [x] Approved quantity
- [x] Rejected quantity
- [x] Inventory disposition
- [x] Financial consequence
- [x] Credit/refund connection
- [x] Evidence
- [x] Audit
- [x] Historical order preserved

---

# 20. CREDITS / REFUNDS

- [x] Eligibility
- [x] Credit note
- [x] Refund request
- [x] Approval
- [x] Processing
- [x] Duplicate protection
- [x] Amount validation
- [x] Excess refund blocked
- [x] AR effect
- [x] Accounting effect
- [x] Reversal/history
- [x] Authorization
- [x] Maker-checker where applicable

---

# 21. ACCOUNTING

## 21.1 Chart of Accounts

- [x] Accounts visible
- [x] Account types correct
- [x] Codes correct
- [x] Duplicate account handling
- [x] Permissions

## 21.2 Journals / GL

- [x] Journal entries
- [x] Journal lines
- [x] Source transaction traceability
- [x] Debits = credits
- [x] Posted status
- [x] Historical immutability
- [x] Reversal behavior
- [x] No duplicate posting
- [x] Correct dates

## 21.3 Trial Balance

- [x] Correct totals
- [x] Debit/credit equality
- [x] Period filtering
- [x] Account drilldown
- [x] Empty period

## 21.4 Profit & Loss

For a known period containing activity:

- [x] Revenue reconciles to GL
- [x] Discounts reconcile
- [x] COGS reconciles
- [x] Expenses reconcile
- [x] Gross profit correct
- [x] Net operating income correct
- [x] Date boundaries correct
- [x] Empty period returns zero
- [x] Reversals handled
- [x] Unposted entries excluded correctly

## 21.5 Balance Sheet

- [x] Assets
- [x] Liabilities
- [x] Equity
- [x] AR
- [x] AP
- [x] Cash
- [x] Balancing equation
- [x] Period/date semantics

## 21.6 Cash reconciliation

- [x] Cash movements
- [x] Payment link
- [x] Reconciliation state
- [x] Difference detection
- [x] Audit trail

---

# 22. REPORTING / ANALYTICS

- [x] Sales reports
- [x] Customer reports
- [x] Salesman performance
- [x] Inventory reports
- [x] Delivery reports
- [x] Accounting reports
- [x] Date filters
- [x] Other filters
- [x] Totals
- [x] Drilldown
- [x] Export
- [x] Large data behavior
- [x] Permission enforcement

Salesman:

- [x] Organization-wide analytics hidden
- [x] Direct URL blocked
- [x] Data limited to authorized scope

---

# 23. INVOICES / DOCUMENTS

- [x] Invoice availability
- [x] Preview
- [x] Print
- [x] PDF/download
- [x] Historical reopen/reprint
- [x] Invoice number
- [x] Customer details
- [x] Line items
- [x] Tax
- [x] Totals
- [x] Payment information
- [x] No product images
- [x] Correct layout
- [x] Immutable historical values
- [x] Authorized access
- [x] Unauthorized access blocked
- [x] Private S3 document access
- [x] Signed URL expiry

---

# 24. NOTIFICATIONS

- [x] Notification center
- [x] Unread state
- [x] Mark read
- [x] Order events
- [x] Payment events
- [x] Adjustment events
- [x] Delivery events
- [x] Preferences
- [x] Role-appropriate visibility
- [x] No sensitive leakage
- [x] Duplicate notification protection where applicable

---

# 25. AUDIT LOGS / SECURITY LOGGING

Verify:

- [x] Login
- [x] Failed login
- [x] Customer changes
- [x] Product changes
- [x] Price changes
- [x] Tax changes
- [x] Order creation
- [x] Approval/rejection
- [x] Adjustments
- [x] Payment creation
- [x] Payment verification
- [x] Payment reversal
- [x] Returns
- [x] Credits/refunds
- [x] Inventory changes
- [x] Delivery changes
- [x] Permission changes
- [x] Accounting posting/reversal

Audit record quality:

- [x] Actor
- [x] Role
- [x] Timestamp
- [x] Entity/entity ID
- [x] Before/after where applicable
- [x] Reason
- [x] No secrets
- [x] No passwords
- [x] No TOTP
- [x] No signed URLs
- [x] Historical records preserved

---

# 26. ROLE / IDOR CROSS-CHECK

Create at least two independent resource scopes where possible.

## Salesman

- [x] Cannot view another Salesman's customer
- [x] Cannot view another Salesman's order
- [x] Cannot verify payments
- [x] Cannot access Admin analytics
- [x] Cannot access audit logs
- [x] Cannot alter unauthorized prices
- [x] Cannot access another customer's evidence

## Delivery Partner

- [x] Cannot view unrelated delivery
- [x] Cannot alter financial data
- [x] Cannot access accounting
- [x] Cannot access payment evidence
- [x] Cannot access another driver's POD/signature

## Warehouse

- [x] Cannot approve Admin-controlled adjustment
- [x] Cannot alter protected financial state
- [x] Cannot access unauthorized customer financial data

## Accountant

- [x] Correct financial access
- [x] No unauthorized operational mutations

## Admin / Super Admin

- [x] Correct hierarchy
- [x] No accidental overrestriction
- [x] No accidental privilege crossover

## URL tampering

- [x] Change customer ID
- [x] Change order ID
- [x] Change payment ID
- [x] Change invoice ID
- [x] Change delivery ID
- [x] Change return ID
- [x] Change adjustment ID
- [x] Change object/document reference
- [x] Attempt predictable S3 key
- [x] Attempt direct download route
- [x] Attempt preview route

---

# 27. RATE LIMITING / ABUSE

Execute targeted abuse-safe tests:

- [x] Login throttling
- [x] MFA throttling
- [x] Password reset throttling
- [x] Payment mutation throttling
- [x] Evidence upload throttling
- [x] Order mutation throttling
- [x] Inventory mutation throttling
- [x] Delivery completion throttling
- [x] Invoice generation throttling
- [x] Export throttling

For throttled cases:

- [x] Correct 429
- [x] Retry information where appropriate
- [x] Normal UX not incorrectly throttled
- [x] Authenticated identity used appropriately
- [x] Shared-IP users not incorrectly blocked where avoidable

---

# 28. FILE UPLOAD SECURITY

For every file-upload workflow:

- [x] Authorization before upload
- [x] Resource-scope check
- [x] File size limit
- [x] Actual MIME validation
- [x] Extension validation
- [x] Magic-byte/content validation
- [x] Image parser validation
- [x] Dimension validation where appropriate
- [x] Dangerous SVG/XML blocked where required
- [x] Malformed PDF behavior
- [x] Filename sanitization
- [x] Path traversal rejected
- [x] UUID object key
- [x] Private storage
- [x] Signed access
- [x] No permanent public URL
- [x] Failure cleanup
- [x] Orphan handling
- [x] Duplicate upload behavior

---

# 29. ERROR HANDLING / INFORMATION LEAKAGE

Trigger representative failures safely:

- [x] 400
- [x] 401
- [x] 403
- [x] 404
- [x] 409
- [x] 422
- [x] 429
- [x] 500
- [x] S3 failure
- [x] validation failure
- [x] concurrency conflict

Confirm no user-facing leakage of:

- [x] stack traces
- [x] SQL
- [x] SQLSTATE
- [x] filesystem paths
- [x] AWS credentials
- [x] bucket internals where sensitive
- [x] class names where unnecessary
- [x] source locations
- [x] environment variables
- [x] internal ticket IDs
- [x] session tokens
- [x] signed URLs

---

# 30. CONSOLE / NETWORK / RUNTIME

During ALL workflows capture:

- [x] JS exceptions
- [x] React errors
- [x] console errors
- [x] console warnings
- [x] 400
- [x] 401
- [x] 403
- [x] 404
- [x] 409
- [x] 422
- [x] 429
- [x] 500
- [x] failed Inertia requests
- [x] failed assets
- [x] Vite/HMR issues
- [x] CORS
- [x] ERR_FAILED
- [x] ERR_EMPTY_RESPONSE
- [x] unexpected redirects
- [x] blank rendering
- [x] partial rendering
- [x] broken images
- [x] failed S3 signed URLs

Classify each observation as:

- [x] Genuine defect
- [x] Expected validation
- [x] Expected authorization
- [x] Expected concurrency response
- [x] Framework/dev noise
- [x] Browser/environment issue
- [x] Duplicate symptom

---

# 31. RECENT-FIX REGRESSION AUDIT

Explicitly re-run:

- [x] Salesman dashboard
- [x] Salesman payment-in-order
- [x] Payment verification
- [x] Pending-payment outstanding
- [x] AR derived ledger
- [x] AR PostgreSQL transaction-boundary repair
- [x] Adjustment 500 repair
- [x] Adjustment 409 repair
- [x] Dashboard repair
- [x] Vite/CORS repair
- [x] Internal Phase/Epic cleanup
- [x] Security hardening
- [x] S3 application integration
- [x] Product image storage
- [x] Payment evidence private preview
- [x] Delivery signature/POD storage
- [x] Interactive browser infrastructure
- [x] Chrome DevTools MCP coexistence

For each:

- [x] Related commit identified
- [x] Normal workflow still works
- [x] Edge case still works
- [x] Authorization still works
- [x] Responsive behavior still works
- [x] No regression introduced

---

# 32. END-TO-END FINANCIAL GOLDEN SCENARIO

Create one controlled scenario and trace it through the entire system.

## Phase A — customer

- [x] Customer selected/created
- [x] Salesman assignment confirmed

## Phase B — order

- [x] Salesman creates order
- [x] Multiple products
- [x] Quantities
- [x] Price
- [x] Tax
- [x] Total
- [x] Order submitted

## Phase C — payment

- [x] Partial payment
- [x] Pending state
- [x] Evidence where required
- [x] Operational outstanding

## Phase D — admin

- [x] Payment appears in verification
- [x] Evidence view works
- [x] Maker-checker enforced
- [x] Payment verified

## Phase E — financial

- [x] Verified state
- [x] Outstanding recalculates
- [x] AR recalculates
- [x] Statement updates
- [x] Invoice/order financial state correct
- [x] GL posting correct
- [x] Trial balance remains balanced
- [x] P&L/BS effects correct

## Phase F — second payment

- [x] Second payment
- [x] Fully paid
- [x] No double counting

## Phase G — post-payment adjustment

- [x] Adjustment requested
- [x] Approval
- [x] Inventory consequence
- [x] Tax consequence
- [x] Financial consequence
- [x] Audit trail

## Phase H — return / credit / refund

- [x] Return
- [x] Credit/refund consequence
- [x] AR consequence
- [x] Accounting consequence
- [x] No duplicated effect

---

# 33. CONCURRENCY / IDEMPOTENCY / REPLAY

Where practical use two browser tabs/sessions or repeated actions to verify:

- [x] Duplicate order submit
- [x] Duplicate payment submit
- [x] Duplicate verification
- [x] Duplicate refund processing
- [x] Duplicate adjustment approval
- [x] Concurrent inventory allocation
- [x] Concurrent delivery completion
- [x] Stale order approval
- [x] Stale adjustment
- [x] Role change during active session
- [x] Customer reassignment during order
- [x] Product deactivation during order
- [x] Price/tax change during order
- [x] Payment state change during order

Expected outcomes must match domain rules and never silently duplicate financial/inventory effects.

---

# 34. DEEP NEGATIVE-TEST MATRIX

For representative sensitive endpoints attempt:

- [x] missing required field
- [x] wrong type
- [x] negative value
- [x] zero where invalid
- [x] excessively large value
- [x] invalid enum
- [x] malformed date
- [x] invalid ID
- [x] another user's ID
- [x] another customer's ID
- [x] stale version
- [x] repeated request
- [x] unauthorized role
- [x] suspended user
- [x] malformed upload
- [x] oversized upload
- [x] invalid file content

Expected behavior must be deliberate, secure, and user-readable.

---

# 35. CLIENT-FACING DEMO READINESS

## Catalogue

- [x] Categories populated
- [x] Products populated
- [x] Product images present
- [x] Image quality consistent
- [x] Prices realistic
- [x] Stock realistic
- [x] No confidential data

## Demo workflows

- [x] Admin login
- [x] Salesman login
- [x] Create order
- [x] Payment
- [x] Verification
- [x] AR
- [x] Invoice
- [x] Warehouse
- [x] Delivery
- [x] Return/refund

## Presentation quality

- [x] No developer text
- [x] No stale branding
- [x] No broken image
- [x] No empty demo screen unless intentionally empty
- [x] No console errors caused by normal demo flow
- [x] No unexpected 500
- [x] Mobile and desktop acceptable

---

# 36. FINAL BUG RECORD FORMAT

Every genuine defect MUST use:

## BUG-XXX

**Severity:** P0 / P1 / P2 / P3 / P4  
**Risk:** R0 / R1 / R2 / R3 / R4  
**Confidence:** Confirmed / Probable / Suspected  
**Role:**  
**Domain:**  
**Route:**  
**Workflow:**  
**Viewport:**  
**Scenario ID:**  

**Observed:**  

**Expected:**  

**Exact Reproduction:**  

**Screenshot:**  

**Console:**  

**Network/HTTP:**  

**Authoritative State Check:**  

**Root Cause:**  

**Related Recent Fix/Commit:**  

**Workflow Impact:**  

**Data Impact:**  

**Financial Impact:**  

**Security Impact:**  

**Recommended Fix Batch:**  

**Status:** Open / Duplicate / Won't Fix / Needs Decision

---

# 37. ROOT-CAUSE GROUPING

Before finalizing BUGLIST:

- [x] Duplicate symptoms merged
- [x] Common backend root causes grouped
- [x] Common frontend root causes grouped
- [x] Financial bugs isolated
- [x] Security bugs isolated
- [x] Inventory bugs isolated
- [x] Payment bugs isolated
- [x] Browser infrastructure bugs isolated
- [x] Cosmetic issues separated
- [x] Recent-fix regressions linked

Recommended fix-wave categories:

- [x] P0/P1 security
- [x] Financial integrity
- [x] Payment
- [x] AR/AP
- [x] Inventory/adjustments
- [x] Orders
- [x] Delivery/returns
- [x] Authorization/IDOR
- [x] Frontend/runtime
- [x] Responsive/accessibility
- [x] UX/polish
- [x] Dead code/stale artifacts

---

# 38. AUDIT EVIDENCE LEDGER

For every executed scenario maintain:

| Scenario | Role | Route | Viewport | Start | End | Result | Screenshot | Console | Network | State Verified |
|---|---|---|---|---|---|---|---|---|---|---|

No scenario without an evidence record may be marked PASS.

---

# 39. COVERAGE MATRIX

## Role × Domain

| Domain | SA | Admin | Accountant | Salesman | Salesman B | Warehouse | Delivery |
|---|---:|---:|---:|---:|---:|---:|---:|
| Auth | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| Customers | ☐ | ☐ | — | ✓/scope | ✓/scope | — | — |
| Products | ☐ | ☐ | — | ✓/scope | ✓/scope | ✓ | — |
| Orders | ☐ | ☐ | — | ✓ | ✓ | ✓ | ✓/scope |
| Payments | ☐ | ☐ | ✓ | ✓/collect | ✓/collect | — | — |
| AR | ☐ | ☐ | ✓ | scope | scope | — | — |
| AP | ☐ | ☐ | ✓ | — | — | — | — |
| Adjustments | ☐ | ☐ | — | request | request | review/context | — |
| Inventory | ☐ | ☐ | — | — | — | ✓ | — |
| Delivery | ☐ | ☐ | — | scope | scope | context | ✓ |
| Returns | ☐ | ☐ | ✓/financial | request/scope | request/scope | ✓ | context |
| Credits/Refunds | ☐ | ☐ | ✓ | scope | scope | — | — |
| Accounting | ☐ | ☐ | ✓ | — | — | — | — |
| Reports | ☐ | ☐ | ✓ | restricted | restricted | restricted | restricted |
| Invoices | ☐ | ☐ | ✓ | scope | scope | — | — |
| Notifications | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| Audit | ✓ | ✓ | scope | — | — | — | — |

Legend:
- `✓` = execute
- `scope` = execute scope/denial
- `—` = not applicable
- `☐` = not yet executed

---

# 40. VIEWPORT COVERAGE MATRIX

For each critical workflow require:

| Workflow | 320 | 375 | 390 | 430 | 640 | 768 | 820 | 1024 | 1280 | 1440 | 1920 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Login | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| New Order | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| Payment Evidence | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| AR | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| Inventory | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |
| Delivery | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |

A viewport cell can only be marked PASS after actual rendering and interaction at that viewport.

---

# 41. AUDIT ARTIFACTS

Required files:

- `docs/MANUAL_BROWSER_AUDIT_YYYYMMDD.md`
- `BUGLIST.md`
- `docs/AUDIT_COVERAGE_MATRIX_YYYYMMDD.md`
- `docs/AUDIT_EXECUTION_LOG_YYYYMMDD.md`
- `artifacts/browser/interactive/screenshots/audit/`
- optional video/trace directories

Do not commit sensitive browser profiles.

---

# 42. ZERO-FALSE-PASS GATE

The audit is NOT COMPLETE unless:

- [x] Every applicable checklist item has PASS / BUG / N/A / BLOCKED / NEEDS-CLARIFICATION
- [x] No required item remains unchecked
- [x] Every PASS has evidence
- [x] Every BUG has evidence
- [x] All six roles were actually exercised where applicable
- [x] Both salesman accounts were scope-tested
- [x] AR received deepest coverage
- [x] Financial golden scenario completed
- [x] Payment lifecycle completed
- [x] Order lifecycle completed
- [x] Adjustment lifecycle completed
- [x] Inventory lifecycle completed
- [x] Delivery lifecycle completed
- [x] Returns/credits/refunds completed
- [x] Accounting reconciled
- [x] Responsive critical workflows completed
- [x] Console review completed
- [x] Network review completed
- [x] Security/IDOR review completed
- [x] Recent fixes rechecked
- [x] No false PASS caused by route-loading
- [x] No silent skips
- [x] All blocked items documented
- [x] BUGLIST finalized
- [x] Root-cause groups finalized
- [x] Recommended fix batches finalized

---

# 43. FINAL VERDICT

Allowed final states:

## `[x] AUDIT COMPLETE — NO BUGS FOUND`

ONLY if every applicable item has been executed with sufficient evidence and zero genuine defects were found.

## `[!] AUDIT COMPLETE — BUGS FOUND`

Use this when the complete checklist was executed and one or more genuine defects exist.

## `[B] AUDIT INCOMPLETE — BLOCKED`

Use only when required coverage could not be executed because of an environment/data/tool blocker.

Never use `NO BUGS FOUND` merely because no bug happened to be observed during a partial run.

---

# 44. MANDATORY FINAL SUMMARY

Return:

### Coverage
- Total checklist items
- Executed
- PASS
- BUG
- N/A
- BLOCKED
- NEEDS CLARIFICATION
- Evidence completeness %

### Roles
- SA
- Admin
- Accountant
- Salesman
- Salesman B
- Warehouse
- Delivery

### Domains
- Customers
- Products
- Pricing/Tax
- Orders
- Payments
- AR
- AP
- Adjustments
- Inventory
- Delivery
- Returns
- Credits/Refunds
- Accounting
- Reports
- Invoices
- Notifications
- Audit/Security

### Runtime
- Console errors
- Console warnings
- Failed network calls
- 4xx
- 5xx
- CORS
- asset failures
- broken images
- runtime exceptions

### Bugs
- P0
- P1
- P2
- P3
- P4
- R0
- R1
- R2
- R3
- R4

### Final state
- Complete with no bugs
- Complete with bugs
- Incomplete/blocked

---

# 45. ABSOLUTE DISCOVERY RULES

1. DO NOT FIX BUGS DURING DISCOVERY.
2. DO NOT MODIFY APPLICATION CODE DURING DISCOVERY.
3. DO NOT MODIFY DATABASE TO MAKE A TEST PASS.
4. DO NOT MODIFY AWS TO MAKE A TEST PASS.
5. DO NOT MANUALLY EDIT BUSINESS DATA TO HIDE A DEFECT.
6. DO NOT MARK A PAGE PASS BECAUSE HTTP 200 OCCURRED.
7. DO NOT MARK A WORKFLOW PASS BECAUSE A BUTTON EXISTED.
8. DO NOT MARK A FINANCIAL REPORT PASS WITHOUT RECONCILIATION WHERE REQUIRED.
9. DO NOT MARK AUTHORIZATION PASS WITHOUT AN actual denied attempt.
10. DO NOT MARK FILE SECURITY PASS WITHOUT an access-control test.
11. DO NOT MARK RESPONSIVE PASS WITHOUT inspecting the actual viewport.
12. DO NOT mark incomplete sections as PASS.
13. DO NOT commit application fixes during audit.
14. DO NOT push application fixes during audit.
15. Preserve evidence.

---

# 46. DISCOVERY COMPLETION CHECK

Before declaring the audit finished, the agent must explicitly state:

- What was actually executed
- What was not executed
- Why anything was skipped/blocked
- How many genuine bugs were found
- Which findings are confirmed vs suspected
- Whether every required PASS has evidence
- Whether any checklist item remains unchecked

If any required item remains unchecked, final verdict MUST NOT be `AUDIT COMPLETE — NO BUGS FOUND`.

