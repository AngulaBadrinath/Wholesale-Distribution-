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

- [ ] Every checklist item is independently considered.
- [ ] Never mark an unchecked child item as PASS because its parent page loaded.
- [ ] Never infer functionality from source code alone.
- [ ] Never infer a successful mutation from a 200 response alone.
- [ ] Verify visible UI + network + application state + business outcome where applicable.
- [ ] For financial/inventory/security operations, verify authoritative backend state where practical.
- [ ] Use the same visible Chrome window for interactive testing.
- [ ] Use the same browser session unless the scenario explicitly requires a new session.
- [ ] Use real user interactions, not direct database writes, to create test states.
- [ ] Do not bypass authorization with cookies/localStorage/session manipulation.
- [ ] Do not silently skip difficult cases.
- [ ] Every skipped item requires SKIP reason + prerequisite + explicit status.
- [ ] Every discovered defect receives evidence.
- [ ] Duplicate symptoms must be grouped only after evidence confirms a common root cause.
- [ ] Expected validation, expected 401/403, expected 404, expected 409, and expected 429 are not bugs.
- [ ] Framework/dev-only noise is not a product defect unless it leaks into production behavior.
- [ ] If evidence is insufficient, status = `? NEEDS EVIDENCE`, never PASS.
- [ ] If a step cannot be performed because required state/data is missing, status = `- BLOCKED`, never PASS.
- [ ] If a workflow works only through a bypass not available to the real user, status = FAIL/BUG.
- [ ] If an operation appears correct visually but authoritative state is wrong, status = BUG.
- [ ] If authoritative state is correct but UI is wrong, status = BUG.
- [ ] If UI/network/backend all agree, only then can the item be PASS.

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

- [ ] Start time captured
- [ ] End time captured
- [ ] Base URL captured
- [ ] Environment explicitly identified as LOCAL / PRE-PRODUCTION
- [ ] Production is not used accidentally
- [ ] Chrome version captured
- [ ] Chrome executable captured
- [ ] QA profile captured
- [ ] CDP endpoint captured
- [ ] `chrome-devtools` MCP connected
- [ ] `unique-distributors-browser` MCP connected
- [ ] Both MCP layers point to the SAME Chrome instance
- [ ] Visible Chrome window confirmed
- [ ] Second-monitor placement confirmed
- [ ] Playwright fallback confirmed
- [ ] Audit artifact directory confirmed
- [ ] Screenshot recording confirmed
- [ ] Console capture confirmed
- [ ] Network capture confirmed
- [ ] Video/trace policy confirmed
- [ ] Test seed/data state documented

## 1.2 Browser integrity

- [ ] Dedicated QA profile only
- [ ] Personal Chrome profile not attached
- [ ] CDP bound to loopback only
- [ ] No external network exposure of CDP
- [ ] No Playwright CDN download required
- [ ] Browser remains visible
- [ ] Browser survives multiple conversational instructions
- [ ] Manual takeover works
- [ ] Agent can observe manual changes
- [ ] Agent can resume from live state

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

- [ ] Unique Distributors branding
- [ ] Correct favicon/logo
- [ ] No stale "Wholesale Distribution Management System" where not intended
- [ ] No internal ticket IDs in client-facing UI
- [ ] No `AUTH-*`
- [ ] No `BUG-*`
- [ ] No `QA-*`
- [ ] No `PHASE-*`
- [ ] No `EPIC-*`
- [ ] No developer diagnostics in user-facing UI
- [ ] No foundation/demo scaffold exposed

## 3.2 Navigation

- [ ] Correct authenticated landing
- [ ] Header
- [ ] Sidebar
- [ ] Mobile navigation
- [ ] Breadcrumbs
- [ ] Role-specific navigation
- [ ] Notification bell
- [ ] Profile/account menu
- [ ] Search/filter controls
- [ ] Every visible navigation link resolves
- [ ] No dead links
- [ ] No unexpected redirect loops
- [ ] Back navigation
- [ ] Forward navigation
- [ ] Refresh preserves valid state
- [ ] Deep links work
- [ ] 404 page is intentional
- [ ] 403 page is intentional
- [ ] 500 page does not leak internals

## 3.3 Global states

For representative pages:

- [ ] Loading state
- [ ] Empty state
- [ ] Error state
- [ ] Success state
- [ ] Validation state
- [ ] Disabled state
- [ ] Pending state
- [ ] Confirmation state
- [ ] Retry behavior
- [ ] No duplicate submission

---

# 4. RESPONSIVE / VISUAL / INTERACTION AUDIT

Required viewports:

- [ ] 320x568
- [ ] 375x667
- [ ] 390x844
- [ ] 430x932
- [ ] 640x? appropriate project height
- [ ] 768x1024
- [ ] 820x? appropriate project height
- [ ] 1024x768
- [ ] 1280x800
- [ ] 1440x900
- [ ] 1920x1080

For EACH critical workflow, execute representative mobile/tablet/desktop verification.

Check:

- [ ] No horizontal overflow
- [ ] No clipping
- [ ] No inaccessible controls
- [ ] Tables become appropriate cards/scroll containers
- [ ] Sticky actions remain usable
- [ ] Modals stay inside viewport
- [ ] Sheets/drawers work
- [ ] Dropdowns/comboboxes work
- [ ] Date pickers work
- [ ] Text truncation is intentional
- [ ] Touch targets are usable
- [ ] Keyboard focus is visible
- [ ] Focus order is sensible
- [ ] Escape closes dialogs where expected
- [ ] Loading indicators appear
- [ ] Errors remain readable
- [ ] Success feedback remains visible
- [ ] No layout shift causes accidental clicks

---

# 5. ACCESSIBILITY / KEYBOARD

Representative audit across every major domain:

- [ ] Tab order
- [ ] Visible focus
- [ ] Keyboard operation
- [ ] Modal focus trap
- [ ] Modal focus return
- [ ] Drawer focus behavior
- [ ] Accessible names
- [ ] Form labels
- [ ] Error association
- [ ] Required field announcement/semantics
- [ ] Button vs link semantics
- [ ] Disabled vs read-only semantics
- [ ] Table headers
- [ ] Status not conveyed by color alone
- [ ] Sufficient text contrast
- [ ] Screen-size responsive text
- [ ] No keyboard traps

---

# 6. AUTHENTICATION / SESSION / ACCESS

Roles:

- [ ] SUPER_ADMIN
- [ ] ADMIN
- [ ] ACCOUNTANT
- [ ] SALESMAN
- [ ] SALESMAN_B
- [ ] WAREHOUSE_MANAGER
- [ ] DELIVERY_PARTNER
- [ ] SUSPENDED / DISABLED test identity

## 6.1 Authentication

For each applicable role:

- [ ] Valid login
- [ ] Invalid email
- [ ] Invalid password
- [ ] Empty credentials
- [ ] Throttling
- [ ] MFA prompt
- [ ] Correct TOTP
- [ ] Incorrect TOTP
- [ ] Repeated incorrect TOTP
- [ ] Logout
- [ ] Browser session persistence
- [ ] Session expiry
- [ ] Session revocation
- [ ] Suspended account rejected
- [ ] Disabled account rejected
- [ ] Correct role landing
- [ ] Redirect after login
- [ ] Unauthorized direct route
- [ ] Permission denial
- [ ] Resource-scope denial
- [ ] IDOR attempt
- [ ] User enumeration resistance
- [ ] Password reset request
- [ ] Reset token behavior
- [ ] Password change
- [ ] MFA settings
- [ ] MFA recovery behavior

## 6.2 Session security

- [ ] Session cookie flags
- [ ] CSRF behavior
- [ ] Session fixation protection
- [ ] Logout invalidates protected session
- [ ] Revoked session cannot access protected page
- [ ] Open old tab after logout
- [ ] Open deep link after logout

---

# 7. CUSTOMER MANAGEMENT

## 7.1 Create

- [ ] Open create
- [ ] Business/customer name
- [ ] Contact
- [ ] Phone
- [ ] Email
- [ ] Billing address
- [ ] Delivery address
- [ ] Credit limit
- [ ] Payment terms
- [ ] Status
- [ ] Salesman assignment
- [ ] Required validation
- [ ] Invalid formats
- [ ] Boundary values
- [ ] Save
- [ ] Duplicate/unique behavior
- [ ] Confirmation
- [ ] Created record appears
- [ ] Audit event created

## 7.2 Manage

- [ ] Edit
- [ ] Search
- [ ] Filters
- [ ] Detail
- [ ] Order history
- [ ] Payment history
- [ ] Outstanding
- [ ] Aging
- [ ] Statement
- [ ] Credits/refunds
- [ ] Adjustments

## 7.3 Lifecycle

- [ ] ACTIVE
- [ ] ON_HOLD
- [ ] INACTIVE
- [ ] New-order restriction
- [ ] Historical access preserved
- [ ] Assign salesman
- [ ] Reassign
- [ ] Unassign
- [ ] Salesman A can see assigned customer
- [ ] Salesman B cannot see customer
- [ ] Reassignment affects future scope correctly
- [ ] Existing historical records remain valid

---

# 8. PRODUCT / CATEGORY MANAGEMENT

## 8.1 Categories

- [ ] Create
- [ ] Edit
- [ ] Search
- [ ] Filter
- [ ] Assign products
- [ ] Category containing products handled safely
- [ ] Empty category
- [ ] Duplicate category handling
- [ ] Deactivation behavior

## 8.2 Products

- [ ] Create
- [ ] Edit
- [ ] SKU
- [ ] Name
- [ ] Description
- [ ] Category
- [ ] Cost
- [ ] MRP/List
- [ ] Selling price
- [ ] Minimum allowed price
- [ ] Tax
- [ ] Inventory
- [ ] Active/inactive
- [ ] Search
- [ ] Filter
- [ ] Pagination
- [ ] Status changes reflected in ordering

## 8.3 Product images

- [ ] Upload valid image
- [ ] Preview
- [ ] Replace
- [ ] Remove
- [ ] Drag/drop
- [ ] Invalid extension
- [ ] Invalid MIME
- [ ] Invalid magic bytes
- [ ] Oversize file
- [ ] Malformed image
- [ ] Dangerous filename
- [ ] Signed/private access works
- [ ] Unauthorized access blocked
- [ ] Product image renders in catalog
- [ ] Invoice does not show product image

---

# 9. PRICING / TAX

- [ ] Valid price selection
- [ ] MRP ceiling
- [ ] Minimum-price enforcement
- [ ] Price override permission
- [ ] Override reason
- [ ] Unauthorized override blocked
- [ ] Historical price preserved
- [ ] Product tax
- [ ] Mixed-tax order
- [ ] Line tax
- [ ] Tax snapshot
- [ ] Historical tax preserved
- [ ] Currency formatting
- [ ] Percentage formatting
- [ ] Boundary values
- [ ] Decimal precision
- [ ] Zero tax
- [ ] Tax change does not rewrite history

---

# 10. FLAGSHIP SALESMAN NEW ORDER

## 10.1 Customer

- [ ] Start new order
- [ ] Assigned customer list
- [ ] Assigned customer selectable
- [ ] Unauthorized customer unavailable
- [ ] Direct tampering with customer ID blocked
- [ ] ON_HOLD customer behavior
- [ ] INACTIVE customer behavior

## 10.2 Catalog

- [ ] Browse
- [ ] Search
- [ ] Category filter
- [ ] Add one product
- [ ] Add multiple products
- [ ] Product image
- [ ] Product status behavior
- [ ] Out-of-stock/availability behavior

## 10.3 Quantities/pricing

- [ ] Change quantity
- [ ] Quantity zero
- [ ] Negative quantity
- [ ] Decimal quantity if unsupported
- [ ] Excessive quantity
- [ ] Availability check
- [ ] Permitted selling price
- [ ] Minimum-price restriction
- [ ] Price override flow
- [ ] Tax
- [ ] Mixed-tax behavior
- [ ] Subtotal
- [ ] Grand total
- [ ] Recalculation after quantity change
- [ ] Recalculation after price change

## 10.4 Review

- [ ] Customer
- [ ] Products
- [ ] Quantities
- [ ] Prices
- [ ] Tax
- [ ] Totals
- [ ] Payment section
- [ ] Outstanding preview
- [ ] No stale values

## 10.5 Payment collection during order

- [ ] Cash
- [ ] Cheque
- [ ] Money Order
- [ ] Partial payment
- [ ] Pay in full
- [ ] Zero payment
- [ ] Negative amount
- [ ] Amount greater than order
- [ ] Cheque number
- [ ] Cheque date
- [ ] Bank
- [ ] Money Order number
- [ ] Issuer
- [ ] JPEG evidence
- [ ] Evidence preview
- [ ] Missing required evidence blocked
- [ ] Invalid evidence blocked
- [ ] Pending status
- [ ] Operational outstanding reflects approved pending-payment rule

## 10.6 Submission

- [ ] Submit
- [ ] Loading/submitting state
- [ ] Double-click submit
- [ ] Duplicate submit protection
- [ ] Confirmation
- [ ] Order detail
- [ ] Payment linked to order
- [ ] Payment linked to customer
- [ ] Recorder identity correct
- [ ] Order history updated
- [ ] Payment state correct
- [ ] Outstanding correct
- [ ] Audit event
- [ ] Correct notifications

---

# 11. DRAFT ORDERS

- [ ] Save draft
- [ ] Draft list
- [ ] Reopen
- [ ] Edit customer
- [ ] Edit product
- [ ] Edit quantity
- [ ] Edit permitted price
- [ ] Resume payment
- [ ] Submit draft
- [ ] Correct transition
- [ ] Cancel/delete where supported
- [ ] Unauthorized draft access blocked
- [ ] Draft does not post final accounting prematurely
- [ ] Draft survives refresh

---

# 12. ADMIN ORDER OPERATIONS

Queues:

- [ ] New orders
- [ ] Needs attention
- [ ] Processing
- [ ] Delivery
- [ ] Adjustments
- [ ] Completed
- [ ] Rejected/cancelled
- [ ] All/search history

Review:

- [ ] Customer
- [ ] Items
- [ ] Quantity
- [ ] Pricing
- [ ] Tax
- [ ] Payment
- [ ] Financial summary
- [ ] Delivery
- [ ] Timeline
- [ ] Audit

Actions:

- [ ] Approve
- [ ] Reject
- [ ] Rejection reason
- [ ] Invalid state
- [ ] Duplicate action
- [ ] Inventory reservation
- [ ] Correct fulfillment state
- [ ] Stale/concurrent approval behavior

---

# 13. PAYMENTS — COMPLETE LIFECYCLE

## 13.1 Creation

- [ ] Payment created in new order
- [ ] Cash
- [ ] Cheque
- [ ] Money Order
- [ ] Evidence
- [ ] Order linkage
- [ ] Customer linkage
- [ ] Recorder identity
- [ ] Pending state

## 13.2 Verification workspace

- [ ] Pending queue
- [ ] Verified queue
- [ ] Rejected queue
- [ ] Reversed queue
- [ ] All queue
- [ ] Search
- [ ] Filters
- [ ] Open payment
- [ ] Evidence preview
- [ ] Verify
- [ ] Reject
- [ ] Rejection reason
- [ ] Resubmission
- [ ] Maker-checker
- [ ] Same-user verify prohibited where policy requires
- [ ] Duplicate verification protected
- [ ] Correct state transition

## 13.3 Financial effects

- [ ] Pending payment included according to approved rule
- [ ] No double counting
- [ ] Operational outstanding correct
- [ ] AR correct
- [ ] GL treatment correct
- [ ] Payment history immutable
- [ ] Verification changes financial presentation correctly
- [ ] Reversal behavior correct
- [ ] Repeated reversal blocked
- [ ] Excess reversal blocked

## 13.4 File/evidence security

- [ ] Private object
- [ ] No public URL
- [ ] Signed preview works
- [ ] Signed URL expires
- [ ] Unauthorized evidence blocked
- [ ] Cross-customer evidence blocked
- [ ] Cross-salesman evidence blocked
- [ ] Evidence metadata correct
- [ ] Failed upload cleanup
- [ ] No credential leakage

---

# 14. ACCOUNTS RECEIVABLE — DEEPEST VERIFICATION

## 14.1 Dashboard

- [ ] Total AR
- [ ] Customer count
- [ ] Current
- [ ] 1–30
- [ ] 31–60
- [ ] 61–90
- [ ] 91+
- [ ] Customer rows
- [ ] Pending payment amounts
- [ ] Operational outstanding
- [ ] Non-zero data when transactions exist
- [ ] Empty state when no balances exist
- [ ] Search
- [ ] Filter
- [ ] Reference-date behavior

## 14.2 Customer AR

- [ ] Customer balance
- [ ] Charges
- [ ] Payments
- [ ] Pending payments
- [ ] Verified payments
- [ ] Credits
- [ ] Refunds
- [ ] Adjustments
- [ ] Running balance
- [ ] Chronological transaction history

## 14.3 Statement

- [ ] Opens without 500
- [ ] Correct dates
- [ ] Opening balance
- [ ] Orders/invoices
- [ ] Payments
- [ ] Pending payment presentation
- [ ] Credits
- [ ] Refunds
- [ ] Adjustments
- [ ] Running balance
- [ ] Closing balance
- [ ] Date presets
- [ ] Custom range
- [ ] Print
- [ ] Export where supported

## 14.4 AR reconciliation

For a known transaction:

- [ ] Order outstanding = operational AR
- [ ] Customer balance = AR
- [ ] Statement = AR
- [ ] Aging buckets = balance
- [ ] Pending payment included once
- [ ] Pending → verified transfer correct
- [ ] Credit note effect correct
- [ ] Refund effect correct
- [ ] Adjustment effect correct
- [ ] No hidden transactions
- [ ] No duplicated transactions

---

# 15. ACCOUNTS PAYABLE

- [ ] Supplier list
- [ ] Supplier detail
- [ ] Bills
- [ ] Payments
- [ ] Outstanding
- [ ] History
- [ ] Search
- [ ] Filters
- [ ] Totals
- [ ] Permissions
- [ ] Invalid direct access
- [ ] Accounting consistency

---

# 16. ADJUSTMENTS / QUANTITY ALLOCATION

## 16.1 Request/review

- [ ] Request
- [ ] Item
- [ ] Quantity
- [ ] Reason
- [ ] Notes
- [ ] Review queue
- [ ] Original quantity
- [ ] Current allocation
- [ ] Inventory context
- [ ] Tax impact
- [ ] Financial impact

## 16.2 Actions

- [ ] Approve
- [ ] Reject
- [ ] Valid approval succeeds
- [ ] False 409 absent
- [ ] Real stale conflict protected
- [ ] Duplicate action protected
- [ ] Authorization

## 16.3 Integrity

- [ ] Original ordered quantity immutable
- [ ] Cancelled quantity correct
- [ ] Fulfillable quantity correct
- [ ] Multiple adjustments
- [ ] Allocation correct
- [ ] Inventory impact
- [ ] Tax impact
- [ ] Financial impact
- [ ] Audit trail

---

# 17. INVENTORY / WAREHOUSE

- [ ] Inventory dashboard
- [ ] On hand
- [ ] Reserved
- [ ] Available
- [ ] Damaged
- [ ] Movements
- [ ] Fulfillment
- [ ] Picking
- [ ] Processing
- [ ] Exceptions
- [ ] Damage handling
- [ ] Inventory adjustments
- [ ] Order-linked allocation

Integrity:

- [ ] Available cannot become logically negative
- [ ] Damaged stock not sellable
- [ ] Reservations tied to orders
- [ ] Partial cancellation releases correct quantity
- [ ] Concurrent allocation protected
- [ ] Stock exception workflow correct
- [ ] Audit coverage

---

# 18. DELIVERY

- [ ] Delivery dashboard
- [ ] Assigned list
- [ ] Detail
- [ ] Assign
- [ ] Accept
- [ ] Pickup
- [ ] Out for delivery
- [ ] Delivered
- [ ] Failed
- [ ] Failure reason
- [ ] Reschedule
- [ ] Return to warehouse
- [ ] History
- [ ] Current deliverable quantity
- [ ] Cancelled quantity excluded
- [ ] Financial restrictions enforced

Evidence:

- [ ] Signature capture
- [ ] Signature preview
- [ ] POD upload
- [ ] POD preview
- [ ] Invalid file
- [ ] Oversize file
- [ ] Private access
- [ ] Scope protection

---

# 19. RETURNS

- [ ] Return request
- [ ] Eligible quantity
- [ ] Review
- [ ] Inspection
- [ ] Approved quantity
- [ ] Rejected quantity
- [ ] Inventory disposition
- [ ] Financial consequence
- [ ] Credit/refund connection
- [ ] Evidence
- [ ] Audit
- [ ] Historical order preserved

---

# 20. CREDITS / REFUNDS

- [ ] Eligibility
- [ ] Credit note
- [ ] Refund request
- [ ] Approval
- [ ] Processing
- [ ] Duplicate protection
- [ ] Amount validation
- [ ] Excess refund blocked
- [ ] AR effect
- [ ] Accounting effect
- [ ] Reversal/history
- [ ] Authorization
- [ ] Maker-checker where applicable

---

# 21. ACCOUNTING

## 21.1 Chart of Accounts

- [ ] Accounts visible
- [ ] Account types correct
- [ ] Codes correct
- [ ] Duplicate account handling
- [ ] Permissions

## 21.2 Journals / GL

- [ ] Journal entries
- [ ] Journal lines
- [ ] Source transaction traceability
- [ ] Debits = credits
- [ ] Posted status
- [ ] Historical immutability
- [ ] Reversal behavior
- [ ] No duplicate posting
- [ ] Correct dates

## 21.3 Trial Balance

- [ ] Correct totals
- [ ] Debit/credit equality
- [ ] Period filtering
- [ ] Account drilldown
- [ ] Empty period

## 21.4 Profit & Loss

For a known period containing activity:

- [ ] Revenue reconciles to GL
- [ ] Discounts reconcile
- [ ] COGS reconciles
- [ ] Expenses reconcile
- [ ] Gross profit correct
- [ ] Net operating income correct
- [ ] Date boundaries correct
- [ ] Empty period returns zero
- [ ] Reversals handled
- [ ] Unposted entries excluded correctly

## 21.5 Balance Sheet

- [ ] Assets
- [ ] Liabilities
- [ ] Equity
- [ ] AR
- [ ] AP
- [ ] Cash
- [ ] Balancing equation
- [ ] Period/date semantics

## 21.6 Cash reconciliation

- [ ] Cash movements
- [ ] Payment link
- [ ] Reconciliation state
- [ ] Difference detection
- [ ] Audit trail

---

# 22. REPORTING / ANALYTICS

- [ ] Sales reports
- [ ] Customer reports
- [ ] Salesman performance
- [ ] Inventory reports
- [ ] Delivery reports
- [ ] Accounting reports
- [ ] Date filters
- [ ] Other filters
- [ ] Totals
- [ ] Drilldown
- [ ] Export
- [ ] Large data behavior
- [ ] Permission enforcement

Salesman:

- [ ] Organization-wide analytics hidden
- [ ] Direct URL blocked
- [ ] Data limited to authorized scope

---

# 23. INVOICES / DOCUMENTS

- [ ] Invoice availability
- [ ] Preview
- [ ] Print
- [ ] PDF/download
- [ ] Historical reopen/reprint
- [ ] Invoice number
- [ ] Customer details
- [ ] Line items
- [ ] Tax
- [ ] Totals
- [ ] Payment information
- [ ] No product images
- [ ] Correct layout
- [ ] Immutable historical values
- [ ] Authorized access
- [ ] Unauthorized access blocked
- [ ] Private S3 document access
- [ ] Signed URL expiry

---

# 24. NOTIFICATIONS

- [ ] Notification center
- [ ] Unread state
- [ ] Mark read
- [ ] Order events
- [ ] Payment events
- [ ] Adjustment events
- [ ] Delivery events
- [ ] Preferences
- [ ] Role-appropriate visibility
- [ ] No sensitive leakage
- [ ] Duplicate notification protection where applicable

---

# 25. AUDIT LOGS / SECURITY LOGGING

Verify:

- [ ] Login
- [ ] Failed login
- [ ] Customer changes
- [ ] Product changes
- [ ] Price changes
- [ ] Tax changes
- [ ] Order creation
- [ ] Approval/rejection
- [ ] Adjustments
- [ ] Payment creation
- [ ] Payment verification
- [ ] Payment reversal
- [ ] Returns
- [ ] Credits/refunds
- [ ] Inventory changes
- [ ] Delivery changes
- [ ] Permission changes
- [ ] Accounting posting/reversal

Audit record quality:

- [ ] Actor
- [ ] Role
- [ ] Timestamp
- [ ] Entity/entity ID
- [ ] Before/after where applicable
- [ ] Reason
- [ ] No secrets
- [ ] No passwords
- [ ] No TOTP
- [ ] No signed URLs
- [ ] Historical records preserved

---

# 26. ROLE / IDOR CROSS-CHECK

Create at least two independent resource scopes where possible.

## Salesman

- [ ] Cannot view another Salesman's customer
- [ ] Cannot view another Salesman's order
- [ ] Cannot verify payments
- [ ] Cannot access Admin analytics
- [ ] Cannot access audit logs
- [ ] Cannot alter unauthorized prices
- [ ] Cannot access another customer's evidence

## Delivery Partner

- [ ] Cannot view unrelated delivery
- [ ] Cannot alter financial data
- [ ] Cannot access accounting
- [ ] Cannot access payment evidence
- [ ] Cannot access another driver's POD/signature

## Warehouse

- [ ] Cannot approve Admin-controlled adjustment
- [ ] Cannot alter protected financial state
- [ ] Cannot access unauthorized customer financial data

## Accountant

- [ ] Correct financial access
- [ ] No unauthorized operational mutations

## Admin / Super Admin

- [ ] Correct hierarchy
- [ ] No accidental overrestriction
- [ ] No accidental privilege crossover

## URL tampering

- [ ] Change customer ID
- [ ] Change order ID
- [ ] Change payment ID
- [ ] Change invoice ID
- [ ] Change delivery ID
- [ ] Change return ID
- [ ] Change adjustment ID
- [ ] Change object/document reference
- [ ] Attempt predictable S3 key
- [ ] Attempt direct download route
- [ ] Attempt preview route

---

# 27. RATE LIMITING / ABUSE

Execute targeted abuse-safe tests:

- [ ] Login throttling
- [ ] MFA throttling
- [ ] Password reset throttling
- [ ] Payment mutation throttling
- [ ] Evidence upload throttling
- [ ] Order mutation throttling
- [ ] Inventory mutation throttling
- [ ] Delivery completion throttling
- [ ] Invoice generation throttling
- [ ] Export throttling

For throttled cases:

- [ ] Correct 429
- [ ] Retry information where appropriate
- [ ] Normal UX not incorrectly throttled
- [ ] Authenticated identity used appropriately
- [ ] Shared-IP users not incorrectly blocked where avoidable

---

# 28. FILE UPLOAD SECURITY

For every file-upload workflow:

- [ ] Authorization before upload
- [ ] Resource-scope check
- [ ] File size limit
- [ ] Actual MIME validation
- [ ] Extension validation
- [ ] Magic-byte/content validation
- [ ] Image parser validation
- [ ] Dimension validation where appropriate
- [ ] Dangerous SVG/XML blocked where required
- [ ] Malformed PDF behavior
- [ ] Filename sanitization
- [ ] Path traversal rejected
- [ ] UUID object key
- [ ] Private storage
- [ ] Signed access
- [ ] No permanent public URL
- [ ] Failure cleanup
- [ ] Orphan handling
- [ ] Duplicate upload behavior

---

# 29. ERROR HANDLING / INFORMATION LEAKAGE

Trigger representative failures safely:

- [ ] 400
- [ ] 401
- [ ] 403
- [ ] 404
- [ ] 409
- [ ] 422
- [ ] 429
- [ ] 500
- [ ] S3 failure
- [ ] validation failure
- [ ] concurrency conflict

Confirm no user-facing leakage of:

- [ ] stack traces
- [ ] SQL
- [ ] SQLSTATE
- [ ] filesystem paths
- [ ] AWS credentials
- [ ] bucket internals where sensitive
- [ ] class names where unnecessary
- [ ] source locations
- [ ] environment variables
- [ ] internal ticket IDs
- [ ] session tokens
- [ ] signed URLs

---

# 30. CONSOLE / NETWORK / RUNTIME

During ALL workflows capture:

- [ ] JS exceptions
- [ ] React errors
- [ ] console errors
- [ ] console warnings
- [ ] 400
- [ ] 401
- [ ] 403
- [ ] 404
- [ ] 409
- [ ] 422
- [ ] 429
- [ ] 500
- [ ] failed Inertia requests
- [ ] failed assets
- [ ] Vite/HMR issues
- [ ] CORS
- [ ] ERR_FAILED
- [ ] ERR_EMPTY_RESPONSE
- [ ] unexpected redirects
- [ ] blank rendering
- [ ] partial rendering
- [ ] broken images
- [ ] failed S3 signed URLs

Classify each observation as:

- [ ] Genuine defect
- [ ] Expected validation
- [ ] Expected authorization
- [ ] Expected concurrency response
- [ ] Framework/dev noise
- [ ] Browser/environment issue
- [ ] Duplicate symptom

---

# 31. RECENT-FIX REGRESSION AUDIT

Explicitly re-run:

- [ ] Salesman dashboard
- [ ] Salesman payment-in-order
- [ ] Payment verification
- [ ] Pending-payment outstanding
- [ ] AR derived ledger
- [ ] AR PostgreSQL transaction-boundary repair
- [ ] Adjustment 500 repair
- [ ] Adjustment 409 repair
- [ ] Dashboard repair
- [ ] Vite/CORS repair
- [ ] Internal Phase/Epic cleanup
- [ ] Security hardening
- [ ] S3 application integration
- [ ] Product image storage
- [ ] Payment evidence private preview
- [ ] Delivery signature/POD storage
- [ ] Interactive browser infrastructure
- [ ] Chrome DevTools MCP coexistence

For each:

- [ ] Related commit identified
- [ ] Normal workflow still works
- [ ] Edge case still works
- [ ] Authorization still works
- [ ] Responsive behavior still works
- [ ] No regression introduced

---

# 32. END-TO-END FINANCIAL GOLDEN SCENARIO

Create one controlled scenario and trace it through the entire system.

## Phase A — customer

- [ ] Customer selected/created
- [ ] Salesman assignment confirmed

## Phase B — order

- [ ] Salesman creates order
- [ ] Multiple products
- [ ] Quantities
- [ ] Price
- [ ] Tax
- [ ] Total
- [ ] Order submitted

## Phase C — payment

- [ ] Partial payment
- [ ] Pending state
- [ ] Evidence where required
- [ ] Operational outstanding

## Phase D — admin

- [ ] Payment appears in verification
- [ ] Evidence view works
- [ ] Maker-checker enforced
- [ ] Payment verified

## Phase E — financial

- [ ] Verified state
- [ ] Outstanding recalculates
- [ ] AR recalculates
- [ ] Statement updates
- [ ] Invoice/order financial state correct
- [ ] GL posting correct
- [ ] Trial balance remains balanced
- [ ] P&L/BS effects correct

## Phase F — second payment

- [ ] Second payment
- [ ] Fully paid
- [ ] No double counting

## Phase G — post-payment adjustment

- [ ] Adjustment requested
- [ ] Approval
- [ ] Inventory consequence
- [ ] Tax consequence
- [ ] Financial consequence
- [ ] Audit trail

## Phase H — return / credit / refund

- [ ] Return
- [ ] Credit/refund consequence
- [ ] AR consequence
- [ ] Accounting consequence
- [ ] No duplicated effect

---

# 33. CONCURRENCY / IDEMPOTENCY / REPLAY

Where practical use two browser tabs/sessions or repeated actions to verify:

- [ ] Duplicate order submit
- [ ] Duplicate payment submit
- [ ] Duplicate verification
- [ ] Duplicate refund processing
- [ ] Duplicate adjustment approval
- [ ] Concurrent inventory allocation
- [ ] Concurrent delivery completion
- [ ] Stale order approval
- [ ] Stale adjustment
- [ ] Role change during active session
- [ ] Customer reassignment during order
- [ ] Product deactivation during order
- [ ] Price/tax change during order
- [ ] Payment state change during order

Expected outcomes must match domain rules and never silently duplicate financial/inventory effects.

---

# 34. DEEP NEGATIVE-TEST MATRIX

For representative sensitive endpoints attempt:

- [ ] missing required field
- [ ] wrong type
- [ ] negative value
- [ ] zero where invalid
- [ ] excessively large value
- [ ] invalid enum
- [ ] malformed date
- [ ] invalid ID
- [ ] another user's ID
- [ ] another customer's ID
- [ ] stale version
- [ ] repeated request
- [ ] unauthorized role
- [ ] suspended user
- [ ] malformed upload
- [ ] oversized upload
- [ ] invalid file content

Expected behavior must be deliberate, secure, and user-readable.

---

# 35. CLIENT-FACING DEMO READINESS

## Catalogue

- [ ] Categories populated
- [ ] Products populated
- [ ] Product images present
- [ ] Image quality consistent
- [ ] Prices realistic
- [ ] Stock realistic
- [ ] No confidential data

## Demo workflows

- [ ] Admin login
- [ ] Salesman login
- [ ] Create order
- [ ] Payment
- [ ] Verification
- [ ] AR
- [ ] Invoice
- [ ] Warehouse
- [ ] Delivery
- [ ] Return/refund

## Presentation quality

- [ ] No developer text
- [ ] No stale branding
- [ ] No broken image
- [ ] No empty demo screen unless intentionally empty
- [ ] No console errors caused by normal demo flow
- [ ] No unexpected 500
- [ ] Mobile and desktop acceptable

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

- [ ] Duplicate symptoms merged
- [ ] Common backend root causes grouped
- [ ] Common frontend root causes grouped
- [ ] Financial bugs isolated
- [ ] Security bugs isolated
- [ ] Inventory bugs isolated
- [ ] Payment bugs isolated
- [ ] Browser infrastructure bugs isolated
- [ ] Cosmetic issues separated
- [ ] Recent-fix regressions linked

Recommended fix-wave categories:

- [ ] P0/P1 security
- [ ] Financial integrity
- [ ] Payment
- [ ] AR/AP
- [ ] Inventory/adjustments
- [ ] Orders
- [ ] Delivery/returns
- [ ] Authorization/IDOR
- [ ] Frontend/runtime
- [ ] Responsive/accessibility
- [ ] UX/polish
- [ ] Dead code/stale artifacts

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

- [ ] Every applicable checklist item has PASS / BUG / N/A / BLOCKED / NEEDS-CLARIFICATION
- [ ] No required item remains unchecked
- [ ] Every PASS has evidence
- [ ] Every BUG has evidence
- [ ] All six roles were actually exercised where applicable
- [ ] Both salesman accounts were scope-tested
- [ ] AR received deepest coverage
- [ ] Financial golden scenario completed
- [ ] Payment lifecycle completed
- [ ] Order lifecycle completed
- [ ] Adjustment lifecycle completed
- [ ] Inventory lifecycle completed
- [ ] Delivery lifecycle completed
- [ ] Returns/credits/refunds completed
- [ ] Accounting reconciled
- [ ] Responsive critical workflows completed
- [ ] Console review completed
- [ ] Network review completed
- [ ] Security/IDOR review completed
- [ ] Recent fixes rechecked
- [ ] No false PASS caused by route-loading
- [ ] No silent skips
- [ ] All blocked items documented
- [ ] BUGLIST finalized
- [ ] Root-cause groups finalized
- [ ] Recommended fix batches finalized

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

