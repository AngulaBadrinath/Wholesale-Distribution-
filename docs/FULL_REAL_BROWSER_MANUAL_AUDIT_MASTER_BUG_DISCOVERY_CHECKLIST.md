# FULL REAL-BROWSER MANUAL AUDIT + MASTER BUG DISCOVERY
## Whole-Project Master Checklist

Purpose: exhaustive read-only browser verification of the Wholesale Distribution Management System using the permanent local Chrome/Chromium + Playwright harness.

Status:
- [ ] Not started
- [~] In progress
- [x] Passed
- [!] Failed / Bug found
- [-] N/A
- [?] Needs clarification

RULE: A workflow is not "passed" just because its route loads. Execute the real user actions and inspect the resulting UI, network, console, data, and state.

---

# 1. AUDIT CONTROL & ENVIRONMENT

- [ ] Start/end time recorded
- [ ] Application/base URL recorded
- [ ] Environment confirmed local/non-production
- [ ] Chrome/Chromium name/version recorded
- [ ] Browser executable path recorded
- [ ] Playwright local-browser resolver confirmed
- [ ] Authentication/test credentials confirmed
- [ ] Test data/seed state documented
- [ ] Screenshot directory documented
- [ ] Video/trace directory documented
- [ ] Console capture enabled
- [ ] Network/HTTP capture enabled
- [ ] Browser diagnostics working
- [ ] No code changes made during discovery

# 2. BROWSER / RENDERING / RESPONSIVE

## Browser
- [ ] Real Chrome/Chromium launches
- [ ] JS execution
- [ ] CSS rendering
- [ ] Inertia navigation
- [ ] Forms/interactions
- [ ] Modals/dialogs
- [ ] Drawers/sheets
- [ ] Select/combobox/date picker
- [ ] File upload/preview
- [ ] Back/forward
- [ ] Refresh
- [ ] Deep links
- [ ] Error pages

## Viewports
- [ ] 320
- [ ] 375
- [ ] 390
- [ ] 430
- [ ] 640
- [ ] 768
- [ ] 820
- [ ] 1024
- [ ] 1280
- [ ] 1440
- [ ] 1920

For critical workflows:
- [ ] Mobile screenshot
- [ ] Tablet screenshot
- [ ] Desktop screenshot

Check:
- [ ] Overflow
- [ ] Clipping
- [ ] Broken tables/cards
- [ ] Sticky controls
- [ ] Modal positioning
- [ ] Text truncation
- [ ] Touch targets
- [ ] Keyboard/focus behavior
- [ ] Loading/empty/error/success states

# 3. AUTHENTICATION / SESSION / ACCESS

Roles:
- [ ] SUPER_ADMIN
- [ ] ADMIN
- [ ] ACCOUNTANT
- [ ] SALESMAN
- [ ] WAREHOUSE_MANAGER
- [ ] DELIVERY_PARTNER

For applicable roles:
- [ ] Login
- [ ] Invalid login
- [ ] Throttling
- [ ] Logout
- [ ] Session persistence
- [ ] Session revocation
- [ ] Suspended/disabled behavior
- [ ] Role routing
- [ ] Unauthorized route
- [ ] Direct URL access
- [ ] Permission denial
- [ ] Resource-scope denial
- [ ] IDOR attempt

# 4. GLOBAL APPLICATION SHELL

- [ ] Correct landing/dashboard
- [ ] Header
- [ ] Sidebar
- [ ] Mobile navigation
- [ ] Breadcrumbs
- [ ] Role-specific navigation
- [ ] Notification bell
- [ ] User/profile menu
- [ ] Search/filter controls
- [ ] Navigation links resolve
- [ ] No dead links
- [ ] No obsolete Phase/Epic development UI
- [ ] No foundation/demo content exposed in operational portals

# 5. CUSTOMER ONBOARDING & MANAGEMENT

## Create
- [ ] Open customer creation
- [ ] Required fields
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
- [ ] Save
- [ ] Confirmation
- [ ] Created record

## Manage
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

## Assignment/lifecycle
- [ ] Assign salesman
- [ ] Salesman A sees customer
- [ ] Salesman B cannot see customer
- [ ] Reassign
- [ ] Unassign
- [ ] ACTIVE
- [ ] ON_HOLD
- [ ] INACTIVE
- [ ] New-order restrictions
- [ ] Historical access preserved

# 6. SALESMAN PORTAL

## Dashboard
- [ ] Dashboard loads
- [ ] Assigned customers summary
- [ ] Order summary
- [ ] Recent orders
- [ ] Product/category access
- [ ] Relevant operational metrics
- [ ] No organization-wide sales analytics
- [ ] Order History separate and accessible

## Customer/product/order access
- [ ] Assigned customer list
- [ ] Search/filter
- [ ] Customer detail
- [ ] Catalog
- [ ] Product search/category
- [ ] Permitted pricing
- [ ] Customer scope enforced

# 7. PRODUCT & CATEGORY MANAGEMENT

## Categories
- [ ] Create
- [ ] Edit
- [ ] Search/filter
- [ ] Product assignment
- [ ] Category containing products handled safely

## Products
- [ ] Create
- [ ] Edit
- [ ] SKU
- [ ] Name/description
- [ ] Category
- [ ] Cost
- [ ] MRP/List
- [ ] Default selling price
- [ ] Minimum allowed price
- [ ] Tax
- [ ] Inventory
- [ ] Active/inactive
- [ ] Search/filter

## Images
- [ ] Upload
- [ ] Preview
- [ ] Replace
- [ ] Remove
- [ ] Drag/drop where supported
- [ ] Invalid file behavior
- [ ] Private/access-controlled behavior
- [ ] Invoice does not display product image

# 8. PRICING & TAX

- [ ] Valid price selection
- [ ] Minimum-price enforcement
- [ ] MRP ceiling
- [ ] Price override permission
- [ ] Override reason
- [ ] Historical transaction price preserved
- [ ] Product-level tax
- [ ] Mixed-tax order
- [ ] Line-level tax
- [ ] Tax snapshot
- [ ] Historical tax preserved
- [ ] Consistent currency/percentage formatting

# 9. FLAGSHIP — SALESMAN NEW ORDER

Execute end-to-end.

## Order creation
- [ ] Start new order
- [ ] Select assigned customer
- [ ] Unauthorized customer unavailable
- [ ] Browse catalog
- [ ] Search
- [ ] Category filter
- [ ] Add one product
- [ ] Add multiple products
- [ ] Change quantities
- [ ] Invalid quantity handling
- [ ] Availability display
- [ ] Select permitted price
- [ ] Tax
- [ ] Mixed-tax behavior
- [ ] Subtotal
- [ ] Grand total

## Review
- [ ] Customer
- [ ] Products
- [ ] Quantities
- [ ] Prices
- [ ] Tax
- [ ] Totals
- [ ] Payment section

## Payment during order
- [ ] Cash
- [ ] Cheque
- [ ] Money Order
- [ ] Partial payment
- [ ] Pay in full
- [ ] Amount validation
- [ ] Cheque number/date/bank
- [ ] Money Order number/issuer
- [ ] JPEG evidence
- [ ] Evidence preview
- [ ] Required-evidence validation
- [ ] Pending status
- [ ] Operational outstanding

## Submit
- [ ] Submit
- [ ] Confirmation
- [ ] Duplicate-submit behavior
- [ ] Order detail
- [ ] Payment linked to order
- [ ] Payment linked to customer
- [ ] Order history updated
- [ ] Correct payment/outstanding state

# 10. DRAFT ORDERS

- [ ] Save draft
- [ ] Draft list
- [ ] Reopen
- [ ] Edit customer
- [ ] Edit products
- [ ] Edit quantity
- [ ] Edit permitted price
- [ ] Resume payment
- [ ] Submit draft
- [ ] Correct state transition
- [ ] Cancel/delete where supported

# 11. ADMIN ORDER OPERATIONS

Queues:
- [ ] New Orders
- [ ] Needs Attention
- [ ] Processing
- [ ] Delivery
- [ ] Adjustments
- [ ] Completed
- [ ] Cancelled/Rejected
- [ ] All/Search History

Order review:
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
- [ ] Invalid state handling
- [ ] Duplicate action handling
- [ ] Correct inventory reservation
- [ ] Correct order/fulfillment state

# 12. PAYMENT — COMPLETE FLOW

## Salesman collection
- [ ] Payment recorded during New Order
- [ ] Cash
- [ ] Cheque
- [ ] Money Order
- [ ] Evidence
- [ ] Order/customer linkage
- [ ] Recorder identity
- [ ] Pending state

## Admin verification
- [ ] Verification workspace
- [ ] Pending queue
- [ ] Verified queue
- [ ] Rejected queue
- [ ] Reversed queue
- [ ] All queue
- [ ] Search/filter
- [ ] Open payment
- [ ] Evidence preview
- [ ] Verify
- [ ] Reject
- [ ] Rejection reason
- [ ] Correct/resubmit where supported
- [ ] Maker-checker
- [ ] Duplicate verification protection
- [ ] Correct state transition

## Financial effects
- [ ] Pending payment included according to approved rule
- [ ] No double counting
- [ ] Outstanding correct
- [ ] AR correct
- [ ] Accounting treatment correct
- [ ] Payment history preserved

# 13. ACCOUNTS RECEIVABLE — DEEPEST SECTION

## AR Dashboard
- [ ] Total AR
- [ ] Customer count
- [ ] Current
- [ ] 31–60
- [ ] 61–90
- [ ] 90+
- [ ] Customer rows
- [ ] Pending payment amounts
- [ ] Operational outstanding
- [ ] Non-zero data when transactions exist

## Customer AR
- [ ] Customer balance
- [ ] Charges
- [ ] Payments
- [ ] Pending payments
- [ ] Verified payments
- [ ] Credits
- [ ] Refunds
- [ ] Adjustments
- [ ] Running balance
- [ ] Transaction history

## Statement
- [ ] Opens without 500
- [ ] Correct dates
- [ ] Opening balance
- [ ] Orders/invoices
- [ ] Payments
- [ ] Pending-payment presentation
- [ ] Credits
- [ ] Refunds
- [ ] Adjustments
- [ ] Running balance
- [ ] Closing balance
- [ ] Date filters
- [ ] Print/export where supported

## AR consistency
- [ ] Order outstanding = AR operational balance
- [ ] Customer balance = AR
- [ ] Statement agrees with AR
- [ ] Aging agrees with balances
- [ ] Pending included once
- [ ] Pending → verified transfers correctly
- [ ] Credits/refunds/adjustments reconcile

# 14. ACCOUNTS PAYABLE

- [ ] Supplier list
- [ ] Supplier detail
- [ ] Bills
- [ ] Payments
- [ ] Outstanding
- [ ] History
- [ ] Search/filter
- [ ] Totals
- [ ] Permissions

# 15. ADJUSTMENTS & QUANTITY ALLOCATION

## Request/review
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

## Actions
- [ ] Approve
- [ ] Reject
- [ ] Valid approval succeeds
- [ ] False 409 absent
- [ ] Genuine stale conflict protected
- [ ] Duplicate action protected
- [ ] Authorization enforced

## Quantity integrity
- [ ] Original ordered quantity never rewritten
- [ ] Cancelled quantity valid
- [ ] Fulfillable quantity valid
- [ ] Multiple adjustments valid
- [ ] Allocation valid
- [ ] Inventory impact correct
- [ ] Tax impact correct
- [ ] Financial impact correct
- [ ] Audit trail correct

# 16. INVENTORY / WAREHOUSE

- [ ] Inventory dashboard
- [ ] On hand
- [ ] Reserved
- [ ] Available
- [ ] Damaged
- [ ] Movements
- [ ] Fulfillment work
- [ ] Picking
- [ ] Processing
- [ ] Stock exceptions
- [ ] Damage handling
- [ ] Inventory adjustments
- [ ] Order-linked allocation

Verify:
- [ ] Available cannot logically become negative
- [ ] Damaged stock not sellable
- [ ] Reservations tied to orders
- [ ] Partial cancellation releases correct quantity

# 17. DELIVERY

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

# 18. RETURNS

- [ ] Return request
- [ ] Eligible quantity
- [ ] Review
- [ ] Inspection
- [ ] Approved quantity
- [ ] Rejected quantity
- [ ] Inventory disposition
- [ ] Financial consequence
- [ ] Credit/refund connection
- [ ] Audit trail
- [ ] Historical order preserved

# 19. CREDITS & REFUNDS

- [ ] Eligibility
- [ ] Credit note
- [ ] Refund request
- [ ] Refund approval
- [ ] Refund processing
- [ ] Duplicate-processing protection
- [ ] Amount validation
- [ ] AR effect
- [ ] Accounting effect
- [ ] Reversal/history

# 20. ACCOUNTING

- [ ] Chart of Accounts
- [ ] Journal entries
- [ ] Journal lines
- [ ] General Ledger
- [ ] Trial Balance
- [ ] P&L
- [ ] Balance Sheet
- [ ] Accounts Receivable
- [ ] Accounts Payable
- [ ] Cash/reconciliation
- [ ] Reversal

Verify:
- [ ] Debits = credits
- [ ] Source transaction traceability
- [ ] No duplicate posting
- [ ] Posted history immutable
- [ ] Payment/AR/accounting consistency

# 21. REPORTING / ANALYTICS

Authorized roles:
- [ ] Sales reports
- [ ] Customer reports
- [ ] Salesman performance
- [ ] Inventory reports
- [ ] Delivery reports
- [ ] Accounting reports
- [ ] Date filters
- [ ] Other filters
- [ ] Totals
- [ ] Drill-down
- [ ] Export where supported

Salesman:
- [ ] Organization-wide analytics hidden
- [ ] Direct unauthorized access blocked

# 22. INVOICES / DOCUMENTS

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

# 23. NOTIFICATIONS

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

# 24. AUDIT / SECURITY LOGS

Verify audit coverage for:
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

Check:
- [ ] Actor
- [ ] Role
- [ ] Timestamp
- [ ] Entity/entity ID
- [ ] Before/after where applicable
- [ ] Reason where required
- [ ] No secrets/tokens/passwords
- [ ] Historical records preserved

# 25. ROLE / IDOR CROSS-CHECK

- [ ] Salesman cannot access another Salesman's customer
- [ ] Salesman cannot access another Salesman's order
- [ ] Salesman cannot verify payments
- [ ] Salesman cannot access admin analytics
- [ ] Delivery cannot modify financial data
- [ ] Warehouse cannot bypass Admin-controlled adjustment
- [ ] Accountant restrictions correct
- [ ] Admin/Super Admin boundaries correct
- [ ] Changing IDs cannot bypass resource scope

# 26. CONSOLE / NETWORK / SERVER DIAGNOSTICS

During all walkthroughs capture:

- [ ] JS exceptions
- [ ] React runtime errors
- [ ] Console errors
- [ ] Console warnings
- [ ] HTTP 400
- [ ] HTTP 401
- [ ] HTTP 403
- [ ] HTTP 404
- [ ] HTTP 409
- [ ] HTTP 422
- [ ] HTTP 500
- [ ] Failed Inertia requests
- [ ] Failed assets
- [ ] Vite/HMR failures
- [ ] CORS
- [ ] ERR_FAILED
- [ ] ERR_EMPTY_RESPONSE
- [ ] Unexpected redirects
- [ ] Blank/partial rendering

Classify each:
- [ ] Genuine application defect
- [ ] Expected business validation
- [ ] Expected authorization
- [ ] Framework/development noise
- [ ] Browser/environment issue
- [ ] Duplicate symptom

# 27. RECENT-FIX REGRESSION REVIEW

Explicitly inspect regressions related to:
- [ ] Salesman dashboard
- [ ] Salesman payment-in-order
- [ ] Payment verification
- [ ] Pending-payment outstanding
- [ ] AR derivation
- [ ] AR read-path repair
- [ ] Adjustment 500/409
- [ ] Dashboard controller
- [ ] Vite/CORS
- [ ] Phase/Epic cleanup
- [ ] Browser verification infrastructure

For each suspected regression:
- [ ] Related commit identified
- [ ] Affected module identified
- [ ] Grouped with root cause

# 28. END-TO-END FINANCIAL SCENARIO

Execute a realistic scenario:

- [ ] Create/select customer
- [ ] Assign salesman
- [ ] Salesman creates order
- [ ] Add products
- [ ] Tax
- [ ] Partial payment
- [ ] Pending state
- [ ] Operational outstanding
- [ ] Admin sees payment
- [ ] Admin verifies
- [ ] Verified state
- [ ] Outstanding remains correct
- [ ] AR updates
- [ ] Statement updates
- [ ] Invoice/order financial state correct
- [ ] Accounting state correct

Then:
- [ ] Second payment
- [ ] Fully paid
- [ ] Adjustment after payment
- [ ] Credit/refund consequence
- [ ] No duplicate financial effect

# 29. MASTER BUG DISCOVERY

For each genuine defect record:

- BUG ID
- Severity
- Risk
- Role
- Domain
- Route
- Workflow
- Observed
- Expected
- Exact reproduction
- Screenshot/evidence
- Console error
- HTTP/network error
- Likely root cause
- Related recent fix/commit
- Workflow impact
- Data impact
- Financial impact
- Security impact
- Recommended fix batch
- Status

Severity:
- P0 = severe security/data-loss/financial/system blocker
- P1 = critical business workflow failure
- P2 = important functional defect
- P3 = minor functional/UX defect
- P4 = cosmetic/deferred enhancement

Risk:
- R0 = security/financial/inventory/history
- R1 = core workflow
- R2 = operational
- R3 = reporting/UX
- R4 = cosmetic

Do not classify expected validation, intentional denial, or known framework noise as bugs.

# 30. BUG ROOT-CAUSE GROUPING

- [ ] Duplicate symptoms merged
- [ ] Shared root causes identified
- [ ] Recent-fix regressions identified
- [ ] Financial bugs isolated
- [ ] Security bugs isolated
- [ ] Workflow bugs isolated
- [ ] Cosmetic issues separated

Recommended fix batches should be based on root cause, e.g.:
- [ ] AR/financial domain
- [ ] Payment workflow
- [ ] Adjustment/inventory workflow
- [ ] Dashboard/navigation
- [ ] Frontend/browser infrastructure
- [ ] UX/polish

# 31. AUDIT COMPLETION

- [ ] All roles reviewed
- [ ] AR received deepest coverage
- [ ] Customer onboarding covered
- [ ] New-order workflow covered
- [ ] Payment collection/verification covered
- [ ] Order approval covered
- [ ] Adjustments covered
- [ ] Inventory covered
- [ ] Delivery covered
- [ ] Returns covered
- [ ] Credits/refunds covered
- [ ] AP covered
- [ ] Accounting covered
- [ ] Reporting covered
- [ ] Invoices covered
- [ ] Notifications covered
- [ ] Audit/security covered
- [ ] Responsive coverage completed
- [ ] Console/network review completed
- [ ] Recent-fix regression review completed
- [ ] BUGLIST.md finalized
- [ ] Fix batches finalized
- [ ] Skipped items documented

FINAL STATE:
- [ ] AUDIT COMPLETE — NO BUGS FOUND
- [ ] AUDIT COMPLETE — BUGS FOUND
- [ ] AUDIT INCOMPLETE — BLOCKED

DO NOT FIX BUGS DURING DISCOVERY.
DO NOT COMMIT/PUSH APPLICATION FIXES.
