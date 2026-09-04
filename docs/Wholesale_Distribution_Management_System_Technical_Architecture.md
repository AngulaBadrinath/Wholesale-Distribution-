# Technical Architecture Document (TAD)

## Wholesale Distribution Management System

**Document Type:** Technical Architecture Document (TAD)  
**Working Product Name:** Wholesale Distribution Management System  
**Product Name Status:** Temporary / Replaceable  
**Document Version:** 1.0  
**Status:** Development Baseline  
**Depends On:** PRD v1.0  
**Primary Market:** United States  
**Currency:** USD  
**Initial Customer Scale:** Approximately 500–700 customers

---

# 0. Document Control & Architecture Rules

## 0.1 Purpose

This document translates the approved Product Requirements Document into an implementable technical architecture.

It defines:

- technology stack;
- runtime environment;
- application architecture;
- module boundaries;
- data architecture;
- domain/entity relationships;
- transaction boundaries;
- inventory concurrency strategy;
- payment/file storage architecture;
- tax architecture;
- invoice/document architecture;
- authentication and authorization foundations;
- frontend architecture;
- background jobs and events;
- caching;
- API/Inertia interaction;
- environments;
- CI/CD;
- deployment;
- observability;
- backups;
- testing architecture;
- coding and repository conventions;
- extensibility rules.

This document must not silently change confirmed business requirements from the PRD.

## 0.2 Source-of-truth relationship

The approved PRD remains the business/product source of truth.

This document is the technical source of truth for implementation.

When technical constraints require a business behavior change, do not silently alter the system. Record the conflict, update the PRD first if necessary, then update this document.

## 0.3 Core architectural principles

The implementation must follow these principles:

1. **Shared transaction core**
2. **Modular monolith**
3. **PostgreSQL as the transactional source of truth**
4. **Non-destructive business history**
5. **Quantity-based allocation**
6. **Explicit adjustment records**
7. **Atomic inventory reservation**
8. **Server-side authorization**
9. **Historical transaction snapshots**
10. **Idempotent critical commands**
11. **Private object storage for business documents**
12. **Asynchronous secondary effects**
13. **Configurable product identity**
14. **Build for current scope without premature infrastructure complexity**

---

# 1. Architecture Decision Summary

## 1.1 Recommended stack

| Layer | Technology |
|---|---|
| Backend framework | Laravel 13 |
| Backend language | PHP 8.5 |
| Frontend | React 19.2 |
| Frontend language | TypeScript |
| Server/client bridge | Inertia 3 |
| CSS | Tailwind CSS 4 |
| UI components | shadcn/ui |
| Build tooling | Vite |
| Primary database | PostgreSQL 18 |
| Cache | Redis |
| Queue backend | Redis |
| File/object storage | Amazon S3 |
| Local environment | Docker / Docker Compose |
| Version control | Git / GitHub |
| CI/CD | GitHub Actions |
| Error monitoring | Sentry |
| Infrastructure monitoring | AWS CloudWatch |
| Production DNS | Amazon Route 53 |
| Production application | AWS EC2 initially; container-ready design for future ECS |
| Production database | Amazon RDS for PostgreSQL |
| Production Redis | Amazon ElastiCache for Redis |
| PDF/document rendering | Chromium-based renderer |
| Email provider | Amazon SES when transactional email is enabled |

## 1.2 Why this stack

This is a transaction-heavy business application with an initial scale of approximately 500–700 customers. The primary engineering challenge is data integrity and operational correctness, not internet-scale distributed computing.

Therefore:

- use a relational database;
- keep the backend as one deployable modular application;
- keep the frontend modern and componentized;
- use Redis only where it adds operational value;
- store business files outside PostgreSQL;
- avoid microservices and Kubernetes in V1.

---

# 2. High-Level System Architecture

```text
                         INTERNET
                            |
                    Route 53 / DNS
                            |
                    HTTPS / TLS
                            |
                 ┌──────────┴──────────┐
                 |                     |
          Salesman Portal         Admin Portal
                 |                     |
                 └──────────┬──────────┘
                            |
                    Delivery Portal
                            |
                            v
                 ┌────────────────────┐
                 │ Laravel 13 App     │
                 │ Modular Monolith   │
                 │                    │
                 │ Auth / Policies    │
                 │ Application Layer  │
                 │ Domain Modules     │
                 │ Query Services     │
                 │ Jobs / Events      │
                 └───────┬─────┬──────┘
                         |     |
                         |     +------------------+
                         |                        |
                         v                        v
                  PostgreSQL 18                 Redis
                  Source of Truth          Cache / Queue / Locks
                         |
                         v
                       S3
              Private files/documents
```

The application is one logical system with three role-oriented portals. The portals must never maintain separate copies of the order domain.

---

# 3. Architectural Style

## 3.1 Modular monolith

The application is a **modular monolith**.

A modular monolith means:

- one application;
- one deployment unit initially;
- one primary relational database;
- explicit internal module boundaries;
- independent domain/application services within the application;
- no unnecessary network calls between internal modules.

## 3.2 Why not microservices

Microservices are not justified by current scale.

They would introduce:

- distributed transactions;
- multiple deployments;
- service discovery;
- network failure modes;
- API versioning;
- distributed tracing;
- service authentication;
- more operational overhead.

The product should first solve:

- order correctness;
- inventory concurrency;
- payment reconciliation;
- tax accuracy;
- financial history;
- auditability.

A future service extraction remains possible if a module later becomes operationally independent.

---

# 4. Application Module Map

Recommended logical modules:

```text
Identity & Access
Customers
Salesmen
Products
Pricing
Tax
Orders
Order Adjustments
Inventory
Warehouse
Delivery
Payments
Documents / Invoicing
Returns
Credits & Refunds
Receivables
Purchasing
Accounting
Reporting
Notifications
Audit
System Settings
```

## 4.1 Module rule

A module owns its domain behavior.

Other modules must interact through:

- application services;
- domain services;
- explicit commands;
- domain events;
- query/read models where appropriate.

Do not allow arbitrary controllers to directly modify unrelated module tables.

---

# 5. Layered Backend Architecture

Recommended dependency direction:

```text
HTTP / Inertia Controllers
          |
          v
Application Services / Use Cases
          |
          v
Domain Rules / Policies
          |
          v
Persistence / Eloquent
          |
          v
PostgreSQL
```

## 5.1 Controllers

Controllers should:

- accept request input;
- invoke the appropriate use case;
- return Inertia pages or JSON responses;
- translate validation/authentication failures into UI/API responses.

Controllers should not contain complex transactional business logic.

## 5.2 Application services / use cases

Examples:

```text
CreateOrder
SubmitOrder
ApproveOrder
ReserveInventory
RequestOrderAdjustment
ApproveOrderAdjustment
ApplyOrderAdjustment
RecordPayment
VerifyPayment
ReversePayment
GenerateInvoice
CreateReturnRequest
ApproveReturn
CompleteDelivery
```

Use cases coordinate domain operations and transaction boundaries.

## 5.3 Domain services

Use for complex reusable rules such as:

```text
PricingService
TaxCalculationService
OrderAllocationService
InventoryReservationService
FinancialImpactService
ReceivableBalanceService
InvoiceSnapshotService
```

## 5.4 Query services

Read-heavy screens should use dedicated query classes/services where useful.

Examples:

```text
AdminNewOrdersQuery
AdminActiveOrdersQuery
AdminDeliveryQueueQuery
AdminAdjustmentsQuery
CustomerStatementQuery
InventoryAvailabilityQuery
PaymentVerificationQueueQuery
```

This avoids forcing complex dashboard/query logic into domain write services.

---

# 6. Frontend Architecture

## 6.1 Stack

```text
React 19.2
TypeScript
Inertia 3
Tailwind CSS 4
shadcn/ui
Vite
```

## 6.2 Three portal experiences

```text
Salesman
Admin
Delivery Partner
```

Use separate route/page namespaces and layouts while sharing:

- domain data;
- authorization;
- API/application logic;
- reusable visual primitives;
- shared types where practical.

## 6.3 Frontend principle

The frontend is responsible for:

- presentation;
- interaction;
- local form state;
- optimistic UI only where safe;
- user feedback;
- accessibility.

The frontend is **not** the authority for:

- price rules;
- tax;
- permissions;
- inventory availability;
- payment validity;
- adjustment authorization;
- accounting.

The backend recalculates and validates authoritative values.

---

# 7. Portal Routing Architecture

Conceptual routes:

```text
/salesman/*
/admin/*
/delivery/*
```

Authentication establishes identity.

Authorization determines whether the authenticated user may access a route/action.

Do not assume that access to one page grants access to all actions on that page.

Example:

```text
Admin can view order
≠
Admin can reverse payment
```

Permissions are enforced at the backend.

---

# 8. Database Architecture

## 8.1 Primary database

PostgreSQL 18.

## 8.2 Database responsibility

PostgreSQL is authoritative for:

- users;
- roles;
- customers;
- salesmen;
- products;
- pricing;
- tax configuration;
- orders;
- order items;
- quantity allocations;
- adjustments;
- inventory state;
- inventory movements;
- payments;
- payment verification;
- returns;
- credits;
- invoices/document metadata;
- receivables;
- accounting;
- audit records;
- settings.

## 8.3 Database design rules

Use:

- foreign keys;
- unique constraints;
- check constraints where valuable;
- non-null constraints where business-required;
- database indexes based on query patterns;
- transactions for multi-record state changes.

Do not rely on application code alone to preserve fundamental referential integrity.

---

# 9. Core Entity Model

Conceptual relationship:

```text
Customer
   |
   +---- Orders
   |       |
   |       +---- Order Items
   |       |        |
   |       |        +---- Allocations
   |       |        +---- Adjustments
   |       |
   |       +---- Payments
   |       |        |
   |       |        +---- Attachments
   |       |
   |       +---- Deliveries
   |       |
   |       +---- Returns
   |       |
   |       +---- Invoices
   |
   +---- Receivable Transactions
```

Products participate through order items and inventory records.

---

# 10. Product & Pricing Data Model

Conceptual entities:

```text
products
categories
product_tax_profiles
product_price_history
```

Product fields should include:

```text
id
sku
name
description
category_id
status
cost_price
mrp/list_price
default_selling_price
minimum_allowed_price
tax_profile_id
```

Do not use current product values to reconstruct historical transactions.

---

# 11. Historical Transaction Snapshot Strategy

When an order is created/confirmed, store transaction-time values on order items.

At minimum:

```text
product_id
product_name_snapshot
sku_snapshot
unit_price
tax_profile_snapshot/reference
tax_rate_snapshot
taxable_amount
tax_amount
line_subtotal
line_total
```

A later product change must not alter the historical order.

This is mandatory for:

- price;
- tax;
- product naming/SKU context used by historical documents.

---

# 12. Order Data Model

Conceptual `orders` fields:

```text
id
order_number
customer_id
salesman_id
status
fulfillment_status
payment_status
delivery_status
adjustment_status
currency
subtotal
tax_total
adjustment_total
grand_total
original_subtotal
original_tax_total
original_grand_total
submitted_at
approved_at
completed_at
created_by
approved_by
timestamps
```

Do not treat `grand_total` as an independently editable source of truth.

It should be derived/reconciled from transactional line and adjustment data.

---

# 13. Order Items

Conceptual fields:

```text
id
order_id
product_id
product_name_snapshot
sku_snapshot
ordered_quantity
cancelled_quantity
reserved_quantity
picked_quantity
dispatched_quantity
delivered_quantity
return_requested_quantity
returned_quantity
accepted_return_quantity
rejected_return_quantity
unit_price
tax_rate_snapshot
tax_amount
line_subtotal
line_total
```

Actual implementation may normalize allocations/fulfillment events into separate tables rather than storing every quantity directly on the order item. The architecture must preserve the same business semantics.

---

# 14. Quantity Allocation Architecture

Use a dedicated allocation concept.

```text
Order Item
    |
    +---- Allocation
            |
            +---- Warehouse/Stock Source
            +---- Reserved Qty
            +---- Fulfillment State
            +---- Released Qty
            +---- Consumed Qty
```

The purpose is to identify exactly how much inventory is committed to an order item at any point.

This supports:

- partial cancellation;
- split fulfillment;
- warehouse exceptions;
- delivery quantities;
- future multiple-location support.

---

# 15. Order Adjustment Architecture

Core entities:

```text
order_adjustments
order_adjustment_items
```

Conceptual fields:

```text
adjustment_id
order_id
type
status
reason
notes
requested_by
approved_by
requested_at
approved_at
applied_at
reversed_at
```

Adjustment items should identify:

```text
order_item_id
quantity_impact
unit_value_impact
tax_impact
financial_impact
inventory_impact
```

## 15.1 Adjustment application

A sensitive adjustment follows:

```text
Validate state
      ↓
Authorize actor
      ↓
Validate quantity
      ↓
Determine inventory effect
      ↓
Determine tax effect
      ↓
Determine financial effect
      ↓
Create adjustment record
      ↓
Update allocation
      ↓
Update required inventory state
      ↓
Update/reconcile financial transaction
      ↓
Create audit event
      ↓
COMMIT
```

The above should occur within a controlled transaction where the operations are required to succeed together.

---

# 16. Inventory Architecture

Conceptual structures:

```text
inventory_balances
inventory_allocations
inventory_movements
stock_exceptions
```

Inventory state should distinguish:

```text
on_hand
reserved
available
damaged
```

Conceptually:

```text
available = on_hand - reserved
```

The exact representation may use derived values, materialized balances, or ledger-style movement reconstruction, but one approach must be selected consistently in implementation.

---

# 17. Inventory Movement Ledger

Every material inventory transition should create a movement record.

Example:

```text
movement_type
product_id
quantity
from_state
to_state
reference_type
reference_id
reason
actor
occurred_at
```

Examples:

```text
AVAILABLE → RESERVED
RESERVED → PICKED
PICKED → DISPATCHED
ON_HAND → DAMAGED
WAREHOUSE_RECEIPT → AVAILABLE
```

This movement history must be retained.

---

# 18. Inventory Concurrency

Inventory reservation is a critical transaction.

Example:

```text
Available = 1

User A requests 1
User B requests 1
```

Only one reservation may succeed.

Recommended strategy:

```text
BEGIN TRANSACTION

SELECT / lock relevant inventory balance

Recalculate available quantity

If available < requested:
    reject

Otherwise:
    create/update reservation
    create inventory movement
    update balance

COMMIT
```

Use PostgreSQL transactional locking/atomic update patterns.

The code must be tested under concurrency.

---

# 19. Inventory Adjustment on Damaged Goods

Example:

```text
Reserved = 10
2 units become damaged
```

The adjustment must:

1. reduce eligible reserved quantity;
2. create the appropriate stock movement;
3. classify affected goods as damaged if physically damaged;
4. reduce fulfillable quantity;
5. recalculate affected order totals;
6. apply tax impact;
7. update financial position;
8. update delivery quantity;
9. audit the entire action.

Never simply subtract two from a stock number without a movement/history record.

---

# 20. Payment Architecture

Core entities:

```text
payments
payment_attachments
payment_verification_events
```

Payment fields:

```text
id
customer_id
order_id
method
amount
currency
status
payment_date
recorded_by
verified_by
verified_at
notes
reference
```

Methods:

```text
CASH
CHEQUE
MONEY_ORDER
```

The design must be extensible for future methods.

---

# 21. Payment Method Details

## 21.1 Cash

No evidence attachment required by default.

## 21.2 Cheque

Required data:

```text
amount
cheque_number
cheque_date
bank_name
JPEG attachment
status
```

Potential status:

```text
RECORDED
PENDING_VERIFICATION
CONFIRMED
RETURNED
REVERSED
```

## 21.3 Money Order

Required data:

```text
amount
reference/details
JPEG attachment
status
```

---

# 22. Payment Attachment Storage

Actual JPEG files should not be stored in PostgreSQL.

Use:

```text
Private S3 object
       |
       v
payment_attachments
       |
       v
payment_id
```

Metadata:

```text
storage_key
original_filename
mime_type
size
checksum where useful
uploaded_by
uploaded_at
```

Access must be authorized.

Do not expose predictable public object URLs.

---

# 23. Tax Architecture

Create a dedicated tax module.

Conceptual entities:

```text
tax_profiles
tax_rules
tax_rates/history where required
```

The order line stores transaction-time tax information.

Tax calculation flow:

```text
Product
  ↓
Tax Configuration
  ↓
Tax Calculation Service
  ↓
Order Item Tax Snapshot
  ↓
Order Totals
```

The tax engine should accept:

```text
product
taxable amount
effective date
tax configuration
```

and return a deterministic calculation result.

---

# 24. Tax Rounding

Exact rounding policy remains a PRD TBD.

Until confirmed:

- centralize rounding;
- never duplicate tax arithmetic in frontend components;
- never let multiple modules independently round the same tax;
- document the chosen policy once approved.

---

# 25. Order Financial Calculation

Create a central calculation service.

Conceptual flow:

```text
Order Items
   ↓
Line Subtotals
   ↓
Line Tax
   ↓
Approved Adjustments
   ↓
Net Order Liability
```

Then:

```text
Net Liability
      ↓
Applicable Payments
      ↓
Outstanding / Credit or Refund Due
```

The frontend may display previews, but the backend must authoritatively calculate the result.

---

# 26. Receivable Architecture

Do not make customer outstanding a manually editable field.

Use transactional records such as:

```text
AR Debit
AR Payment
AR Credit
AR Refund
AR Adjustment
```

Then derive/reconcile:

```text
Customer Balance
```

Customer statement:

```text
Date
Reference
Description
Debit
Credit
Running Balance
```

This provides a foundation for aging.

---

# 27. Accounting Architecture

Core entities:

```text
accounts
journal_entries
journal_lines
accounting_periods if required
financial_transactions / source links
reversals
```

Every journal entry should identify its source business event where applicable.

Example:

```text
Source:
Order #10045
```

or:

```text
Source:
Payment #PAY-1008
```

Posted financial history is immutable from a business perspective.

Corrections use reversal/adjustment entries.

---

# 28. Invoice Architecture

Core concepts:

```text
invoices
invoice_lines
invoice_snapshots
invoice_documents
```

Invoice generation:

```text
Order / Current Financial State
        ↓
Invoice Snapshot
        ↓
Template Renderer
        ↓
HTML Preview / Print
        ↓
Chromium PDF
        ↓
Private Storage
```

Invoice must preserve the transaction/document data that was used to generate the financial document.

---

# 29. Invoice Rendering Rule

Hard rule:

```text
Product images are NEVER included in invoice rendering.
```

No invoice template should reference:

```text
product.image
product.thumbnail
product.media
```

for display.

Payment evidence JPEGs must also not be automatically embedded in the invoice.

---

# 30. Invoice Numbering

Invoice numbers must be generated independently of order numbers.

Architecture should use a safe sequence/generation mechanism that prevents duplicates under concurrency.

Example:

```text
ORD-001234
INV-000987
```

Exact prefix/format remains configurable/TBD.

---

# 31. Returns Architecture

Entities:

```text
returns
return_items
return_inspections
return_adjustments
```

Return flow:

```text
Request
 ↓
Approval
 ↓
Pickup
 ↓
Warehouse Receipt
 ↓
Inspection
 ↓
Accepted / Partially Accepted / Rejected
```

Inventory and financial impacts occur only according to the actual accepted return state.

---

# 32. Delivery Architecture

Entities:

```text
deliveries
delivery_items or references to order allocations
delivery_status_history
delivery_failures
```

Delivery must consume the **current deliverable allocation**, not original order quantity.

A cancelled quantity cannot reappear in the delivery payload.

---

# 33. Admin Order Workspace Architecture

The Admin order sub-pages should be implemented as **query-driven views**, not physically separate order stores.

Example:

```text
New Orders Query
Active Orders Query
Delivery Query
Adjustments Query
Completed Query
Cancelled Query
All Orders Query
```

Each query applies current state/role/filter conditions.

This provides:

- clean operational lists;
- fast filtering;
- server-side pagination;
- accurate counts;
- one order source of truth.

---

# 34. Search & Pagination

Large operational lists must use backend queries.

Never load every order into browser memory.

Use:

```text
server-side filtering
server-side sorting
server-side pagination
indexed search fields
```

Important indexes will likely include:

```text
order_number
customer_id
salesman_id
status
payment_status
delivery_status
created_at
```

Exact index definitions should follow actual query plans once the schema is implemented.

---

# 35. Redis Architecture

Redis is not a transactional source of truth.

Use Redis for:

```text
Cache
Queue backend
Rate limiting
Temporary locks where appropriate
Short-lived application state
```

Do not store authoritative:

```text
orders
payments
inventory truth
accounting truth
```

only in Redis.

---

# 36. Queues & Background Jobs

Background jobs are appropriate for non-critical secondary work.

Examples:

```text
GenerateInvoicePdf
SendOrderNotification
SendPaymentNotification
SendDeliveryNotification
ProcessReportExport
CleanupTemporaryFiles
```

Critical business state changes must commit successfully before secondary jobs depend on them.

Use transaction-aware dispatching so jobs do not execute against data that ultimately rolls back.

---

# 37. Domain Events

Recommended events:

```text
OrderSubmitted
OrderApproved
OrderRejected
InventoryReserved
StockExceptionReported
OrderAdjustmentRequested
OrderAdjustmentApplied
PaymentRecorded
PaymentVerified
PaymentReversed
ChequeReturned
DeliveryAssigned
DeliveryCompleted
ReturnRequested
ReturnApproved
CreditIssued
RefundApproved
InvoiceIssued
```

Events are for decoupled secondary reactions.

Do not use events to make critical financial correctness depend on eventual execution unless the entire business process is explicitly designed as asynchronous.

---

# 38. Idempotency Architecture

Critical commands must be safe against duplicate network retries.

Apply idempotency to operations such as:

```text
order submission
payment recording
adjustment application
invoice issuance
delivery completion
```

Conceptually:

```text
idempotency_key
actor
command_type
request_hash
result/reference
created_at
```

A repeated request with the same valid idempotency key must not create a second business transaction.

---

# 39. Authentication Architecture

All portals use centralized authentication.

Conceptually:

```text
User
 ↓
Credential/session authentication
 ↓
Authenticated principal
 ↓
Role
 ↓
Permissions
 ↓
Policy/domain authorization
```

Do not duplicate authentication systems per portal.

---

# 40. Authorization Foundation

Use permission-based authorization rather than scattered role checks.

Example permissions:

```text
order.create
order.submit
order.view
order.approve
order.reject
order.adjust.request
order.adjust.approve
order.adjust.apply
payment.create
payment.verify
payment.reverse
inventory.view
inventory.adjust
delivery.update
return.approve
refund.approve
accounting.post
accounting.reverse
invoice.view
invoice.print
```

Detailed role assignment belongs in Document 03.

---

# 41. Audit Architecture

Create an immutable audit event model.

Conceptual fields:

```text
id
actor_id
actor_role_snapshot
action
entity_type
entity_id
before_state
after_state
reason
request_id
ip_address
user_agent
created_at
```

Avoid storing uncontrolled sensitive secrets in audit payloads.

Audit logs must not be user-editable from normal UI.

---

# 42. File & Document Security

Business files should use private object storage.

Recommended access flow:

```text
User requests document
       ↓
Backend authorization
       ↓
Generate short-lived signed URL / stream
       ↓
User receives file
```

Never make cheque/money-order images publicly accessible merely because the file is easier to display.

---

# 43. Configuration Architecture

Product identity and environment-specific values must be configuration-driven.

Examples:

```text
APP_NAME
COMPANY_NAME
COMPANY_LOGO
COMPANY_ADDRESS
COMPANY_PHONE
COMPANY_EMAIL
APP_URL
APP_TIMEZONE
APP_LOCALE
DEFAULT_CURRENCY
```

Business settings such as:

```text
invoice numbering
tax configuration defaults
payment policy
feature switches
```

should be represented as configuration/settings rather than hard-coded constants where practical.

---

# 44. Environment Architecture

Use:

```text
LOCAL
  ↓
STAGING
  ↓
PRODUCTION
```

## 44.1 Local

Developer machine with Docker Compose.

Services:

```text
Laravel/PHP
PostgreSQL
Redis
Node/Vite
Mail testing
Local S3-compatible storage if desired
```

## 44.2 Staging

Cloud environment matching production topology as closely as practical.

Used for:

- integration testing;
- UAT;
- migration verification;
- release validation.

## 44.3 Production

Client's live system.

Production data must never be used casually for development/testing.

---

# 45. Docker Architecture

The application should be reproducible through Docker.

Conceptual services:

```text
app
web/server
queue-worker
scheduler
postgres
redis
```

During local development, the app, worker and scheduler can share the same Laravel codebase while running as separate processes/containers.

---

# 46. Production Infrastructure

## 46.1 Initial AWS recommendation

Start with:

```text
Route 53
   ↓
HTTPS
   ↓
EC2
   ↓
Laravel Application

EC2 → RDS PostgreSQL
EC2 → ElastiCache Redis
EC2 → S3
EC2 → SES when enabled
```

CloudFront can be added where it provides meaningful value.

## 46.2 Container-ready design

The application should still be built so it can later run on ECS/Fargate without major application changes.

Do not couple business logic to EC2-specific filesystem behavior.

---

# 47. Production Storage Rules

Local application disks should not be considered permanent storage for business documents.

Use S3 for:

```text
payment evidence
invoice PDFs
product images
future return evidence
other business documents
```

Database stores metadata/references.

---

# 48. Application Filesystem

The application may use local ephemeral storage for:

```text
temporary uploads
temporary PDF rendering
temporary exports
cache
```

These files must not be the only copy of permanent business documents.

---

# 49. Database Backups

Production RDS should use:

- automated backups;
- appropriate retention;
- point-in-time recovery;
- snapshots before major schema changes.

Backup restoration must be tested periodically.

A backup that has never been restored is not considered fully validated.

---

# 50. Deployment Pipeline

Recommended:

```text
Developer
   ↓
Git branch
   ↓
Pull Request
   ↓
GitHub Actions
   ├── PHP checks
   ├── tests
   ├── frontend checks
   ├── build
   └── security/basic validation
   ↓
Staging
   ↓
UAT
   ↓
Production deployment
```

Do not deploy unreviewed AI-generated code directly to production.

---

# 51. Migration Strategy

All database changes must use version-controlled migrations.

Rules:

- migrations must be forward-compatible where possible;
- destructive migrations require explicit review;
- data migrations must be separate from structural changes when complexity warrants;
- production migrations require backups and a rollback/forward-fix plan.

Never casually edit production schema manually without recording the change in repository history.

---

# 52. Application Logging

Use structured application logs.

Include:

```text
timestamp
level
request_id
user_id where appropriate
module
operation
message
metadata
```

Do not log:

- passwords;
- authentication secrets;
- full sensitive payment evidence;
- unnecessary financial secrets.

---

# 53. Request Correlation

Every important request should have a correlation/request ID.

Example:

```text
Request ID: req_abc123
```

That identifier should be available in:

- application logs;
- audit context where appropriate;
- error reports;
- background job context where practical.

This makes production debugging easier.

---

# 54. Error Monitoring

Use Sentry or equivalent for application failures.

Errors should include:

- environment;
- release/version;
- route/operation;
- stack trace;
- correlation ID;
- sanitized user context.

Do not expose internal exception details to end users.

---

# 55. Performance Strategy

Expected scale does not require distributed architecture.

Focus on:

```text
proper indexes
pagination
selective eager loading
query optimization
Redis caching for suitable read data
queueing expensive secondary work
avoiding N+1 queries
```

Use database query analysis when slow screens appear.

---

# 56. Caching Strategy

Appropriate candidates:

```text
product catalog data
categories
stable settings
permission metadata
tax configuration where safe
dashboard aggregate caches where useful
```

Do not cache volatile financial values without clear invalidation rules.

Customer outstanding and inventory availability should prioritize correctness over cache speed.

---

# 57. Security Boundaries

Architecture must protect:

```text
Authentication
Authorization
Financial data
Payment evidence
Customer information
Business documents
Audit logs
Administrative controls
```

HTTPS is mandatory in staging/production.

Security-sensitive operations should re-check permissions and current business state at execution time.

---

# 58. Data Integrity Boundaries

Use a database transaction when a business operation requires multiple state changes to succeed together.

Example:

## Apply item cancellation

```text
Adjustment
+
Order allocation
+
Inventory reservation
+
Financial impact
+
Tax impact
+
Audit record
```

must not end half-applied.

---

# 59. Transaction Boundary Rule

The application should distinguish:

### Atomic business transaction

Operations that must commit together.

### Secondary side effects

Operations that can occur after successful commit.

Example:

```text
Atomic:
Cancel item
Inventory update
Financial update
Audit event

Secondary:
Send notification
Generate non-critical report cache
```

---

# 60. Frontend Form Validation

Use frontend validation for UX.

But every critical rule is revalidated by the backend.

Example:

Frontend may warn:

```text
Cancel quantity cannot exceed 5
```

Backend must independently verify:

```text
remaining eligible quantity >= requested cancellation
```

---

# 61. File Upload Validation

Cheque and money-order JPEG uploads should validate:

- allowed MIME type;
- actual file signature;
- reasonable size;
- image dimensions if needed;
- filename normalization;
- storage path generation;
- authorization.

Do not trust the browser's MIME type alone.

Use generated storage names instead of user-provided filenames as object identifiers.

---

# 62. Image Processing

For catalog images, image resizing/optimization may be performed asynchronously.

For cheque/money-order evidence:

- preserve evidence fidelity;
- avoid destructive processing;
- optionally generate a preview derivative;
- retain the original securely.

---

# 63. Invoice PDF Generation

Recommended process:

```text
Invoice snapshot
       ↓
HTML template
       ↓
Chromium renderer
       ↓
PDF
       ↓
S3
       ↓
Document metadata record
```

Use deterministic templates.

The PDF renderer must run in an environment with controlled fonts and rendering dependencies.

---

# 64. Invoice Regeneration Rule

An issued invoice should have a stable snapshot.

If a later business adjustment requires a new financial document:

```text
new adjustment / credit / revised invoice
```

rather than silently changing an already-issued document.

---

# 65. Reporting Architecture

Reports should query transactional truth.

Start with database-backed reports.

Possible future evolution:

```text
Primary DB
   ↓
optimized reporting queries
```

Only introduce a separate reporting database/data warehouse when scale actually justifies it.

---

# 66. Search Architecture

V1 does not require Elasticsearch/OpenSearch.

Use PostgreSQL indexes and search capabilities.

Potential search targets:

```text
order number
customer name
customer phone
SKU
product name
invoice number
payment reference
```

Re-evaluate external search only if actual query volume/complexity demands it.

---

# 67. Notification Architecture

Keep business event creation independent from notification provider.

Example:

```text
OrderApproved
    ↓
Notification Handler
    ├── In-app
    ├── Email
    ├── SMS
    └── WhatsApp
```

Providers can be added later without rewriting order logic.

---

# 68. Scheduler

Laravel scheduler can handle recurring jobs such as:

```text
cleanup temporary files
payment reminders
report generation
health checks
reconciliation tasks
data maintenance
```

Exact scheduled jobs should be defined when implementation requirements are finalized.

---

# 69. Queue Workers

Separate workers conceptually:

```text
default
documents
notifications
reports
```

This allows expensive PDF/report processing to avoid blocking urgent notifications or ordinary jobs.

A simpler single queue is acceptable in early local development.

---

# 70. Testing Architecture

Minimum test layers:

## Unit

Business calculations:

```text
tax
pricing
quantity allocation
credit exposure
customer balance
financial impact
```

## Feature/integration

```text
order creation
approval
inventory reservation
adjustments
payments
returns
invoice
delivery
```

## Concurrency

Explicit tests for:

```text
simultaneous stock reservation
duplicate commands
duplicate payments
```

## Browser/E2E

Critical workflows across portals.

---

# 71. Critical Acceptance Test Matrix

At minimum automate:

```text
Normal order
Mixed tax order
Partial quantity cancellation
Multiple adjustments
Damaged goods after reservation
Cheque + JPEG
Money Order + JPEG
Mixed payments
Payment verification
Cheque returned/bounced
Invoice without images
Partial delivery
Partial return
Return rejection
Fully paid order reduced by adjustment
Partially paid order reduced by adjustment
Historical price change
Historical tax change
Duplicate order submission
Concurrent reservation
Authorization bypass attempt
```

---

# 72. API / Inertia Strategy

The primary web application should use Inertia for portal page interaction.

Do not build a separate public REST API unless a concrete integration needs one.

Use JSON/API endpoints internally where beneficial for:

- asynchronous interactions;
- upload workflows;
- autocomplete/search;
- mobile-like delivery interactions;
- future external integrations.

Any external API introduced later must use versioned contracts.

---

# 73. Data Access Strategy

Use Eloquent models for persistence, but avoid using raw model mutation everywhere.

Recommended:

```text
Model
 ↓
Repository/query abstraction only where complexity justifies it
 ↓
Application service
```

Do not introduce repository classes mechanically for every table if they add no value.

The architecture should remain understandable to a small development team.

---

# 74. Domain Naming Rules

Use consistent naming.

Examples:

```text
Order
OrderItem
OrderAdjustment
InventoryMovement
Payment
PaymentAttachment
ReturnRequest
Invoice
JournalEntry
AuditEvent
```

Avoid ambiguous names such as:

```text
Transaction
Record
Data
ItemChange
```

when a specific domain term exists.

---

# 75. Status Storage

Statuses should use centrally defined domain values/enums where suitable.

Do not scatter raw status strings across dozens of controllers.

For example:

```text
OrderStatus
PaymentStatus
DeliveryStatus
AdjustmentStatus
ReturnStatus
```

The exact final set must match the approved workflow.

---

# 76. Soft Delete Strategy

Do not apply soft deletion blindly to every financial entity.

For master data such as products/customers, inactive status may be preferable to deletion.

For financial and historical transactions:

```text
do not delete
do not hide through destructive deletion
use reversal / cancellation / adjustment
```

---

# 77. Audit vs History

Audit logs answer:

> Who changed the system and why?

Domain history answers:

> What business events happened?

Keep both concepts where needed.

For example:

```text
Order Adjustment
```

is a business/domain record.

```text
Admin approved adjustment at 14:32 from IP...
```

is audit context.

---

# 78. Time Handling

Database timestamps should be stored in UTC.

Application should use timezone-aware date/time handling.

UI should display dates/times according to configured business timezone.

Never use server-local timezone as an accidental business rule.

---

# 79. Currency Handling

V1 currency is USD.

All financial calculations must use decimal/fixed precision arithmetic.

Do not use binary floating-point arithmetic for authoritative monetary calculations.

Currency should be explicit in financial records where appropriate.

---

# 80. Money Precision

Recommended database strategy:

```text
NUMERIC(19,4)
```

or another explicitly approved fixed-precision design.

The exact scale can be finalized before migrations based on the client's pricing/tax requirements.

The architecture must never use JavaScript floating-point values as the authoritative monetary representation.

---

# 81. Frontend Money Handling

The frontend should receive serialized decimal/string monetary values where appropriate and format them only for display.

Avoid:

```text
JS number
→ arithmetic
→ submit as authoritative amount
```

The backend recalculates authoritative totals.

---

# 82. Configuration & Secrets

Never commit:

```text
passwords
API keys
AWS secrets
database credentials
application keys
Sentry DSNs if considered sensitive
```

to Git.

Use environment variables and/or AWS Secrets Manager/SSM for production secrets.

---

# 83. Environment Configuration Categories

Separate:

### Application

```text
APP_ENV
APP_KEY
APP_URL
APP_NAME
APP_TIMEZONE
APP_LOCALE
```

### Database

```text
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

### Redis

```text
REDIS_HOST
REDIS_PORT
REDIS_PASSWORD
```

### Storage

```text
FILESYSTEM_DISK
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION
AWS_BUCKET
```

### Mail

```text
MAIL_MAILER
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
```

### Monitoring

```text
SENTRY_DSN
```

Exact variable set will depend on enabled services.

---

# 84. Environment File Rule

`.env` is environment-specific configuration.

Commit:

```text
.env.example
```

Do not commit:

```text
.env
```

The `.env.example` should contain safe placeholders and documentation for every required variable.

---

# 85. Local Development Environment

Recommended local stack:

```text
Docker Compose
├── application
├── PostgreSQL
├── Redis
└── optional local object storage
```

Use the same PHP major/minor and PostgreSQL major version intended for staging/production wherever practical.

---

# 86. Repository Structure

Recommended high-level structure:

```text
project/
├── app/
│   ├── Console/
│   ├── Domain/
│   │   ├── Identity/
│   │   ├── Customers/
│   │   ├── Salesmen/
│   │   ├── Products/
│   │   ├── Pricing/
│   │   ├── Tax/
│   │   ├── Orders/
│   │   ├── Adjustments/
│   │   ├── Inventory/
│   │   ├── Warehouse/
│   │   ├── Delivery/
│   │   ├── Payments/
│   │   ├── Documents/
│   │   ├── Returns/
│   │   ├── Finance/
│   │   ├── Accounting/
│   │   ├── Reporting/
│   │   ├── Notifications/
│   │   └── Audit/
│   ├── Http/
│   └── Models/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── js/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── pages/
│   │   │   ├── admin/
│   │   │   ├── salesman/
│   │   │   └── delivery/
│   │   ├── features/
│   │   ├── hooks/
│   │   ├── types/
│   │   └── lib/
│   └── views/
├── routes/
├── tests/
├── storage/
├── docker/
└── docs/
```

This is a recommended direction. The final exact structure should be validated against the actual Laravel starter/project generated.

---

# 87. Seed Data & Factories

Development environments should have seedable test data for:

- users;
- roles;
- customers;
- salesmen;
- products;
- tax profiles;
- inventory;
- orders;
- payments;
- deliveries.

Factories should produce realistic combinations for testing.

Do not use production customer data as test fixtures.

---

# 88. Local Demo Scenarios

Create repeatable seed scenarios for:

```text
Normal order
Partially paid order
Mixed payment order
Cheque with attachment
Money Order with attachment
Damaged-stock adjustment
Partial return
Overpayment after adjustment
```

This accelerates manual QA and debugging.

---

# 89. Release Versioning

Use application release/version metadata.

Example:

```text
v1.0.0
v1.0.1
v1.1.0
```

The actual release strategy can use semantic versioning where practical.

Each release should correspond to an identifiable Git commit/tag.

---

# 90. Feature Change Strategy

When a client changes a requirement:

```text
Client request
   ↓
PRD change / Change ID
   ↓
Architecture impact analysis
   ↓
Security impact analysis
   ↓
Frontend impact analysis
   ↓
Ticket update
   ↓
Implementation
```

Do not modify code first and document later.

---

# 91. Architecture Traceability

Every major architecture decision should reference PRD requirement groups.

Examples:

```text
PRD-GEN-002
→ Shared Laravel transaction core

FEAT-ORD-005
→ Order item quantity allocation

FEAT-ORD-006
→ Order Adjustment module

FEAT-INV-001
→ Atomic inventory reservation

FEAT-PAY-002
→ Cheque payment + attachment architecture

FEAT-PAY-003
→ Money Order payment + attachment architecture

FEAT-TAX-001
→ Tax module

FEAT-DOC-003
→ Invoice rendering/printing

FEAT-ADM-001..007
→ Query-driven Admin order workspace
```

---

# 92. Future Warehouse Portal

The architecture must permit a dedicated Warehouse Portal later.

V1 can keep Warehouse operations under the chosen current interface boundary, but backend capabilities should be isolated enough that a future:

```text
/warehouse/*
```

portal can consume the same services.

No duplicated business rules.

---

# 93. Future Customer/Supplier Portals

The same principle applies.

Future:

```text
Customer Portal
Supplier Portal
Warehouse Portal
```

must use the existing domain core rather than copying order/inventory/payment logic.

---

# 94. Future Payment Methods

Adding:

```text
BANK_TRANSFER
CARD
ONLINE_PAYMENT
```

should require:

- payment method implementation/configuration;
- UI changes;
- permissions;
- reconciliation changes;
- accounting mapping;
- tests;

not a redesign of the order domain.

---

# 95. Future Tax Expansion

Potential future integrations with external tax systems should enter through a dedicated tax abstraction.

Current implementation should not hard-code external provider assumptions.

---

# 96. Future Infrastructure Growth

Current:

```text
Single modular application
+
PostgreSQL
+
Redis
+
S3
```

Future scaling can evolve toward:

```text
Containerized application
+
ECS/Fargate
+
read replicas if needed
+
separate workers
+
dedicated reporting infrastructure
```

Only introduce complexity when justified by measured requirements.

---

# 97. Failure Handling

Critical operations must be designed for failure.

Examples:

### Inventory update fails

Entire business transaction rolls back.

### PDF generation fails

Financial transaction may still remain valid; PDF generation can be retried.

### Notification fails

Order remains valid; notification retries independently.

### S3 upload fails during required payment evidence upload

Payment creation must not be finalized as compliant if evidence is mandatory.

---

# 98. External Service Failure Principle

External services must not become the authoritative source of internal transaction state.

For example:

```text
S3 unavailable
```

does not mean:

```text
Order database is unavailable
```

But if a required document attachment cannot be persisted, the business action requiring that attachment must fail safely or remain explicitly incomplete according to the business rule.

---

# 99. Disaster Recovery Goals

Exact RPO/RTO remain operational decisions.

Architecture should support:

- automated database backups;
- point-in-time recovery;
- infrastructure recreation;
- S3 durability/versioning as appropriate;
- documented deployment procedure;
- tested restoration.

---

# 100. Production Readiness Checklist

Before production:

```text
Database backups enabled
Database restore tested
HTTPS enabled
Secrets stored securely
Debug disabled
Error pages sanitized
RBAC tested
Critical actions audited
Payment attachments protected
S3 bucket private
Queue workers healthy
Scheduler configured
Monitoring active
Invoice generation tested
Inventory concurrency tested
Idempotency tested
Production migrations reviewed
Staging UAT completed
```

---

# 101. Recommended Build Sequence

The architecture should be implemented in this order:

```text
1. Project bootstrap
2. Local Docker environment
3. Database connection
4. Authentication
5. Roles / permissions foundation
6. Settings / configurable identity
7. Customers
8. Salesmen
9. Products / categories
10. Pricing
11. Tax
12. Orders
13. Order items
14. Quantity allocation
15. Inventory
16. Admin order workspace
17. Order adjustments
18. Payments
19. Payment evidence
20. Delivery
21. Invoices
22. Returns
23. Credits / refunds
24. Receivables
25. Accounting
26. Reports
27. Audit hardening
28. Notifications
29. Automated tests / E2E
30. Staging
31. UAT
32. Production
```

The implementation can overlap where safe, but the transaction core should be established before building sophisticated reporting.

---

# 102. Architecture Anti-Patterns

The following are explicitly prohibited unless a documented exception is approved:

```text
Multiple databases for the three portals
Separate order copies per portal
Business logic hidden only in frontend
Floating-point authoritative money calculations
Hard-coded tax calculations in UI
Hard-coded product prices inside invoices
Destructive quantity overwrite
Manual inventory subtraction without movement
Payment details embedded directly into orders
Public payment evidence files
Controller methods containing entire workflows
One giant OrderController handling every domain
Microservices without concrete business justification
Kubernetes in V1 solely for prestige
Kafka for simple internal events
Elasticsearch for simple order search
Hard-coded company/product branding
Silent financial deletion
```

---

# 103. Definition of Done for Architecture

Technical Architecture is considered implementation-ready when:

- module boundaries are understood;
- core data entities are mapped;
- transactional boundaries are defined;
- inventory concurrency is defined;
- payment/document storage is defined;
- historical snapshots are defined;
- invoice generation is defined;
- environment strategy is defined;
- deployment topology is defined;
- testing strategy is defined;
- observability is defined;
- future extension points are identified;
- known business TBDs remain explicitly marked.

---

# 104. Known Decisions Requiring Later Specification

The following technical/business details are intentionally delegated to later documents:

```text
Exact role → permission matrix
Exact authentication/MFA requirements
Detailed authorization policies
Detailed frontend screen behavior
Exact tax jurisdiction/rounding policy
Exact accounting recognition policy
Exact invoice numbering policy
Exact cheque verification process
Exact refund vs account-credit policy
Exact warehouse UI boundary
```

Document 02 must not invent these simply to make the architecture appear complete.

---

# 105. Technology Version Policy

For the initial baseline:

```text
PHP: 8.5
Laravel: 13
React: 19.2
TypeScript: current compatible version
Inertia: 3
Tailwind: 4
PostgreSQL: 18
Redis: supported current production release compatible with deployment platform
Node.js: current LTS compatible with frontend toolchain
```

Before production deployment, verify exact patch versions and vendor support status.

Pin compatible versions in lockfiles and deployment artifacts.

---

# 106. Architecture Decision Records

Important changes after approval should be captured as ADRs.

Example:

```text
ADR-001 Modular monolith
ADR-002 PostgreSQL as source of truth
ADR-003 React + Inertia
ADR-004 S3 for business documents
ADR-005 Atomic inventory reservation
ADR-006 Transaction snapshots for price/tax
ADR-007 EC2 initial production deployment
```

If a fundamental architectural decision changes, do not silently edit the architecture history.

---

# 107. Final Technical Blueprint

```text
                     WHOLESALE DISTRIBUTION
                       MANAGEMENT SYSTEM
                                |
           ┌────────────────────┼────────────────────┐
           |                    |                    |
           v                    v                    v
      SALESMAN PORTAL      ADMIN PORTAL       DELIVERY PORTAL
           |                    |                    |
           └────────────────────┼────────────────────┘
                                |
                         INERTIA / HTTP
                                |
                                v
                  ┌───────────────────────────┐
                  │       LARAVEL 13          │
                  │      MODULAR MONOLITH     │
                  │                           │
                  │ Identity                  │
                  │ Customers                 │
                  │ Sales                     │
                  │ Products / Pricing / Tax  │
                  │ Orders / Adjustments      │
                  │ Inventory / Warehouse     │
                  │ Delivery                  │
                  │ Payments                  │
                  │ Documents                 │
                  │ Returns / Finance         │
                  │ Accounting / Reporting    │
                  │ Notifications / Audit     │
                  └────────────┬──────────────┘
                               |
                 ┌─────────────┼─────────────┐
                 |             |             |
                 v             v             v
          PostgreSQL 18      Redis           S3
          SOURCE OF TRUTH    Cache/Queue     Private Files
                 |
                 v
           AWS RDS PostgreSQL

        Production application:
           AWS EC2 initially
           Container-ready for ECS later
```

---

# 108. Final Architecture Principle

The application should be understood as:

> **A modular Laravel transaction engine with React-based role-specific portals, PostgreSQL as the authoritative business ledger, Redis for non-authoritative operational acceleration, S3 for private documents, and AWS for production infrastructure.**

The system's complexity belongs in its **domain correctness**, not in unnecessary infrastructure.

The highest-risk technical areas are:

```text
Order state integrity
Quantity allocation
Inventory concurrency
Order adjustments
Tax snapshots
Payment verification
Financial calculations
Invoice snapshots
Authorization
Auditability
Idempotency
```

These areas receive priority during implementation and testing.

---

## Appendix A — Technology Rationale

### Laravel 13

Chosen for:

- mature transaction/database ecosystem;
- authentication and authorization ecosystem;
- queues/jobs;
- storage integrations;
- validation;
- excellent fit for relational business applications;
- compatibility with React/Inertia starter architecture.

### PHP 8.5

Chosen as the current supported PHP runtime for Laravel 13.

### React + TypeScript

Chosen for:

- rich Admin tables;
- interactive order-entry interfaces;
- adjustment workflows;
- delivery mobile-first UI;
- strong component reuse.

### Inertia

Chosen to avoid building and maintaining an unnecessarily separate SPA/backend API architecture.

### PostgreSQL

Chosen for:

- relational integrity;
- transactions;
- constraints;
- locking;
- financial data;
- complex reporting queries.

### Redis

Chosen only where latency/throughput benefits justify it.

### S3

Chosen for durable business document/object storage.

### AWS

Chosen for a straightforward production path and future DevOps growth.

---

## Appendix B — Explicit V1 Infrastructure Constraint

The initial implementation must favor:

**simple, reproducible, observable infrastructure**

over:

**maximum theoretical scalability**.

Do not introduce microservices, Kubernetes, Kafka, service meshes, multiple databases, or distributed systems unless a new approved requirement makes them necessary.

---

## Appendix C — Antigravity Implementation Rule

Before implementing any feature, Antigravity must:

1. Read the latest approved PRD.
2. Read the latest approved Technical Architecture.
3. Read the Security & Access document once available.
4. Read the Frontend Specification once available.
5. Read the corresponding feature ticket.
6. Inspect existing code before changing it.
7. Identify dependencies and affected modules.
8. Implement the smallest correct change.
9. Add or update tests.
10. Run validation.
11. Report modified files, tests, and any architecture concerns.

Antigravity must not:

- invent business rules;
- rewrite unrelated modules;
- bypass authorization;
- duplicate domain logic;
- silently change transactional semantics;
- replace historical data with current master data;
- disable tests to make a feature pass.

---

# End of Technical Architecture Document

**Next specification:**  
**03 — Security & Access Document**