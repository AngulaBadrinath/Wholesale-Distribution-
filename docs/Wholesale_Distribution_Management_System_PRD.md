# Product Requirements Document (PRD)

## Wholesale Distribution Management System

**Document Type:** Product Requirements Document (PRD)  
**Working Product Name:** Wholesale Distribution Management System  
**Product Name Status:** Temporary / Replaceable  
**Document Version:** 1.0  
**Status:** Development Baseline  
**Primary Audience:** Client, Product/Business Owner, Architect, Developer, QA, Antigravity/AI Coding Agent  
**Currency Context:** USD  
**Initial Customer Scale:** Approximately 500–700 customers  
**Primary Market:** United States  

---

## 0. Document Control & Specification Rules

### 0.1 Purpose of this document

This PRD is the business/product source of truth for the initial build of the Wholesale Distribution Management System. It defines what the application must do, why each capability exists, who may use it, how the business workflows operate, what rules must never be violated, and what future changes must preserve.

This document intentionally focuses on product behavior and business requirements. Technical implementation details such as programming language, framework, folder structure, database engine, API implementation, caching, deployment, infrastructure, and exact UI components belong in later specification documents.

### 0.2 Working product name

The current working product name is:

> **Wholesale Distribution Management System**

This name is intentionally temporary. The final product/company/application name may change in the future.

The implementation must therefore treat the product name as configurable application metadata rather than hard-coding it throughout the application. Changing the display name should not require changing business logic, database structure, routes, or individual screens.

### 0.3 Requirement status vocabulary

Every major requirement should be treated using one of these statuses:

- **CONFIRMED** — explicitly agreed with the client or established as a current business requirement.
- **PROPOSED** — recommended design choice that is useful for implementation but should be confirmed before being treated as an irreversible business policy.
- **TBD** — a business rule or policy still requires clarification.
- **DEFERRED** — intentionally postponed to a future phase.
- **OUT OF SCOPE** — explicitly excluded from the current baseline.
- **CHANGED** — superseded by a later client decision or specification version.
- **DEPRECATED** — no longer applicable but retained for historical traceability.

### 0.4 Development baseline rule

Antigravity and any other implementation agent must treat this document as the current product baseline only when this version is explicitly approved for development.

The implementation must not invent missing business rules. Where the PRD marks a requirement as TBD, the implementation must use a safe, configurable, or deliberately incomplete path rather than silently assuming a business policy.

### 0.5 Source-of-truth hierarchy

When requirements appear to conflict, use this hierarchy:

1. Latest explicitly approved client decision.
2. Latest approved PRD version.
3. Approved Technical Architecture document.
4. Approved Security & Access document.
5. Approved Frontend Specification.
6. Feature Ticket List.
7. Earlier drafts, chat discussions, or superseded documents.

No later document may silently contradict a confirmed product requirement. A change that alters business behavior must be reflected back into the PRD and assigned a change/decision identifier.

---

# 1. Product Overview

## 1.1 Product definition

The Wholesale Distribution Management System is a centralized business management platform for wholesale distribution operations. It connects salesmen, administrative operations, warehouse fulfillment, delivery execution, payments, inventory, returns, adjustments, invoices, customer receivables, and accounting around a shared transactional core.

The system is intended to replace fragmented or manual workflows with one auditable flow from customer order creation through operational processing, fulfillment, delivery, payment, invoicing, and financial adjustment.

## 1.2 Core business relationship

The primary operational relationship is:

```text
Salesman
   ↓
Admin
   ↓
Warehouse
   ↓
Delivery
   ↓
Customer
```

The Admin is the operational middle layer. Salesmen primarily create and manage customer orders. Admin reviews and controls the commercial/operational process. Warehouse personnel execute physical inventory and fulfillment activities. Delivery partners execute deliveries. All parties operate against the same underlying transaction records.

## 1.3 Primary business objectives

The product must:

- provide a single source of truth for orders and related transactions;
- allow salesmen to efficiently create orders for assigned customers;
- give Admin a clean operational workspace for processing many concurrent orders;
- keep inventory availability, reservation, fulfillment, and damage information synchronized with orders;
- allow specific items or quantities inside an order to be adjusted without destroying the original order;
- support cash, cheque, and money order payments;
- allow JPEG evidence uploads for cheque and money order payments;
- apply product-specific tax accurately at order-line level;
- generate professional invoices without product images;
- support partial fulfillment, partial cancellation, returns, credits, and refunds;
- maintain customer outstanding balances and receivable history;
- provide structured accounting foundations;
- provide role-based access and strong auditability;
- remain extensible as the client's requirements evolve.

## 1.4 Initial scale assumption

The initial operating assumption is approximately 500–700 customers. The product should support growth beyond this level without requiring a full architectural rewrite, while avoiding premature over-engineering.

## 1.5 V1 operating principle

V1 should prioritize operational correctness and maintainability over excessive feature breadth.

The application should implement the complete core transaction lifecycle correctly before adding advanced promotional, predictive, AI, or multi-enterprise capabilities.

---

# 2. Product Vision

## 2.1 Vision statement

Create a reliable wholesale operations platform where every order, quantity, inventory movement, payment, adjustment, delivery event, invoice, and financial consequence is connected, traceable, and easy for the responsible user to understand.

## 2.2 Desired future direction

The system should be capable of evolving into a broader wholesale ERP/business platform without replacing the underlying transaction model.

Future capabilities may include:

- online payment methods;
- bank transfer support;
- advanced promotion and discount management;
- customer portal;
- supplier portal;
- dedicated warehouse portal;
- multiple warehouse/location support;
- barcode workflows;
- batch/expiry management;
- advanced forecasting;
- automated notifications;
- advanced tax automation;
- richer purchasing and supplier management;
- mobile-specific operational experiences;
- AI-assisted reporting and forecasting.

These are not automatically part of V1.

---

# 3. Product Principles

## 3.1 Single source of truth

There must be one shared transactional order record. Salesman, Admin, Warehouse, Delivery, Payments, Invoicing, Returns, and Accounting must derive their relevant information from the same underlying business transaction rather than maintaining independent copies of the order.

## 3.2 Non-destructive history

Historical commercial intent must never be silently overwritten.

For example, if a customer originally orders 10 units and 2 are later cancelled:

```text
Ordered Qty = 10
Cancelled Qty = 2
Remaining/Fulfillable Qty = 8
```

The implementation must not rewrite the original ordered quantity from 10 to 8.

## 3.3 Event and adjustment orientation

When business state changes, prefer recording an explicit adjustment, movement, transaction, or status transition rather than destructively mutating historical records.

## 3.4 Quantity-based fulfillment

Fulfillment must operate at item/quantity level. One order may be partially cancelled, partially picked, partially delivered, or partially returned while remaining financially and operationally active.

## 3.5 Independent state dimensions

Order status, fulfillment status, payment status, delivery status, and return/adjustment status must remain conceptually independent.

## 3.6 Auditability

Important changes must identify who performed them, when, what changed, and why where applicable.

## 3.7 Role separation

Authentication identifies a user. Authorization determines what that user can do. UI restrictions alone are not sufficient; permissions must be enforced server-side.

## 3.8 Extensibility

The product must avoid hard-coded one-off structures for payment methods, adjustments, tax, notifications, or portal behavior when a clean extensible domain model is practical.

## 3.9 Operational clarity

The Admin experience must prioritize current work and exceptions. Historical orders must not clutter the operational workspace.

---

# 4. Users & Personas

## 4.1 Super Admin

**Purpose:** Full system ownership and configuration.

Typical responsibilities:

- user and role administration;
- critical permissions;
- system configuration;
- pricing/tax configuration where authorized;
- sensitive financial reversals;
- audit review;
- operational oversight.

## 4.2 Admin

**Purpose:** Primary operational controller between Salesman and Warehouse.

Responsibilities include:

- reviewing and processing new orders;
- approving or rejecting orders;
- controlling order adjustments;
- coordinating inventory exceptions;
- viewing and managing payments according to permissions;
- coordinating deliveries;
- approving returns and financial adjustments where authorized;
- viewing operational reports.

## 4.3 Accountant

**Purpose:** Financial management and reconciliation.

Typical responsibilities:

- payment verification and reconciliation;
- receivables;
- payables;
- journal review/posting where authorized;
- credit/refund processing where permitted;
- financial reporting;
- audit review.

## 4.4 Salesman

**Purpose:** Customer-facing sales activity and order creation.

Responsibilities include:

- viewing assigned customers;
- creating orders;
- selecting permitted selling prices;
- recording payments;
- uploading payment evidence for cheque/money order;
- viewing order progress;
- requesting item-level adjustments where allowed;
- printing invoices.

## 4.5 Warehouse Manager

**Purpose:** Physical inventory and fulfillment operations.

Responsibilities include:

- viewing assigned fulfillment work;
- confirming stock conditions;
- picking/packing or fulfillment steps;
- reporting damaged/unavailable goods;
- confirming physical stock movement;
- interacting with the Admin-controlled adjustment workflow.

## 4.6 Delivery Partner

**Purpose:** Execute assigned deliveries.

Responsibilities include:

- viewing assigned deliveries;
- accepting assignments;
- pickup confirmation;
- out-for-delivery status;
- delivery completion;
- delivery failure reasons;
- return-to-warehouse handling where applicable.

The Delivery Partner must not modify selling price, tax configuration, order financial values, customer credit limits, accounting entries, or refund decisions.

---

# 5. Portal & Application Surfaces

## 5.1 Salesman Portal

Primary features:

- authentication;
- assigned customer list;
- customer details;
- product/catalog browsing;
- permitted selling price selection;
- order creation;
- order history;
- order details;
- payment recording;
- payment evidence upload;
- adjustment/cancellation requests;
- customer outstanding information according to permissions;
- invoice preview/print/download.

## 5.2 Admin Portal

Primary features:

- dashboard;
- operational order workspace;
- customers;
- salesmen;
- products/categories;
- pricing/tax controls;
- inventory oversight;
- payments and reconciliation;
- delivery coordination;
- adjustments;
- returns;
- credits/refunds;
- suppliers/purchasing where enabled;
- accounting;
- reports;
- roles/permissions;
- audit logs;
- system settings;
- invoice preview/print/download.

## 5.3 Delivery Partner Portal

Primary features:

- secure login;
- assigned delivery list;
- delivery details;
- accept/reject assignment according to business policy;
- pickup confirmation;
- out-for-delivery status;
- delivered confirmation;
- delivery failure;
- reschedule/return-to-warehouse state where authorized.

---

# 6. End-to-End Business Workflow

## 6.1 Normal order flow

```text
Customer
   ↓
Salesman creates order
   ↓
Order submitted
   ↓
Admin reviews
   ↓
Order approved
   ↓
Inventory reserved
   ↓
Warehouse fulfills
   ↓
Order ready for delivery
   ↓
Delivery assigned
   ↓
Delivery accepted
   ↓
Out for delivery
   ↓
Delivered
   ↓
Order completed
```

Payment, invoice, and accounting are related transaction streams and must not be forced into a single linear state when business reality allows them to proceed independently.

## 6.2 Exception flow

```text
Warehouse detects stock problem
          ↓
Stock Exception
          ↓
Admin reviews
          ↓
Order Adjustment
          ↓
Specific quantity cancelled/reallocated
          ↓
Inventory updated
          ↓
Tax recalculated where applicable
          ↓
Financial impact recalculated
          ↓
Remaining quantities continue
```

The order must not be unnecessarily cancelled merely because one or more items become unavailable.

---

# 7. Customer Management

## 7.1 Customer master data

Customer records should support at minimum:

- unique customer identifier;
- customer/business name;
- primary contact;
- phone;
- email where available;
- billing address;
- delivery address(es) where required;
- assigned salesman;
- lifecycle status;
- credit limit;
- payment terms;
- current outstanding;
- order history;
- payment history;
- return/adjustment history;
- financial statement/aging.

## 7.2 Customer lifecycle

```text
ACTIVE
ON_HOLD
INACTIVE
```

### ACTIVE

Customer may participate in normal ordering subject to credit and business rules.

### ON_HOLD

New orders may require Admin approval or may be blocked depending on business policy.

### INACTIVE

Cannot create new orders. Historical transactions remain accessible.

## 7.3 Customer financial visibility

The system must support viewing:

- current outstanding;
- credit limit;
- payment terms;
- outstanding orders;
- payment history;
- credits/adjustments;
- refunds;
- aging;
- statement history.

---

# 8. Salesman Management

## 8.1 Salesman lifecycle

```text
INVITED → ACTIVE → SUSPENDED → ACTIVE
ACTIVE → INACTIVE
```

Admin controls state transitions according to role permissions.

## 8.2 Salesman data

Support:

- identity/profile;
- login/account status;
- assigned customers;
- order history;
- payment activity;
- adjustment requests;
- performance/operational reporting.

## 8.3 Access boundary

Salesmen should only access customers and order records they are authorized to access. Historical order ownership/assignment changes must remain auditable.

---

# 9. Product & Category Management

## 9.1 Product master

Each product should support:

- product ID;
- SKU;
- product name;
- category;
- description;
- product image(s) for catalog use;
- active/inactive state;
- cost price;
- MRP/List Price;
- default selling price;
- minimum allowed selling price;
- tax configuration;
- inventory information.

## 9.2 Product image rule

Product images may be shown in catalog/product interfaces.

**Product images must never appear on invoices.**

This is a hard document/output requirement.

## 9.3 Historical product data

Changes to product name, price, tax, or catalog data must not corrupt historical transaction snapshots.

---

# 10. Pricing

## 10.1 Pricing model

The product must support:

```text
Cost Price
MRP / List Price
Default Selling Price
Minimum Allowed Price
Actual Order Price
```

## 10.2 Pricing rule

For a normal order:

```text
Minimum Allowed Price ≤ Actual Order Price ≤ MRP/List Price
```

unless an authorized override is performed.

## 10.3 Salesman pricing

Salesman should be able to select an actual selling price within the allowed range.

## 10.4 Price override

A price below the minimum or other exceptional pricing must require appropriate authorization and create an audit record containing the old/new value and reason.

## 10.5 Historical pricing rule

Once an order item records its actual transaction price, later changes to product pricing must not alter the historical order.

---

# 11. Product-Specific Tax

## 11.1 Requirement

Tax is applicable and is **product-specific**.

Tax must therefore be represented at the order-item/line level rather than applying one undifferentiated tax percentage to the whole order.

## 11.2 Product tax configuration

A product should support a tax configuration that can determine whether tax applies and what tax rule/rate is applicable.

The implementation should prefer a reusable tax configuration/profile rather than scattering hard-coded tax percentages across business logic.

## 11.3 Order-line tax snapshot

When an order is created/processed, each order item must preserve the applicable tax information used for that transaction, including as appropriate:

- tax applicability;
- tax rate or rule identifier;
- tax amount;
- taxable amount;
- final line total.

## 11.4 Historical tax integrity

Changing the tax configuration of a product must not retroactively modify an existing order or invoice.

## 11.5 Mixed-tax order

One order may contain:

- taxable products;
- non-taxable products;
- products with different tax rates/rules.

The order total must aggregate the line-level tax calculations.

## 11.6 Tax impact of adjustments

When an item or quantity is cancelled or returned and that adjustment changes the taxable amount, the corresponding tax impact must also be calculated and recorded.

## 11.7 Tax policy TBD areas

The exact tax jurisdiction logic, exemption rules, rounding policy, and any future automated tax-service integration may require separate confirmation. Do not assume these policies until explicitly approved.

---

# 12. Order Management

## 12.1 Order lifecycle

Recommended lifecycle:

```text
DRAFT
  ↓
SUBMITTED
  ↓
PENDING APPROVAL
  ↓
APPROVED
  ↓
STOCK RESERVED
  ↓
PROCESSING
  ↓
READY FOR DELIVERY
  ↓
DELIVERY ASSIGNED
  ↓
DELIVERY ACCEPTED
  ↓
OUT FOR DELIVERY
  ↓
DELIVERED
  ↓
COMPLETED
```

Exceptions:

```text
PENDING APPROVAL → REJECTED
APPROVED → CANCELLED
PROCESSING → PARTIALLY CANCELLED / CANCELLED
OUT FOR DELIVERY → DELIVERY FAILED
DELIVERY FAILED → RESCHEDULED / RETURN TO WAREHOUSE
```

The final transition rules and actor permissions must be formalized in the Security & Access document.

## 12.2 Separate state dimensions

An order must not rely on one status field to represent all aspects of the transaction.

Maintain conceptually separate dimensions:

- Order status;
- Fulfillment status;
- Payment status;
- Delivery status;
- Return/Adjustment status.

Example:

```text
Order Status:       COMPLETED
Fulfillment:        PARTIALLY RETURNED
Payment:            PARTIALLY PAID
Delivery:           DELIVERED
```

This is valid and must be supported.

## 12.3 Order creation

Salesman can create an order for an eligible customer.

Order creation must capture:

- customer;
- salesman;
- items;
- quantities;
- actual selling prices;
- product tax snapshot;
- calculated subtotal;
- tax amount;
- adjustments/discounts if applicable;
- final order total;
- requested delivery information;
- timestamp;
- creator.

## 12.4 Order approval

Admin reviews a submitted order and may:

- approve;
- reject;
- request/perform allowed adjustment;
- identify credit-limit exception;
- identify price exception;
- identify operational issue.

## 12.5 Order rejection

Rejected orders must retain their original data and rejection reason/history.

## 12.6 Order cancellation

Whole-order cancellation is distinct from item-level cancellation.

Whole-order cancellation should only be allowed when business state and permission rules permit it.

---

# 13. Order Item & Quantity Allocation

## 13.1 Core requirement

Order fulfillment must use a quantity-based allocation model.

At minimum, each order item should conceptually track:

```text
Ordered Qty
Cancelled Qty
Reserved Qty
Picked Qty
Dispatched Qty
Delivered Qty
Return Requested Qty
Returned Qty
Accepted Return Qty
Rejected Return Qty
```

## 13.2 Quantity integrity

The system must enforce quantity constraints so that:

- cancelled quantity cannot exceed ordered quantity;
- additional cancellation cannot exceed remaining eligible quantity;
- delivered quantity cannot exceed fulfillable/dispatched quantity under the applicable business state;
- return quantity cannot exceed delivered quantity minus already-returned quantity;
- warehouse allocation cannot create logically impossible negative quantities.

## 13.3 Example

Original:

```text
Product X
Ordered = 10
```

After 2-unit cancellation:

```text
Ordered = 10
Cancelled = 2
Fulfillable = 8
```

The original ordered quantity remains 10.

## 13.4 Quantity allocation and inventory

Inventory reservations should be tied to the specific order/item allocation so that later cancellation or fulfillment can release or consume the correct quantity.

---

# 14. Order Adjustment + Item Quantity Allocation System

## 14.1 Core requirement

The system must use a first-class **Order Adjustment** framework instead of destructive edits for post-submission changes.

The adjustment system is a core product capability used by Admin and, through request workflows, by Salesmen.

## 14.2 Primary adjustment use case

A salesman places an order containing 10 items. At order creation all requested items are available. After the order is placed, warehouse circumstances such as damaged goods make 2 specific items or quantities impossible to deliver.

The system must allow the affected item quantity to be cancelled/adjusted while allowing the remaining order quantities to proceed normally.

## 14.3 Adjustment example

```text
Original order:
Product A = 5
Product B = 10
Product C = 8

Later:
2 units of Product B are damaged.

Adjustment:
Product B
Ordered = 10
Cancelled = 2
Fulfillable = 8
```

The order remains operational.

## 14.4 Adjustment types for V1

At minimum:

- item cancellation;
- partial quantity cancellation;
- full line cancellation where all remaining quantity is cancelled;
- authorized price adjustment where explicitly enabled;
- operational quantity adjustment where permitted.

Returns and credit notes are separate financial/physical processes, even though they can reuse the adjustment architecture conceptually.

## 14.5 Salesman adjustment permissions

Salesman may request an item/quantity adjustment where permitted.

The request should capture:

- requested item;
- requested quantity;
- reason;
- notes;
- request timestamp;
- requesting user.

## 14.6 Admin adjustment permissions

Admin should be able to:

- review adjustment requests;
- approve;
- reject;
- create an authorized adjustment directly when a warehouse/operational issue is reported;
- view financial/tax/inventory impact;
- view adjustment history;
- reverse an adjustment only if the business state and permission policy allow it.

## 14.7 Warehouse relationship

Warehouse personnel should report physical stock problems rather than silently rewriting commercial orders.

Typical flow:

```text
Warehouse detects issue
       ↓
Stock Exception
       ↓
Admin reviews
       ↓
Admin creates/approves Order Adjustment
       ↓
System recalculates affected quantities and financial impact
```

## 14.8 Adjustment record

Every finalized adjustment should retain at minimum:

- adjustment ID;
- order ID;
- order item ID;
- adjustment type;
- quantity/value affected;
- reason;
- notes;
- previous state snapshot;
- new state snapshot;
- inventory impact;
- tax impact;
- financial impact;
- requested by;
- approved by;
- applied timestamp;
- status.

## 14.9 Adjustment statuses

Recommended:

```text
REQUESTED
PENDING_APPROVAL
APPROVED
REJECTED
APPLIED
REVERSED
```

## 14.10 Multiple adjustments

The system must support multiple sequential adjustments against the same order item, provided each new adjustment respects remaining eligible quantity and business state rules.

Example:

```text
Ordered = 20
First adjustment = cancel 3
Second adjustment = cancel 2
Final cancelled = 5
Remaining = 15
```

## 14.11 No destructive quantity rewriting

The system must never make the original ordered quantity disappear merely because a quantity was cancelled.

---

# 15. Order Adjustment Financial Impact

## 15.1 Recalculation principle

An approved item/quantity adjustment must cause the applicable order-level financial values to be recalculated from line-level transactional data.

The system should preserve both:

- original order financial values;
- current net financial position.

## 15.2 Example

```text
Original Subtotal = $2,000
Original Tax      = $100
Original Total    = $2,100

Adjustment:
Item value = -$500
Tax impact = -$50

Current Subtotal = $1,500
Current Tax      = $50
Current Total    = $1,550
```

The actual tax impact depends on the tax configuration of the affected product.

## 15.3 Adjustment impact preview

Before a sensitive adjustment is applied, Admin should be able to see the expected:

- quantity impact;
- inventory impact;
- subtotal impact;
- tax impact;
- order total impact;
- payment/outstanding impact;
- potential credit/refund impact.

This preview is a recommended usability requirement.

---

# 16. Inventory Management

## 16.1 Inventory concepts

The system should distinguish:

```text
ON HAND
RESERVED
AVAILABLE
DAMAGED
```

Conceptually:

```text
AVAILABLE = ON HAND - RESERVED
```

## 16.2 Reservation

Approved/eligible orders reserve stock according to business rules.

Reservation must be atomic enough to prevent simultaneous orders from consuming the same last available quantity.

## 16.3 Cancellation impact

When reserved quantity is cancelled:

- the order allocation is reduced;
- the associated reservation is released or reclassified according to physical stock condition;
- the adjustment is recorded.

If goods are damaged, they must not be returned blindly to available stock.

Example:

```text
Reserved → Damaged
```

or another physically accurate stock movement based on the actual warehouse event.

## 16.4 Inventory movements

Inventory should maintain movement/history records rather than relying exclusively on one mutable stock number.

Examples:

```text
Available → Reserved
Reserved → Picked
Picked → Dispatched
On Hand → Damaged
Warehouse Receipt → Available
```

## 16.5 Manual inventory adjustment

Authorized manual adjustments must record:

- quantity;
- reason;
- actor;
- timestamp;
- previous quantity/state;
- resulting quantity/state.

## 16.6 Negative inventory

The system should prevent negative available inventory unless an explicitly approved future business rule introduces controlled backorder behavior.

## 16.7 Concurrent orders

Two simultaneous order operations targeting the same scarce inventory must not create duplicate reservations or allow the available quantity to become logically negative.

---

# 17. Warehouse & Fulfillment

## 17.1 Warehouse role

Warehouse is responsible for executing physical operations and reporting exceptions.

## 17.2 Fulfillment statuses

Typical fulfillment progression:

```text
NOT STARTED
RESERVED
PROCESSING
PICKED
PACKED
DISPATCHED
DELIVERED
```

The exact UI terminology can be refined later, but the quantity model must remain authoritative.

## 17.3 Stock exception

A warehouse stock exception should identify:

- order;
- item;
- affected quantity;
- issue reason;
- notes;
- reported by;
- timestamp.

Admin then evaluates the commercial/transactional adjustment.

---

# 18. Delivery Management

## 18.1 Delivery lifecycle

```text
PENDING ASSIGNMENT
      ↓
ASSIGNED
      ↓
ACCEPTED
      ↓
PICKED UP
      ↓
OUT FOR DELIVERY
      ↓
DELIVERED
```

Exception:

```text
OUT FOR DELIVERY
      ↓
DELIVERY FAILED
      ↓
RESCHEDULED
   OR
RETURN TO WAREHOUSE
```

## 18.2 Delivery failure reasons

Support structured reasons such as:

- customer unavailable;
- wrong/incomplete address;
- customer refused;
- payment issue;
- transport/vehicle issue;
- damaged goods;
- other.

An additional note can be captured when necessary.

## 18.3 Delivery quantity rule

Delivery users must receive the current deliverable quantity, not the original ordered quantity when some quantity has been cancelled or otherwise removed from fulfillment.

Example:

```text
Ordered = 10
Cancelled = 2
Deliverable = 8

Delivery quantity = 8
```

## 18.4 Delivery permissions

Delivery Partner cannot:

- change selling price;
- change tax configuration;
- change customer credit limit;
- approve financial refunds;
- create accounting adjustments;
- alter original ordered quantity;
- override Admin approval requirements.

---

# 19. Payment Management

## 19.1 Supported payment methods — CONFIRMED

V1 supports:

1. **Cash**
2. **Money Order**
3. **Cheque**

The payment system must be extensible so additional methods can be added later without redesigning the order domain.

## 19.2 Payment transaction separation

Payment must be represented as a separate transaction entity from the order.

A single order may have multiple payment transactions.

Example:

```text
Order Total = $5,000

Cash       = $1,000
Cheque     = $2,000
Money Order= $1,000
Outstanding= $1,000
```

## 19.3 Cash

Cash payment should capture:

- amount;
- date/time;
- recorder;
- related customer/order/reference;
- status;
- notes where applicable.

No file attachment is required for normal cash.

## 19.4 Money Order

Money Order payment must capture:

- amount;
- date;
- relevant reference/details where applicable;
- JPEG confirmation image;
- uploader;
- timestamp;
- verification/reconciliation state.

The JPEG is evidence for the payment transaction.

## 19.5 Cheque

Cheque payment must capture at minimum:

- amount;
- cheque number;
- cheque date;
- bank name where required;
- JPEG cheque image;
- uploader;
- timestamp;
- verification/reconciliation state.

Additional cheque metadata can be added later without changing the core payment concept.

## 19.6 Payment status

Recommended payment lifecycle:

```text
RECORDED
   ↓
PENDING VERIFICATION / RECONCILIATION
   ↓
CONFIRMED
```

Exceptional transitions may include:

```text
CONFIRMED → REVERSED
CHEQUE → RETURNED / BOUNCED
```

The final exact cheque and money-order verification policy remains configurable/TBD if not yet established by the client.

## 19.7 Payment evidence rule

For Money Order and Cheque, the JPEG evidence requirement is mandatory unless a future approved business rule explicitly changes it.

## 19.8 Payment attachment storage

Payment evidence should be stored in file/object storage with database metadata such as:

- storage key/path;
- URL/reference;
- original filename;
- MIME type;
- size;
- uploaded by;
- uploaded at;
- payment ID.

The image should be linked to the payment transaction, not treated as an undifferentiated order attachment.

## 19.9 Payment verification

Pending payments should not be treated as fully confirmed where the business policy requires verification before they affect available financial credit, reconciliation, or final settlement.

The exact treatment of pending cheque/money-order payments in credit exposure and accounting is a policy item that may require client/accountant confirmation.

---

# 20. Payment, Adjustment & Customer Balance

## 20.1 Net liability principle

Conceptually:

```text
Net Customer Liability
= Order Value
+ Applicable Charges
- Discounts
- Approved Cancellations
- Approved Returns
- Credit Notes
```

Outstanding is then derived from the applicable payment transactions.

## 20.2 Outstanding

Conceptually:

```text
Outstanding
= MAX(Net Customer Liability - Applicable Paid Amount, 0)
```

The exact inclusion rules for pending/unconfirmed payment statuses must follow the approved payment policy.

## 20.3 Credit/refund due

Conceptually:

```text
Credit / Refund Due
= MAX(Applicable Paid Amount - Net Customer Liability, 0)
```

## 20.4 Adjustment example — partially paid

```text
Original order = $2,100
Paid          = $1,000
Adjustment    = -$600

New liability = $1,500
Outstanding   = $500
```

## 20.5 Adjustment example — fully paid

```text
Original order = $2,100
Paid          = $2,100
Adjustment    = -$600

New liability = $1,500
Credit/Refund = $600
```

The final business policy for account credit versus cash refund should be confirmed before production if not already defined.

---

# 21. Invoice & Financial Documents

## 21.1 Invoice availability

Invoice functionality must be available in:

- Salesman Portal;
- Admin Portal.

## 21.2 Invoice actions

Authorized users should have:

- preview;
- print;
- download/save as PDF;
- reprint/reopen historical invoice where appropriate.

## 21.3 Invoice numbering

Invoice number should be a separate business identifier from the order number.

Example:

```text
Order:   ORD-001234
Invoice: INV-000987
```

## 21.4 Invoice contents

At minimum:

- company identity/details;
- invoice number;
- invoice date;
- order number;
- customer/business information;
- billing address;
- delivery address where needed;
- line items;
- SKU;
- quantity;
- actual transaction price;
- product-specific tax;
- subtotal;
- applicable adjustments;
- tax total;
- grand total;
- payment summary/status where appropriate.

## 21.5 Hard invoice image rule

> **Product images must never be displayed or embedded in invoices.**

The invoice is a text/data financial document, not a product catalog.

Payment evidence images such as cheque/money-order JPEGs must also not automatically appear in the invoice. They remain accessible from the payment details/administrative workflow.

## 21.6 Historical invoice integrity

An issued invoice must not silently change because:

- product price changes;
- product tax changes;
- product name changes;
- catalog image changes;
- later inventory changes.

Subsequent financial changes should be represented using the appropriate adjustment/credit/refund process according to the approved accounting workflow.

## 21.7 Invoice and adjustments

Depending on the business state, a post-issue change may require:

- revised invoice;
- adjustment document;
- credit note;
- refund;
- account credit.

The exact financial document policy should be aligned with the client's accounting practice.

---

# 22. Admin Order Workspace

## 22.1 Purpose

The Admin must be able to monitor and process many orders continuously without using one giant, jumbled order list.

The Orders area therefore uses operational sub-pages/queues.

## 22.2 Recommended structure

```text
ORDERS
 ├── New Orders
 ├── Active Orders
 ├── Delivery
 ├── Adjustments
 ├── Completed
 ├── Cancelled
 └── All Orders
```

These are filtered operational views of the same underlying order records, not separate order databases.

## 22.3 New Orders

Purpose: Admin's operational inbox.

Contains orders requiring attention, such as:

- submitted;
- pending approval;
- other newly arrived actionable states.

Typical columns:

- order number;
- customer;
- salesman;
- order total;
- payment status;
- item count;
- placed time/date;
- priority/exception indicator;
- action.

When Admin processes the order, it should move out of New Orders into the appropriate next operational view automatically through its state.

## 22.4 Active Orders

Contains orders currently in an operational fulfillment workflow, such as:

- approved;
- reserved;
- processing;
- ready for delivery;
- assigned/accepted where useful.

## 22.5 Delivery

Dedicated operational view for deliveries:

- ready for assignment;
- assigned;
- accepted;
- picked up;
- out for delivery;
- delivery failed;
- delivered.

## 22.6 Adjustments

Dedicated view for:

- pending adjustment requests;
- stock exception-driven adjustments;
- recently applied adjustments;
- rejected adjustments;
- adjustments requiring Admin attention.

## 22.7 Completed

Contains completed transactions while keeping historical data available.

## 22.8 Cancelled

Contains fully cancelled/rejected orders as appropriate.

A partially cancelled order must not be moved into Cancelled merely because one item or quantity was cancelled.

## 22.9 All Orders

A complete search/history workspace with filters such as:

- order number;
- customer;
- salesman;
- status;
- payment status;
- delivery status;
- date range;
- amount range;
- adjustment existence.

## 22.10 Global order search

Admin must be able to locate an order regardless of which operational sub-page currently contains it.

## 22.11 Queue badges

Operational sub-pages should support counts/badges where useful, e.g.:

```text
New Orders            12
Adjustments             6
Payment Verification    4
Delivery Exceptions     2
```

These counts are intended to make the Admin workspace function as an operational inbox.

---

# 23. Cancellation vs Return vs Credit vs Refund

These must remain separate business concepts.

## 23.1 Cancellation

Removes an unfulfilled commitment or quantity from the order.

## 23.2 Return

Brings previously delivered goods back into the business's physical control.

## 23.3 Credit Note

Reduces the customer's financial liability without necessarily returning physical cash immediately.

## 23.4 Refund

Returns actual money to the customer.

These processes may interact but should not be collapsed into one generic “cancel” action.

---

# 24. Returns

## 24.1 Return lifecycle

```text
RETURN REQUESTED
        ↓
PENDING REVIEW
        ↓
APPROVED
        ↓
PICKUP REQUIRED
        ↓
PICKED UP
        ↓
WAREHOUSE RECEIVED
        ↓
INSPECTION
        ↓
ACCEPTED / PARTIALLY ACCEPTED / REJECTED
```

## 24.2 Partial returns

The system must support quantity-level returns.

Example:

```text
Delivered = 8
Returned = 3
Accepted Return = 2
Rejected Return = 1
```

## 24.3 Return inspection

Accepted good units can return to sellable inventory where appropriate.

Damaged/unsellable units must be moved to damaged or other appropriate stock classification.

## 24.4 Financial impact

Only accepted return quantities should create the applicable financial credit according to the approved return policy.

---

# 25. Customer Credit Limits

## 25.1 Credit attributes

Customer may have:

- credit limit;
- current outstanding;
- payment terms.

## 25.2 Exposure calculation

Conceptually:

```text
Projected Exposure
= Current Outstanding
+ New Order Value
- Cash/Applicable Payment Applied at Order
```

## 25.3 Exceeding credit limit

If projected exposure exceeds the credit limit:

- flag the order;
- require Admin approval according to permission policy;
- do not allow a Salesman to silently bypass the rule.

## 25.4 Historical integrity

Changing the credit limit later must not rewrite historical orders.

---

# 26. Receivables & Customer Statements

## 26.1 Customer statement

The system should provide a chronological statement including:

- opening balance where supported;
- order/invoice entries;
- payments;
- approved credits;
- refunds;
- adjustments;
- running balance.

## 26.2 Aging

Initial aging buckets:

```text
0–30 days
31–60 days
61–90 days
90+ days
```

The aging basis and date policy should be formalized in accounting/technical specifications.

---

# 27. Supplier & Purchasing Domain

## 27.1 Conceptual lifecycle

```text
Supplier
   ↓
Purchase Order
   ↓
Goods Received
   ↓
Purchase Invoice
   ↓
Accounts Payable
   ↓
Payment
```

## 27.2 V1 approach

The domain should be structured so purchasing can support inventory/accounting needs, while UI breadth may be phased according to the confirmed V1 scope and budget.

## 27.3 Extensibility

The supplier/purchasing model must not prevent later addition of supplier portal or advanced purchasing workflows.

---

# 28. Accounting

## 28.1 Objective

Accounting should be based on a structured financial transaction model rather than manually editable totals.

## 28.2 Core concepts

- Chart of Accounts;
- Journal Entries;
- Journal Lines;
- General Ledger;
- Accounts Receivable;
- Accounts Payable;
- Cash;
- Trial Balance;
- Profit & Loss;
- Balance Sheet.

## 28.3 Example accounting mappings

Conceptually:

### Sale/invoice

```text
DR Accounts Receivable
CR Sales Revenue
```

### Cash payment

```text
DR Cash
CR Accounts Receivable
```

### Cost of goods sold

```text
DR COGS
CR Inventory
```

### Return/credit

```text
DR Sales Returns / Revenue Adjustment
CR Accounts Receivable
```

### Accepted returned stock

Subject to accounting policy:

```text
DR Inventory
CR COGS
```

### Cash refund

```text
DR Customer Refund / Credit Balance
CR Cash
```

### Supplier invoice

```text
DR Inventory / Expense
CR Accounts Payable
```

### Supplier payment

```text
DR Accounts Payable
CR Cash
```

These mappings are conceptual and must be validated against the client's accountant and final accounting policy before production financial posting is relied upon.

## 28.4 No deletion of posted financial history

A posted financial transaction must not be silently deleted. Corrections should use controlled reversal/adjustment mechanisms.

## 28.5 Accounting policy TBD items

Final revenue recognition timing, COGS timing, treatment of pending cheque/money order, refund accounting, credit-note sequence, and other accounting-policy decisions may require accountant confirmation.

---

# 29. Reporting

## 29.1 Sales reports

- sales by day;
- sales by period;
- sales by customer;
- sales by salesman;
- sales by product;
- adjusted/cancelled sales where useful.

## 29.2 Inventory reports

- on-hand stock;
- available stock;
- reserved stock;
- damaged stock;
- inventory movements;
- low-stock indicators.

## 29.3 Payment reports

- cash received;
- cheque received;
- money orders received;
- pending verification;
- confirmed payments;
- reversed/returned payments;
- reconciliation information.

## 29.4 Receivable reports

- current outstanding;
- customer balances;
- aging;
- overdue customers;
- statements.

## 29.5 Operational reports

- new orders;
- active orders;
- delivery status;
- failed deliveries;
- adjustments;
- returns;
- warehouse exceptions.

## 29.6 Accounting reports

- General Ledger;
- Trial Balance;
- Profit & Loss;
- Balance Sheet;
- Accounts Receivable;
- Accounts Payable.

---

# 30. Notifications & Business Events

## 30.1 Product-level events

The product should expose business events conceptually for important occurrences such as:

- order created;
- order submitted;
- order approved;
- order rejected;
- adjustment requested;
- adjustment approved;
- adjustment rejected;
- payment recorded;
- payment verified;
- payment reversed;
- payment returned/bounced;
- delivery assigned;
- delivery completed;
- delivery failed;
- return requested;
- return approved;
- credit/refund issued.

## 30.2 Notification channels

Initial exact channel set can be phased. Possible future channels include:

- in-app;
- email;
- SMS;
- WhatsApp.

The business event should be independent of the eventual delivery channel.

---

# 31. Authentication & User Access — Product-Level Requirements

Detailed permissions belong in the Security & Access document, but the PRD establishes the business expectations.

## 31.1 Authentication

The system must provide secure authentication for all portals.

## 31.2 Stronger controls for privileged users

Admin, Super Admin, and Accountant accounts should support stronger authentication controls, with MFA/step-up authentication considered or required for sensitive operations according to the final security specification.

## 31.3 Permission enforcement

Permissions must be enforced in the backend. Hiding buttons in the frontend is not a security control.

## 31.4 Session/account control

The product should support:

- secure password handling;
- password reset;
- login throttling;
- failed-login handling;
- session revocation;
- role changes taking effect appropriately.

---

# 32. Auditability

## 32.1 Audited actions

The application should audit at minimum:

- login;
- failed login;
- logout;
- password/security changes;
- customer changes;
- product changes;
- price changes;
- tax configuration changes;
- order creation;
- order edits;
- order approval/rejection;
- item adjustments;
- payment creation;
- payment verification;
- payment reversal;
- return requests/approval;
- refund/credit actions;
- inventory adjustments;
- delivery status changes;
- permission changes;
- accounting entry posting/reversal.

## 32.2 Audit data

Audit records should identify, where applicable:

- actor;
- role;
- timestamp;
- action;
- entity;
- entity ID;
- before state;
- after state;
- reason;
- relevant metadata.

## 32.3 No silent erasure

Critical historical records should not disappear from the business audit trail merely because a user no longer wants to see them.

---

# 33. Core Business Rules Catalog

The following rules are baseline rules for implementation and testing.

| Rule ID | Requirement | Status |
|---|---|---|
| BR-ORD-001 | Original ordered quantity must never be overwritten by later cancellations. | CONFIRMED |
| BR-ORD-002 | Cancellation quantity cannot exceed remaining eligible quantity. | CONFIRMED |
| BR-ORD-003 | Partial item/quantity cancellation must be supported. | CONFIRMED |
| BR-ORD-004 | An order can remain active after partial item cancellation. | CONFIRMED |
| BR-ORD-005 | Order, fulfillment, payment and delivery states are separate dimensions. | CONFIRMED |
| BR-ADM-001 | Admin is the primary operational middle layer between Salesman and Warehouse. | CONFIRMED |
| BR-INV-001 | Available inventory cannot logically become negative through normal reservation. | CONFIRMED |
| BR-INV-002 | Damaged stock must not be treated as available sellable stock. | CONFIRMED |
| BR-PAY-001 | V1 supports Cash, Cheque and Money Order. | CONFIRMED |
| BR-PAY-002 | Money Order payments require JPEG evidence. | CONFIRMED |
| BR-PAY-003 | Cheque payments require JPEG evidence. | CONFIRMED |
| BR-PAY-004 | Payment is a separate transaction from the order. | CONFIRMED |
| BR-PAY-005 | Cheque can have a returned/bounced state. | PROPOSED / VERIFY |
| BR-TAX-001 | Tax is product-specific and calculated at order-line level. | CONFIRMED |
| BR-TAX-002 | Historical order tax must remain unchanged after product tax changes. | CONFIRMED |
| BR-PRI-001 | Actual transaction price must be retained historically. | CONFIRMED |
| BR-PRI-002 | Minimum selling price rule requires authorization for exceptions. | CONFIRMED |
| BR-DLV-001 | Delivery sees current deliverable quantity, not cancelled quantity. | CONFIRMED |
| BR-DOC-001 | Invoice must not display product images. | CONFIRMED |
| BR-DOC-002 | Invoice number is separate from order number. | CONFIRMED |
| BR-ADM-002 | Admin Orders uses operational sub-pages/queues instead of one giant list. | CONFIRMED |
| BR-RET-001 | Cancellation and Return are separate processes. | CONFIRMED |
| BR-FIN-001 | Financial corrections should use controlled adjustments/reversals rather than silent deletion. | CONFIRMED |
| BR-SEC-001 | Authorization must be enforced server-side. | CONFIRMED |
| BR-AUD-001 | Critical changes must be auditable. | CONFIRMED |

---

# 34. Admin Order Queue Rules

The following are UI/product rules, not separate datasets.

| Queue | Primary purpose | Typical inclusion |
|---|---|---|
| New Orders | Immediate Admin attention | Submitted/Pending Approval |
| Active Orders | Ongoing operational work | Approved/Reserved/Processing/Ready |
| Delivery | Delivery operations | Assigned/Accepted/Out for Delivery/Failed/Delivered |
| Adjustments | Change/exception processing | Requested/Pending/Recent Applied |
| Completed | Finished orders | Completed |
| Cancelled | Fully ended cancelled/rejected transactions | Cancelled/Rejected |
| All Orders | Full search/history | All |

An order should appear in the queue determined by current state; it is not physically moved between tables in the database merely because the UI view changed.

---

# 35. End-to-End Acceptance Scenarios

## Scenario A — Normal order

```text
Salesman logs in
→ selects active customer
→ selects products and quantities
→ selects valid selling prices
→ system calculates line taxes
→ order submitted
→ Admin reviews
→ Admin approves
→ inventory reserved
→ warehouse fulfills
→ delivery assigned
→ delivery completed
→ invoice available
→ order completed
```

Expected outcome: one consistent transaction is visible across the applicable portals.

## Scenario B — Damaged goods after order placement

```text
Order contains 10 units/items.
All are initially available.

Later warehouse finds that 2 specific units cannot be delivered.

Warehouse reports stock exception.
Admin reviews.
Admin creates/approves adjustment.
2 units are cancelled.
8 remain fulfillable.
Inventory allocation changes.
Tax impact is recalculated.
Financial impact is recalculated.
Delivery receives only 8.
Audit history records the change.
```

## Scenario C — Partial cancellation with partial payment

```text
Original order total = $2,100
Customer paid = $1,000
Adjustment = -$600

New liability = $1,500
Outstanding = $500
```

Expected outcome: the order remains active and the customer balance reflects the net transaction.

## Scenario D — Full payment followed by downward adjustment

```text
Original order = $2,100
Paid = $2,100
Adjustment = -$600

Net liability = $1,500
Credit/refund due = $600
```

Expected outcome: no loss of payment history; financial difference is represented through the approved credit/refund process.

## Scenario E — Mixed tax order

```text
Product A: taxable
Product B: non-taxable
Product C: different applicable tax
```

Expected outcome: each line retains its own applied tax calculation and the order total aggregates the results.

## Scenario F — Cheque payment

```text
Salesman records cheque
→ enters amount/details
→ uploads JPEG
→ payment stored
→ verification/reconciliation workflow
→ authorized user confirms/rejects/returns as applicable
```

Expected outcome: the JPEG is attached to the payment transaction, not displayed automatically on the invoice.

## Scenario G — Money Order payment

Same pattern as cheque, with mandatory JPEG confirmation evidence.

## Scenario H — Historical tax change

```text
Old order uses 5% product tax.
Product configuration later changes to another tax rule.
```

Expected outcome: old order/invoice remains unchanged.

## Scenario I — Historical price change

```text
Old order item price = $22.
Product's current selling price later becomes $25.
```

Expected outcome: historical order/invoice still shows $22.

## Scenario J — Partial return

```text
Delivered = 8
Customer returns = 3
Accepted = 2
Rejected = 1
```

Expected outcome: only accepted returned quantity creates applicable financial/inventory credit according to policy.

## Scenario K — Multiple order adjustments

```text
Ordered = 20
Adjustment 1 = cancel 3
Adjustment 2 = cancel 2
```

Expected outcome:

```text
Ordered = 20
Cancelled = 5
Remaining = 15
```

## Scenario L — Multiple payments

```text
Order = $5,000
Cash = $1,000
Cheque = $2,000
Money Order = $1,000
Outstanding = $1,000
```

Expected outcome: three separate payment transactions tied to one order.

---

# 36. Edge Case Matrix

| Edge Case | Expected Product Behavior | Severity | V1 |
|---|---|---|---|
| Two orders compete for last stock | Prevent double reservation/negative availability | Critical | Yes |
| Salesman double-submits due to network retry | Prevent duplicate transaction | Critical | Yes |
| Payment submitted twice | Detect/avoid accidental duplicate recording | High | Yes |
| Cheque later bounces | Mark payment appropriately and recompute receivable | High | Yes |
| Cheque image missing | Block/flag according to payment policy | High | Yes |
| Money order image missing | Block/flag according to payment policy | High | Yes |
| Product damaged after reservation | Admin adjustment + inventory reclassification | Critical | Yes |
| Partial cancellation | Preserve original qty and adjust remaining qty | Critical | Yes |
| Repeated partial cancellation | Never exceed eligible remaining quantity | High | Yes |
| Cancellation after dispatch | Treat as return/business exception, not simple cancellation | High | Yes |
| Partial delivery | Track delivered quantity independently | High | Yes |
| Partial return | Track return quantities independently | High | Yes |
| Return partially accepted | Financial credit only for accepted quantity | High | Yes |
| Tax changes later | Historical tax snapshot remains unchanged | Critical | Yes |
| Price changes later | Historical transaction price remains unchanged | Critical | Yes |
| Payment exceeds final liability after adjustment | Compute credit/refund due | High | Yes |
| User loses permission mid-session | Sensitive operation revalidated against current permissions | High | Yes |
| Delivery failure | Capture reason and next state | Medium | Yes |
| Adjustment after later fulfillment event | Allow/reject based on state transition rules, never silently violate quantity history | Critical | Yes |
| Accounting posting failure | Avoid partial inconsistent financial posting | Critical | Yes |

---

# 37. Success Metrics

Success should be measured in operational outcomes, not just page count.

## 37.1 Operational efficiency

- reduced time to create an order;
- reduced time for Admin to process a new order;
- reduced time to find a historical order;
- reduced time to process an adjustment;
- reduced time to verify a payment.

## 37.2 Data accuracy

- low inventory discrepancy rate;
- low payment reconciliation discrepancy rate;
- low order total mismatch rate;
- correct tax calculation rate;
- correct customer outstanding balances.

## 37.3 Operational visibility

Admin should be able to determine quickly:

- what needs attention now;
- which orders are active;
- which adjustments are pending;
- which payments require verification;
- which deliveries failed;
- which customers have significant outstanding balances.

The exact numeric target values should be agreed after the client's current manual process and baseline metrics are understood.

---

# 38. Non-Functional Product Requirements

The implementation must ultimately satisfy the following product-level quality characteristics. Detailed technical targets belong in the architecture document.

## 38.1 Reliability

Transactions involving orders, inventory, payments, and financial impact should not leave the system in an obviously inconsistent state if an operation fails halfway through.

## 38.2 Performance

Normal operational pages should remain responsive with hundreds of customers and an accumulating order history. Large historical datasets should use appropriate pagination/filtering rather than loading everything at once.

## 38.3 Scalability

The initial scale is 500–700 customers, but the system should be structured so growth can occur without redesigning the product domain.

## 38.4 Usability

The Admin portal should minimize repetitive navigation and prioritize action queues. Salesman order creation should be fast and predictable.

## 38.5 Data integrity

Historical financial and operational transactions must not be silently overwritten.

## 38.6 Auditability

Critical actions must be traceable.

---

# 39. V1 Scope

## 39.1 Included / Confirmed or Core

The initial V1 scope includes the core of:

- authentication;
- role-based access;
- three primary portals;
- customer management;
- salesman management;
- product/category management;
- pricing controls;
- product-specific tax;
- order creation and approval;
- order status workflow;
- item quantity allocation;
- Order Adjustment + Item Quantity Allocation;
- inventory reservation and movements;
- warehouse exception flow;
- delivery workflow;
- Cash payment;
- Cheque payment;
- Money Order payment;
- JPEG payment evidence;
- payment verification/reconciliation;
- invoice generation/print/PDF;
- no-product-image invoice rule;
- cancellation;
- returns;
- credits/refunds;
- customer receivables;
- customer statements/aging;
- core accounting foundations;
- operational reports;
- audit logging.

## 39.2 Explicitly not a current priority

The following should not be assumed as mandatory V1 features unless later promoted into scope:

- online payment gateway;
- advanced promotion/coupon engine;
- loyalty program;
- advanced predictive forecasting;
- AI forecasting/agent features;
- customer self-service portal;
- supplier self-service portal;
- multi-country operation;
- multi-company accounting;
- advanced multi-warehouse orchestration;
- barcode/scan ecosystem;
- batch/lot/expiry management;
- complex external tax service automation;
- sophisticated marketing automation.

---

# 40. Future Extensibility Requirements

The application must be capable of evolving without replacing its core business concepts.

## 40.1 Payment extensibility

The payment architecture should allow future addition of methods such as bank transfer or online payment.

## 40.2 Adjustment extensibility

The adjustment framework should support future adjustment categories such as:

- price adjustment;
- delivery adjustment;
- return adjustment;
- credit-note-linked adjustment.

## 40.3 Tax extensibility

Tax should be configurable and historical transactions should remain immutable.

## 40.4 Portal extensibility

A future warehouse portal or customer portal should be able to connect to the same backend transaction core rather than requiring duplicate business logic.

## 40.5 Notification extensibility

Business events should be separable from notification channels.

## 40.6 Reporting extensibility

Reports should derive from transactional data rather than maintaining isolated numbers that can drift from the source of truth.

---

# 41. Requirement Traceability IDs

The implementation documents should use stable IDs to trace requirements across the five-document chain.

Suggested initial IDs:

### Product / General

- PRD-GEN-001 — Product identity is replaceable/configurable.
- PRD-GEN-002 — Single source of truth.
- PRD-GEN-003 — Non-destructive historical records.
- PRD-GEN-004 — Extensible domain design.

### Customers

- FEAT-CUST-001 — Customer management.
- FEAT-CUST-002 — Customer status lifecycle.
- FEAT-CUST-003 — Credit limit.
- FEAT-CUST-004 — Customer statement/aging.

### Salesman

- FEAT-SLS-001 — Salesman management.
- FEAT-SLS-002 — Assigned-customer access.
- FEAT-SLS-003 — Order creation.
- FEAT-SLS-004 — Salesman adjustment request.

### Orders

- FEAT-ORD-001 — Order creation.
- FEAT-ORD-002 — Order submission.
- FEAT-ORD-003 — Order approval.
- FEAT-ORD-004 — Order state management.
- FEAT-ORD-005 — Order item quantity allocation.
- FEAT-ORD-006 — Order Adjustment framework.
- FEAT-ORD-007 — Partial quantity cancellation.
- FEAT-ORD-008 — Adjustment impact preview.

### Inventory

- FEAT-INV-001 — Inventory reservation.
- FEAT-INV-002 — Inventory movement history.
- FEAT-INV-003 — Damaged inventory.
- FEAT-INV-004 — Warehouse stock exception.

### Payments

- FEAT-PAY-001 — Cash payment.
- FEAT-PAY-002 — Cheque payment.
- FEAT-PAY-003 — Money Order payment.
- FEAT-PAY-004 — Payment verification.
- FEAT-PAY-005 — Payment attachment.
- FEAT-PAY-006 — Payment reversal/returned cheque.

### Tax

- FEAT-TAX-001 — Product tax configuration.
- FEAT-TAX-002 — Order-line tax snapshot.
- FEAT-TAX-003 — Adjustment tax impact.

### Delivery

- FEAT-DLV-001 — Delivery assignment.
- FEAT-DLV-002 — Delivery status lifecycle.
- FEAT-DLV-003 — Delivery failure.

### Invoicing

- FEAT-DOC-001 — Invoice generation.
- FEAT-DOC-002 — Invoice preview.
- FEAT-DOC-003 — Invoice printing.
- FEAT-DOC-004 — Invoice PDF.
- FEAT-DOC-005 — Invoice historical integrity.
- FEAT-DOC-006 — No product images in invoice.

### Returns / Finance

- FEAT-RET-001 — Return request.
- FEAT-RET-002 — Return inspection.
- FEAT-RET-003 — Credit/refund handling.
- FEAT-FIN-001 — Receivables.
- FEAT-FIN-002 — Accounting foundation.

### Admin Workspace

- FEAT-ADM-001 — New Orders queue.
- FEAT-ADM-002 — Active Orders queue.
- FEAT-ADM-003 — Delivery queue.
- FEAT-ADM-004 — Adjustments queue.
- FEAT-ADM-005 — Completed queue.
- FEAT-ADM-006 — Cancelled queue.
- FEAT-ADM-007 — All Orders search/history.

---

# 42. Change Management Protocol

Future client requests must not be implemented as casual untracked modifications.

When a new requirement arrives:

```text
1. Record the request.
2. Assign a Change ID.
3. Identify affected PRD requirements.
4. Determine business workflow impact.
5. Determine order/inventory/payment/tax/accounting impact.
6. Determine security impact.
7. Determine frontend impact.
8. Determine architecture/data impact.
9. Decide whether it is V1, a new phase, or out of scope.
10. Update the PRD version.
11. Update dependent technical/security/frontend documents.
12. Add/update feature tickets.
13. Only then implement.
```

## 42.1 Examples

A request such as “add bank transfer” should affect:

- payment requirements;
- payment UI;
- security/permissions;
- reconciliation;
- accounting;
- feature tickets.

A request such as “allow warehouse staff to directly cancel an order item” should affect:

- adjustment workflow;
- role permissions;
- audit requirements;
- order state transitions;
- inventory rules;
- frontend UI.

The change must therefore not be treated as a tiny isolated button addition.

---

# 43. Decision Log

## DEC-001 — Working product name is replaceable

**Decision:** Use “Wholesale Distribution Management System” as temporary working name.  
**Reason:** Final client/product branding may change.

## DEC-002 — Shared transactional core

**Decision:** All portals operate on one shared order/transaction domain.  
**Reason:** Prevent duplicated order truth and inconsistent financial/inventory data.

## DEC-003 — Admin as operational middle layer

**Decision:** Admin coordinates commercial and operational exceptions between Salesman and Warehouse.  
**Reason:** Matches client workflow.

## DEC-004 — Item-level quantity allocation

**Decision:** Fulfillment and cancellation are quantity-driven at order-item level.  
**Reason:** Supports partial cancellation, delivery, and return correctly.

## DEC-005 — Order Adjustment framework

**Decision:** Post-order changes are represented through explicit adjustment records.  
**Reason:** Preserve historical intent and enable auditability.

## DEC-006 — Product-specific tax

**Decision:** Tax is calculated/stored at product/order-line level.  
**Reason:** Different products may have different tax applicability.

## DEC-007 — Payment methods

**Decision:** V1 supports Cash, Cheque and Money Order.  
**Reason:** Client-confirmed requirement.

## DEC-008 — JPEG payment evidence

**Decision:** Cheque and Money Order payments require JPEG evidence.  
**Reason:** Client-confirmed operational verification requirement.

## DEC-009 — Admin order sub-pages

**Decision:** Admin Orders uses operational queues rather than a single giant order table.  
**Reason:** Client needs continuous order processing without clutter.

## DEC-010 — Invoice image exclusion

**Decision:** Product images are never shown on invoices.  
**Reason:** Client-confirmed document requirement and clean financial-document design.

## DEC-011 — Separate payment entity

**Decision:** Payments are independent transactions linked to orders/customers.  
**Reason:** Supports multiple payments and future methods.

---

# 44. Assumptions & Open Questions

The following should remain visible rather than being silently guessed.

| ID | Question / Assumption | Status |
|---|---|---|
| TBD-001 | Exact tax jurisdiction/rule model | TBD |
| TBD-002 | Tax rounding policy | TBD |
| TBD-003 | Exact cheque verification and return/bounce policy | TBD |
| TBD-004 | Exact Money Order verification policy | TBD |
| TBD-005 | Whether pending cheque/money order counts toward credit exposure | TBD |
| TBD-006 | Cash refund vs account credit policy | TBD |
| TBD-007 | Exact accounting recognition timing/policy | TBD |
| TBD-008 | Exact warehouse interface breadth in V1 | Proposed/Confirm |
| TBD-009 | Exact invoice numbering format/prefix | TBD |
| TBD-010 | Exact adjustment approval matrix by role | Security document |
| TBD-011 | Exact payment reconciliation cadence/shift workflow | Proposed/Confirm |
| TBD-012 | Final client branding/product name | Future |

Until these are confirmed, implementations should use configurable or conservative behavior rather than inventing a permanent business rule.

---

# 45. Product-to-Document Dependency

The five documents are intentionally sequential.

```text
01 PRD
WHAT + WHY
   ↓
02 Technical Architecture
HOW THE SYSTEM IS BUILT
   ↓
03 Security & Access
WHO CAN DO WHAT + SECURITY/FAILURE RULES
   ↓
04 Frontend Specification
HOW USERS EXPERIENCE EACH SCREEN
   ↓
05 Feature Ticket List
ATOMIC BUILD TASKS
   ↓
DEVELOPMENT / QA
```

## 45.1 Dependency rules

Document 02 must not contradict PRD business requirements.

Document 03 may refine permissions and security behavior but must not silently change product goals.

Document 04 converts product behavior into screen-level interaction rules.

Document 05 converts the approved specifications into atomic development tasks.

---

# 46. Requirements for Antigravity / AI Coding Agent

This section is intentionally implementation-oriented because the PRD will later be consumed by an AI development agent.

## 46.1 Do not invent business behavior

If a requirement is CONFIRMED, preserve it.

If a requirement is TBD, do not invent a final business policy.

If a requirement is PROPOSED, treat it as a recommendation subject to approval.

## 46.2 Preserve transaction history

Never solve a business state problem by overwriting historical values if an adjustment/event/snapshot can represent the change correctly.

## 46.3 Preserve quantity history

Never change:

```text
ordered_quantity
```

merely because some quantity is cancelled or returned.

## 46.4 Preserve historical financial snapshots

Order-line price and applicable tax must remain historically accurate for the time of transaction.

## 46.5 Keep domains separated

Do not collapse:

- payment into order;
- return into cancellation;
- credit into refund;
- delivery status into order status;
- inventory quantity into one mutable number;
- product master data into historical transaction data.

## 46.6 Use authorization boundaries

Every sensitive action must be validated against current user permissions at the backend/API/domain level.

## 46.7 Do not create redundant order stores

Admin, Salesman, Warehouse, and Delivery interfaces must read/write the shared domain rather than creating independent order copies.

## 46.8 Do not build UI-only protections

An action prohibited by role must remain prohibited even when a request is manually sent to the backend.

## 46.9 Preserve extensibility

Payment methods, adjustments, tax configurations, notifications, and future portal integrations should be designed so reasonable future extensions do not require destructive redesign.

## 46.10 Follow later documents

When Documents 02–05 exist, the implementation agent must treat the latest approved versions as complementary specifications. Technical details may refine implementation, but they must not silently alter confirmed PRD business behavior.

---

# 47. Product Completion Definition

The product should not be considered complete merely because all screens exist.

V1 is functionally complete when:

- the core order lifecycle works end-to-end;
- all three primary portals can perform their authorized workflows;
- inventory reservation/fulfillment remains consistent;
- partial item cancellation works correctly;
- the Order Adjustment + Item Quantity Allocation model works correctly;
- Cash/Cheque/Money Order payments work;
- JPEG evidence is stored/retrievable for required payments;
- product-specific taxes calculate correctly and remain historically stable;
- invoices can be previewed/printed/downloaded from Salesman and Admin portals;
- invoices do not contain product images;
- delivery receives correct current quantities;
- returns/credits/refunds have controlled workflows;
- customer outstanding balances are accurate;
- core accounting records are consistent with approved policy;
- audit history is present for critical operations;
- Admin can process large numbers of orders through clean operational queues;
- critical edge cases do not corrupt transaction history.

---

# 48. Final Product Blueprint

The core product should ultimately behave as follows:

```text
                         WHOLESALE DISTRIBUTION
                           MANAGEMENT SYSTEM
                                   │
             ┌─────────────────────┼─────────────────────┐
             ↓                     ↓                     ↓
        SALESMAN PORTAL        ADMIN PORTAL       DELIVERY PORTAL
             │                     │                     │
             └─────────────────────┼─────────────────────┘
                                   ↓
                         SHARED TRANSACTION CORE
                                   │
          ┌────────────────────────┼────────────────────────┐
          ↓                        ↓                        ↓
       CUSTOMERS                ORDERS                 PRODUCTS
                                   │
                      ┌────────────┼────────────┐
                      ↓            ↓            ↓
                  QUANTITY     ADJUSTMENTS   PAYMENTS
                  ALLOCATION                    │
                      │                         ├── Cash
                      ↓                         ├── Cheque + JPEG
                  INVENTORY                     └── Money Order + JPEG
                      │
                      ↓
                  WAREHOUSE
                      │
                      ↓
                  DELIVERY
                      │
                      ↓
                   INVOICE
                      │
                      ↓
               RECEIVABLES / ACCOUNTING
                      │
             ┌────────┴────────┐
             ↓                 ↓
          RETURNS         CREDITS/REFUNDS
```

The central architectural/product principle remains:

> **One transaction, multiple operational views, explicit adjustments, preserved history, and controlled financial consequences.**

---

# 49. Change History

| Version | Status | Summary |
|---|---|---|
| 0.1 | Draft | Initial product requirements structure |
| 0.2 | Revised | Incorporated Admin operational workflow and order queues |
| 0.3 | Revised | Added item-level cancellation and quantity allocation |
| 0.4 | Revised | Added Order Adjustment framework |
| 0.5 | Revised | Added Cash/Cheque/Money Order + JPEG evidence |
| 0.6 | Revised | Added product-specific tax |
| 0.7 | Revised | Added invoice printing and no-product-image requirement |
| 0.8 | Revised | Added detailed returns/financial interactions |
| 0.9 | Revised | Added traceability, change management and edge-case framework |
| 1.0 | Development Baseline | Consolidated current confirmed requirements into build-ready PRD |

---

# 50. Approval / Sign-Off Checklist

Before using this PRD as the final development contract, the client/product owner should confirm:

- [ ] Product scope is understood.
- [ ] Three portal model is understood.
- [ ] Admin's role as operational middle layer is correct.
- [ ] Customer lifecycle is correct.
- [ ] Salesman lifecycle is correct.
- [ ] Pricing rules are correct.
- [ ] Product-specific tax approach is correct.
- [ ] Order lifecycle is correct.
- [ ] Item-level quantity allocation is correct.
- [ ] Order Adjustment + Item Quantity Allocation is correct.
- [ ] Warehouse stock exception process is correct.
- [ ] Partial cancellation behavior is correct.
- [ ] Cash payment workflow is correct.
- [ ] Cheque payment + JPEG workflow is correct.
- [ ] Money Order + JPEG workflow is correct.
- [ ] Payment verification workflow is correct.
- [ ] Invoice content is correct.
- [ ] Product images are excluded from invoices.
- [ ] Admin Order sub-pages/queues are correct.
- [ ] Delivery workflow is correct.
- [ ] Return workflow is correct.
- [ ] Credit/refund behavior is understood.
- [ ] Customer credit-limit behavior is correct.
- [ ] Accounting scope is understood.
- [ ] Reporting scope is understood.
- [ ] Audit requirements are understood.
- [ ] TBD/open questions are acknowledged.
- [ ] Future change-management process is accepted.

---

## End of Product Requirements Document

**Next document in the specification chain:**  
**02 — Technical Architecture Document**

This PRD defines the product contract. The next document must translate these business requirements into the technical system structure without changing the approved business behavior.
