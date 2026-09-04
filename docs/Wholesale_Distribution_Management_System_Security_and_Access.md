# 03 — Security & Access Document (SAD)

## Wholesale Distribution Management System

**Document Type:** Security & Access Document (SAD)  
**Working Product Name:** Wholesale Distribution Management System  
**Product Name Status:** Temporary / Replaceable  
**Document Version:** 1.0  
**Status:** Development Baseline  
**Depends On:** PRD v1.0 + Technical Architecture v1.0  
**Primary Market:** United States  
**Currency Context:** USD  
**Initial Customer Scale:** Approximately 500–700 customers

---

# 0. Document Control & Security Rules

## 0.1 Purpose

This document defines the security contract for the Wholesale Distribution Management System.

It translates product and architecture requirements into explicit rules for:

- authentication;
- account lifecycle;
- session security;
- multi-factor authentication;
- roles;
- permissions;
- resource-level authorization;
- business-state authorization;
- segregation of duties;
- sensitive financial operations;
- order security;
- inventory security;
- pricing and tax security;
- payment security;
- payment-evidence security;
- invoice/document security;
- file-upload security;
- application security;
- auditability;
- security logging;
- incident response;
- environment security;
- secrets management;
- security testing.

The document is intended to be directly consumable by developers, QA, security reviewers, and Antigravity/AI coding agents.

## 0.2 Security source-of-truth rule

The PRD is the source of truth for business intent.

The Technical Architecture document is the source of truth for technical structure.

This document is the source of truth for security and access behavior.

No implementation may silently weaken a confirmed security requirement to make development easier.

## 0.3 Security status vocabulary

Use:

- **CONFIRMED** — explicitly agreed/required.
- **PROPOSED** — recommended security control pending final policy confirmation.
- **TBD** — requires client/business/security decision.
- **DEFERRED** — intentionally postponed.
- **CHANGED** — replaced by a later approved decision.
- **DEPRECATED** — no longer applicable but retained for traceability.

## 0.4 Core security principle

The system must follow:

```text
Authentication
    ↓
Identity
    ↓
Role
    ↓
Permission
    ↓
Resource Scope
    ↓
Business-State Validation
    ↓
Domain Validation
    ↓
Atomic Operation
    ↓
Audit
```

Permission checks alone are not sufficient.

## 0.5 Default deny

The default security posture is:

> **Deny unless explicitly authorized.**

A new user, role, permission, route, document, or sensitive operation must not automatically inherit broader access merely because a similar capability exists elsewhere.

---

# 1. Security Objectives

## 1.1 Confidentiality

Protect:

- customer information;
- pricing;
- customer credit data;
- payment records;
- cheque images;
- money-order evidence;
- invoices;
- accounting information;
- audit/security information.

## 1.2 Integrity

Prevent unauthorized modification of:

- orders;
- order quantities;
- prices;
- tax;
- inventory;
- payments;
- credits;
- refunds;
- invoices;
- accounting entries;
- roles;
- permissions.

## 1.3 Accountability

Important operations must identify:

- actor;
- timestamp;
- action;
- affected entity;
- relevant before/after values;
- reason where applicable.

## 1.4 Availability

Security controls should not unnecessarily prevent valid business activity.

Rate limits and session controls must be practical for normal business usage.

## 1.5 Resilience

Security failures and transactional failures must fail safely.

A failed authorization or validation check must not leave partial state.

---

# 2. Security Threat Model

## 2.1 Authentication threats

Protect against:

- brute-force login;
- credential stuffing;
- stolen passwords;
- password reset abuse;
- session theft;
- account takeover;
- privilege retained after account suspension.

## 2.2 Authorization threats

Protect against:

- privilege escalation;
- broken access control;
- IDOR/resource enumeration;
- unauthorized cross-customer access;
- unauthorized cross-salesman access;
- unauthorized payment access;
- role/permission manipulation;
- UI-only security.

## 2.3 Transaction threats

Protect against:

- duplicate order submission;
- duplicate payment;
- request replay;
- duplicate adjustment;
- inventory race conditions;
- negative availability;
- unauthorized pricing;
- unauthorized tax changes;
- financial tampering;
- unauthorized refunds/reversals.

## 2.4 File threats

Protect against:

- fake JPEG uploads;
- malicious files disguised as images;
- oversized uploads;
- path traversal;
- public payment-evidence exposure;
- unauthorized invoice downloads;
- unauthorized document access.

## 2.5 Application threats

Protect against:

- XSS;
- CSRF;
- SQL injection;
- mass assignment;
- sensitive error disclosure;
- unsafe direct database mutation;
- insecure configuration.

---

# 3. Security Architecture

## 3.1 Authorization decision model

Every sensitive request must satisfy:

```text
Authenticated
AND
Has required permission
AND
Can access requested resource
AND
Requested action is valid in current business state
AND
Domain constraints pass
```

Only then may the operation proceed.

## 3.2 Example

A user may have:

```text
order.adjust.approve
```

but the operation can still be denied because:

- the order is already finalized;
- the requested quantity is no longer eligible;
- another adjustment consumed the remaining quantity;
- the actor does not have resource scope;
- a required second approval is missing.

## 3.3 Server-side authority

Frontend controls are usability controls only.

The backend must independently enforce:

- permissions;
- resource scope;
- prices;
- taxes;
- quantities;
- inventory;
- payment amounts;
- workflow status;
- refund values;
- accounting values.

---

# 4. Security Risk Classification

## 4.1 Critical

- authentication;
- authorization;
- payment transactions;
- payment evidence;
- inventory;
- financial records;
- accounting;
- order adjustments;
- role/permission management.

## 4.2 High

- orders;
- customer financial data;
- pricing;
- tax;
- invoices;
- returns;
- credits/refunds;
- delivery access.

## 4.3 Standard

- product catalogue;
- categories;
- non-sensitive application configuration.

Controls should be proportionate to the risk level.

---

# 5. Roles

Baseline roles:

```text
SUPER_ADMIN
ADMIN
ACCOUNTANT
SALESMAN
WAREHOUSE_MANAGER
DELIVERY_PARTNER
```

A role is a permission bundle, not an implicit security bypass.

Role names must not be used as the sole authorization mechanism throughout the application.

---

# 6. Account Lifecycle

Recommended states:

```text
INVITED
ACTIVE
SUSPENDED
DISABLED
```

## 6.1 INVITED

- Account has been provisioned.
- User has not completed activation.
- No normal operational access until activation.

## 6.2 ACTIVE

Normal access is permitted according to role and permissions.

## 6.3 SUSPENDED

Operational access is blocked.

Existing sessions must be invalidated or rejected on subsequent authorization checks.

## 6.4 DISABLED

Login is prohibited.

Historical records remain intact.

---

# 7. Role and Account Separation

Do not equate:

```text
Role = account status
```

A user can be:

```text
ADMIN + SUSPENDED
```

or:

```text
SALESMAN + ACTIVE
```

Role changes and account status changes are separate audited operations.

---

# 8. Authentication

All portals use centralized authentication.

Required capabilities:

- secure login;
- logout;
- password reset;
- secure password hashing;
- login throttling;
- failed-login tracking;
- account lock/protection behavior as appropriate;
- session lifecycle management;
- account suspension enforcement.

Authentication identifies the user.

Authorization determines what that user may do.

---

# 9. Password Security

Passwords must:

- never be stored in plaintext;
- use a framework-supported adaptive password hash;
- never be logged;
- never be included in audit payloads;
- be reset only through secure one-time flows.

Password reset should use:

- high-entropy tokens;
- short validity;
- one-time use;
- invalidation after use;
- session/security-state re-evaluation after successful reset.

---

# 10. Password Policy

Recommended baseline:

- minimum practical password length;
- block obviously compromised/common credentials where feasible;
- do not impose arbitrary complexity rules that encourage insecure password handling;
- rate-limit authentication and reset attempts.

Exact organization-specific password policy is **PROPOSED/TBD** until client policy is confirmed.

---

# 11. Multi-Factor Authentication

## 11.1 Recommended baseline

### Super Admin
MFA: **Required**

### Admin
MFA: **Required in production**

### Accountant
MFA: **Required in production**

### Salesman
MFA: configurable based on client policy.

### Warehouse Manager
MFA: configurable based on final operational policy.

### Delivery Partner
Use a secure authentication mechanism appropriate for the operational device; exact method remains implementation/policy decision.

## 11.2 MFA recovery

MFA recovery mechanisms must not become an easier path to account takeover.

Recovery should require stronger identity verification and be audited.

---

# 12. Step-Up Authentication

Re-authentication or fresh MFA should be considered/required for high-risk operations.

Examples:

```text
payment.reverse
refund.approve
accounting.reverse
role.manage
permission.manage
critical system settings
high-value financial adjustments
```

Any monetary threshold remains configurable/TBD.

---

# 13. Session Security

Sessions must support:

- secure cookies;
- HttpOnly;
- appropriate SameSite policy;
- HTTPS in staging/production;
- session regeneration after authentication;
- session expiration;
- idle timeout;
- explicit logout invalidation;
- session invalidation after password/security changes;
- session invalidation or re-evaluation after role/status changes.

## 13.1 Privilege revocation

If:

```text
ADMIN → SALESMAN
```

the old privileged session must not remain permanently privileged.

If:

```text
ACTIVE → SUSPENDED
```

the user must lose operational access promptly.

---

# 14. Login Security

Protect against:

- brute force;
- credential stuffing;
- user enumeration.

Avoid different user-visible responses such as:

```text
User does not exist.
```

versus:

```text
Wrong password.
```

where this would disclose account existence.

---

# 15. Permission Architecture

Use permission-based authorization.

Naming convention:

```text
module.action
```

Examples:

```text
customer.view
customer.create
customer.update

product.view
product.create
product.update
product.price.update
product.tax.update

order.view
order.create
order.submit
order.approve
order.reject
order.cancel

order.adjust.request
order.adjust.review
order.adjust.approve
order.adjust.apply
order.adjust.reverse

payment.view
payment.create
payment.verify
payment.reverse

inventory.view
inventory.adjust
inventory.exception.report

delivery.view
delivery.assign
delivery.update

return.request
return.review
return.approve

credit.create
refund.request
refund.approve

invoice.view
invoice.print
invoice.download

accounting.view
accounting.post
accounting.reverse

user.view
user.create
user.suspend
role.manage
permission.manage
```

---

# 16. Permission Principles

1. Default deny.
2. Grant only required permissions.
3. Permissions are enforced server-side.
4. Sensitive actions have separate permissions.
5. Resource scope is checked after permission.
6. Business state is checked before execution.
7. Permissions are audited when changed.

---

# 17. Role → Permission Matrix

The final implementation must maintain a complete matrix.

Illustrative baseline:

| Capability | Super Admin | Admin | Accountant | Salesman | Warehouse | Delivery |
|---|---:|---:|---:|---:|---:|---:|
| View permitted orders | ✅ | ✅ | Controlled | ✅ | Controlled | Assigned only |
| Create order | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Approve order | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Request adjustment | ✅ | ✅ | ❌ | ✅ | Report issue | ❌ |
| Approve adjustment | ✅ | ✅ | Controlled | ❌ | ❌ | ❌ |
| Create payment | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Verify payment | ✅ | Controlled | ✅ | ❌ | ❌ | ❌ |
| Reverse payment | ✅ | Controlled | Controlled | ❌ | ❌ | ❌ |
| Inventory adjustment | ✅ | Controlled | ❌ | ❌ | Controlled | ❌ |
| Assign delivery | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Update delivery status | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Print invoice | ✅ | ✅ | Controlled | ✅ | ❌ | ❌ |
| Approve refund | ✅ | Controlled | Controlled | ❌ | ❌ | ❌ |
| Manage roles | ✅ | Controlled | ❌ | ❌ | ❌ | ❌ |

"Controlled" must be replaced in implementation with explicit permission names.

---

# 18. Resource-Level Authorization

RBAC is not enough.

Every protected resource must also be checked against the user's allowed scope.

## 18.1 Salesman

Normally limited to:

- assigned customers;
- permitted customer data;
- authorized orders;
- own/requested operational records.

## 18.2 Delivery Partner

Limited to:

- deliveries assigned to that user;
- related order/customer information needed for delivery.

## 18.3 Warehouse Manager

Limited to:

- permitted fulfillment/inventory scope;
- relevant stock and order information.

## 18.4 Admin

Broad operational scope according to permissions.

## 18.5 Super Admin

System-wide scope where explicitly authorized.

---

# 19. IDOR Protection

All object access must validate authorization.

Example attack:

```text
GET /orders/10045
GET /orders/10046
```

Changing an identifier must not grant access.

The same applies to:

```text
customers
payments
payment attachments
invoices
returns
adjustments
inventory records
accounting records
```

Never rely on obscured IDs alone as a security mechanism.

---

# 20. Business-State Authorization

Authorization must account for current state.

Example:

```text
User has:
order.adjust.apply
```

but:

```text
Order = COMPLETED
Adjustment = not allowed in current state
```

The request must be rejected.

This prevents permissions from becoming accidental bypasses of business-state rules.

---

# 21. Segregation of Duties

Where practical, sensitive workflows should separate initiation and approval.

Examples:

```text
Salesman
→ Request adjustment

Admin
→ Approve adjustment
```

```text
Payment recorded
→ Authorized verifier confirms
```

```text
Refund requested
→ Authorized approver approves
```

```text
Role/permission change
→ Privileged administrator executes
```

A user must not gain additional rights merely because they initiated a transaction.

---

# 22. Order Access Security

## 22.1 Salesman

Can:

- create permitted orders;
- view permitted orders;
- submit permitted orders;
- request permitted item adjustments;
- record allowed payments;
- view permitted invoice.

Cannot:

- approve own order;
- bypass pricing restrictions;
- bypass credit controls;
- directly alter historical values;
- approve own sensitive financial adjustment.

## 22.2 Admin

Can perform broader order operations subject to:

- permission;
- resource scope;
- state;
- quantity constraints;
- financial controls.

## 22.3 Delivery Partner

Can only access the order information needed for assigned delivery execution.

---

# 23. Order Adjustment Security

Every adjustment must perform:

```text
Authenticate
 ↓
Check permission
 ↓
Check order resource scope
 ↓
Re-read current state
 ↓
Validate quantity eligibility
 ↓
Validate business state
 ↓
Calculate tax impact
 ↓
Calculate financial impact
 ↓
Validate inventory effect
 ↓
Apply authorized transaction
 ↓
Audit
```

No client-side value may bypass these checks.

---

# 24. Quantity Security

Server must enforce:

```text
cancelled_quantity <= ordered_quantity
```

and:

```text
new_cancellation <= remaining_eligible_quantity
```

and appropriate constraints for:

```text
reserved
picked
dispatched
delivered
returned
accepted return
rejected return
```

The frontend must never be the final authority.

---

# 25. Inventory Security

Inventory may only be modified through authorized inventory operations.

Examples:

```text
ReserveInventory
ReleaseReservation
AdjustInventory
RecordDamage
ReceiveStock
```

Direct mutation of:

```text
available_stock
reserved_stock
```

through arbitrary requests is prohibited.

---

# 26. Inventory Concurrency Security

Inventory reservation must be protected against concurrent requests.

Example:

```text
Available = 1

User A requests 1
User B requests 1
```

Only one operation may succeed.

The implementation must use database transaction/locking or an equally strong atomic strategy.

This is both a security and data-integrity requirement.

---

# 27. Pricing Security

Backend must independently validate:

```text
actual price >= minimum allowed price
actual price <= applicable maximum
```

unless an authorized override applies.

Never trust:

```text
frontend price
frontend discount
frontend totals
```

as authoritative.

Price changes and overrides must be audited.

---

# 28. Tax Security

Tax configuration must be restricted to authorized users.

A tax change must capture:

```text
old configuration
new configuration
actor
timestamp
reason where required
```

Historical transaction tax snapshots must not be altered.

---

# 29. Payment Security

Payment operations are high-risk.

## 29.1 Payment creation

Validate:

- authenticated actor;
- permission;
- order/customer scope;
- valid amount;
- supported method;
- mandatory evidence;
- business state.

## 29.2 Verification

Only authorized verifier roles may confirm.

## 29.3 Reversal

Separate permission and higher scrutiny.

## 29.4 Returned cheque

Only authorized users may transition a cheque to returned/bounced status.

---

# 30. Cheque Security

Cheque records may contain:

```text
amount
cheque number
date
bank
JPEG image
verification state
```

Protect all fields as sensitive financial information.

Do not let ordinary users modify:

```text
verification state
verified_by
returned/bounced state
```

through arbitrary payload fields.

---

# 31. Money Order Security

Money Order records may contain:

```text
amount
reference/details
JPEG evidence
verification state
```

The JPEG evidence is mandatory under the confirmed V1 rule unless a later approved requirement changes it.

---

# 32. Payment Attachment Security

Payment evidence must be stored privately.

Security requirements:

```text
Private object storage
Authorized access only
Validated upload
Non-guessable storage identifier
Short-lived signed access or controlled streaming
No public directory listing
```

Accessing a payment attachment requires authorization for the linked payment/order/customer.

---

# 33. File Upload Security

Validate:

- extension;
- declared MIME;
- detected MIME/file signature;
- image validity;
- size;
- dimensions where useful;
- filename safety.

Generate server-side storage names.

Do not use user-provided filenames as storage keys.

---

# 34. Malicious JPEG Protection

A file named:

```text
cheque.jpg
```

must not be trusted merely because the extension says `.jpg`.

The application should verify that it is an actual supported image type.

Uploaded files must not become executable application content.

---

# 35. Invoice Security

Invoice access must verify:

```text authenticated
+
invoice permission
+
resource access
+
document state
```

Invoice PDFs must use private storage.

Product images must never be embedded in invoices.

Payment evidence images must never be automatically embedded in invoices.

---

# 36. Financial Data Security

High-risk operations include:

```text
Payment verification
Payment reversal
Credit creation
Refund approval
Refund processing
Accounting posting
Accounting reversal
Manual financial adjustment
```

These require:

```text permission
+
state validation
+
domain validation
+
audit
+
idempotency where appropriate
+
step-up authentication where required
```

---

# 37. Financial Input Trust Boundary

The client is untrusted.

The frontend may send:

```text requested quantity
selected price
selected payment amount
```

but the backend must independently calculate/validate:

```text subtotal
tax
grand total
outstanding
credit exposure
inventory availability
financial impact
```

The frontend must never be considered an authoritative calculator.

---

# 38. Customer Credit Limit Security

Changing a credit limit is a sensitive operation.

Every change must record:

```text customer
old limit
new limit
actor
timestamp
reason where applicable
```

Only authorized roles may perform it.

---

# 39. Customer Financial Data Access

Restrict:

```text outstanding
credit limit
payment history
aging
statements
refunds
credits
financial adjustments
```

Salesmen should receive only the financial information required for their workflow.

---

# 40. Accounting Security

Separate:

```text accounting.view
accounting.post
accounting.reverse
```

A user with view access must not automatically have posting/reversal rights.

Posted financial history must not be deleted.

Corrections use controlled reversal/adjustment mechanisms.

---

# 41. Mass Assignment Protection

Request payloads must never be allowed to arbitrarily modify security-sensitive fields.

Examples:

```text role
permissions
approved_by
verified_by
payment_status
tax_amount
grand_total
inventory_available
credit_limit
audit metadata
```

Sensitive fields are controlled by application/domain operations.

---

# 42. CSRF Protection

Browser-based state-changing operations require appropriate CSRF protection.

Do not disable CSRF globally because a specific integration or form is inconvenient.

---

# 43. Cross-Site Scripting (XSS)

User-provided content must be safely escaped/sanitized.

Potentially dangerous fields include:

```text customer name
product description
order notes
adjustment notes
delivery failure notes
payment references
```

Raw HTML should not be accepted unless specifically required and safely sanitized.

---

# 44. SQL Injection

Use parameterized queries and framework-supported query mechanisms.

Raw SQL may be used where necessary, but all user input must be parameterized.

Never concatenate untrusted strings into SQL.

---

# 45. Security Headers

Production should use appropriate HTTP security headers, including where compatible:

```text
Content-Security-Policy
Strict-Transport-Security
X-Content-Type-Options
Referrer-Policy
Frame-ancestors / frame protections
```

Exact CSP must be tested with the actual application.

---

# 46. Error Disclosure

Production user responses must not expose:

- stack traces;
- SQL errors;
- server filesystem paths;
- source code;
- secrets;
- internal service details.

Detailed diagnostics belong in protected server monitoring/logging.

---

# 47. API/Endpoint Security

Even if an endpoint is not linked from the UI, it must enforce:

```text
Authentication
Authorization
Resource scope
Validation
Rate limiting where appropriate
```

"Not visible in frontend" is not a security control.

---

# 48. Rate Limiting

At minimum protect:

```text login
password reset
sensitive financial endpoints
file uploads
payment operations
high-risk administrative endpoints
```

Limits should be environment/configuration driven where practical.

---

# 49. Idempotency Security

Critical business commands must protect against duplicate/replayed requests.

Apply to:

```text order submission
payment creation
adjustment application
invoice issuance
delivery completion
```

Repeated valid retries should return/use the original operation result rather than create another transaction.

---

# 50. Idempotency Key Rules

An idempotency mechanism should associate:

```text actor
operation type
idempotency key
request fingerprint
result/reference
created time
```

A key must not allow one actor to retrieve or replay another actor's operation.

---

# 51. Audit Architecture

The application must maintain durable business audit records.

Audit at minimum:

### Authentication/security

```text login
failed login
logout
password/security changes
MFA changes
```

### Identity/access

```text user creation
user suspension
role change
permission change
```

### Commercial

```text customer change
product change
price change
tax change
order creation
order edit
order approval
order rejection
order cancellation
item adjustment
```

### Financial

```text payment creation
payment verification
payment reversal
cheque returned
credit
refund
accounting post
accounting reversal
```

### Operations

```text inventory adjustment
stock exception
delivery status
return status
invoice issuance/void
```

---

# 52. Audit Event Structure

Conceptual structure:

```text
event_id
actor_id
actor_role_snapshot
action
entity_type
entity_id
timestamp
request_id
before_state
after_state
reason
ip_address
user_agent
```

Only relevant and safe fields should be persisted.

Secrets and credentials must never be copied into audit records.

---

# 53. Audit Immutability

Normal application users must not:

```text
edit audit records
delete audit records
rewrite audit history
```

Do not provide a normal "edit audit event" UI.

---

# 54. Business Audit vs Security Logging

They serve different purposes.

## Security log

Example:

```text
FAILED_LOGIN
```

## Business audit

Example:

```text
ADMIN_CANCELLED_ORDER_ITEM
```

Both should be retained through appropriate infrastructure.

---

# 55. Request Correlation

Security-sensitive requests should have a request/correlation identifier.

Example:

```text
req_01ABC...
```

Use it to correlate:

```text
application logs
security events
audit records where useful
error monitoring
background jobs where practical
```

---

# 56. Logging Rules

Never log:

```text
password
password reset token
session secret
AWS secret
private keys
full payment evidence contents
unnecessary sensitive customer data
```

Use sanitized metadata.

---

# 57. Sensitive Data Classification

## Highly Sensitive

```text
passwords
authentication secrets
payment evidence
financial credentials
security metadata
```

## Sensitive Business

```text
customer data
credit limits
prices
inventory
payments
invoices
accounting records
```

## Operational

```text
catalog data
categories
ordinary workflow statuses
```

Apply access controls accordingly.

---

# 58. Data Encryption

Production should use:

### In transit

HTTPS/TLS.

### At rest

Encrypted:

- database storage;
- backups;
- object/document storage;
- relevant AWS infrastructure services.

Secrets must be separately protected from ordinary data.

---

# 59. Database Security

Production database should:

- not be publicly reachable;
- use private network access;
- accept traffic only from authorized application infrastructure;
- use least-privilege database credentials;
- use encryption in transit where supported;
- use encrypted backups.

---

# 60. Redis Security

Production Redis should:

- not be publicly reachable;
- be protected by network/security controls;
- require authentication where supported/appropriate;
- never contain authoritative financial/inventory truth alone.

---

# 61. S3 Security

Production business-document storage should:

- remain private;
- block public access;
- use encrypted storage;
- use application-controlled access;
- use lifecycle policies where appropriate;
- avoid predictable/public URLs.

Objects include:

```text payment evidence
invoice PDFs
product images
future return evidence
```

---

# 62. Secure Document Access Flow

```text
User requests document
       ↓
Authenticate
       ↓
Check permission
       ↓
Check resource scope
       ↓
Check document state
       ↓
Generate short-lived signed URL
or controlled response
       ↓
Deliver document
```

---

# 63. Environment Security

Environments:

```text
LOCAL
STAGING
PRODUCTION
```

## Local

May use development conveniences.

## Staging

Should closely resemble production security.

## Production

Strict security.

Never use production secrets or production data casually on developer machines.

---

# 64. Secret Management

Never commit:

```text
passwords
API keys
AWS credentials
APP_KEY
database passwords
private signing keys
```

to Git.

Local development may use `.env`.

Production should use secure secret/configuration management such as AWS Secrets Manager or SSM Parameter Store as appropriate.

---

# 65. `.env` Security

Commit:

```text
.env.example
```

Never commit:

```text
.env
```

The `.env.example` must contain safe placeholders only.

A definitive project-wide `.env` inventory will be created after all five specification documents are completed.

---

# 66. Role Change Security

Role changes require:

- authorized actor;
- permission;
- audit;
- appropriate validation;
- session re-evaluation/invalidation for affected user.

Example:

```text
ADMIN → SALESMAN
```

must remove Admin capabilities from active sessions promptly.

---

# 67. Permission Change Security

Permission changes are high-impact operations.

Examples:

```text
grant refund.approve
grant accounting.reverse
grant role.manage
```

Require:

- privileged permission;
- audit;
- current-state verification;
- step-up authentication where appropriate.

---

# 68. Super Admin Security

Super Admin has broad access but must not have a "modify any database field" shortcut.

Even Super Admin operations should respect:

- validation;
- transaction safety;
- audit;
- state constraints;
- least-privilege sub-permissions where possible.

---

# 69. No Security by Obscurity

Do not treat these as authorization:

- hidden buttons;
- obscure routes;
- UUIDs alone;
- unlisted API endpoints;
- frontend-only checks.

These can complement security but never replace authorization.

---

# 70. Sensitive Action Confirmation

The frontend should provide confirmation for actions such as:

```text cancel order
cancel item quantity
approve adjustment
reverse payment
approve refund
reverse accounting entry
change role
change permissions
```

Where possible, show:

```text quantity impact
inventory impact
tax impact
financial impact
```

But confirmation dialogs are not security controls.

---

# 71. Adjustment Approval Security

For:

```text
Cancel 2 units of Product B
```

the system should perform:

```text
Authenticate
→ Permission
→ Resource scope
→ Current order state
→ Quantity validation
→ Inventory validation
→ Tax calculation
→ Financial calculation
→ Approval policy
→ Atomic application
→ Audit
```

This protects the client's item-level adjustment system.

---

# 72. Payment Verification Security

For cheque/money order verification:

```text
Authenticate
→ payment.verify permission
→ payment resource scope
→ current payment status
→ evidence availability
→ verification action
→ financial state update
→ audit
```

No direct client-supplied:

```text status=CONFIRMED
```

must be accepted without authorization and validation.

---

# 73. Payment Reversal Security

Payment reversal is a higher-risk operation.

Require:

- dedicated permission;
- current payment-state check;
- valid reversal reason;
- financial consistency;
- audit;
- idempotency;
- step-up authentication where required by policy.

Original payment history remains preserved.

---

# 74. Refund/Credit Security

Separate:

```text refund.request
refund.approve
refund.process
```

where business workflow requires it.

Do not allow a salesperson to self-approve a refund unless explicitly approved as a business policy.

Refund amount must be calculated/validated against the customer's financial state.

---

# 75. Accounting Reversal Security

Posted accounting entries must not be deleted.

Use:

```text original journal
      ↓
controlled reversal
      ↓
correcting entry
```

Only authorized users may perform reversal.

---

# 76. Delivery Security

Delivery Partner is intentionally restricted.

Cannot:

```text change price
change tax
change customer credit limit
approve refund
modify accounting
change original order quantity
approve sensitive adjustments
```

Can only update assigned delivery operations.

---

# 77. Warehouse Security

Warehouse may:

- view permitted fulfillment;
- confirm physical fulfillment;
- report stock exceptions;
- perform authorized inventory operations.

Warehouse should not silently modify the commercial transaction.

The intended workflow is:

```text
Warehouse detects issue
       ↓
Stock Exception
       ↓
Admin
       ↓
Order Adjustment
```

---

# 78. Customer Security

Customer data must be accessed only by users with appropriate scope.

Salesman should not gain access to customers simply by guessing IDs.

Admin may have broader access.

Future customer-facing portals must use the same underlying authorization model.

---

# 79. Tax and Historical Integrity

Security controls must prevent historical tax snapshots from being altered by ordinary product-tax changes.

Example:

```text
Old Order
Tax = 5%

Product later changes
Tax = 8%
```

Old order must remain 5%.

Any correction must use an authorized business adjustment/reversal process.

---

# 80. Historical Price Integrity

Similarly:

```text
Old Order Price = $22
Current Product Price = $25
```

must continue to show $22.

Changing the product master must not rewrite transaction history.

---

# 81. Input Trust Rules

The following are always untrusted when received from the client:

```text quantity
price
tax
subtotal
tax total
grand total
outstanding
inventory availability
status
approval state
role
permission
verified_by
approved_by
```

Server recalculates or independently validates them.

---

# 82. Secure State Transition Rule

No user may directly set:

```text
order.status
payment.status
delivery.status
adjustment.status
return.status
```

to an arbitrary value.

State transitions must use authorized application/domain operations.

---

# 83. Business Command Security

Prefer named operations:

```text
ApproveOrder
RejectOrder
RequestAdjustment
ApproveAdjustment
ApplyAdjustment
VerifyPayment
ReversePayment
AssignDelivery
CompleteDelivery
ApproveReturn
ApproveRefund
PostJournal
ReverseJournal
```

over generic:

```text
updateRecord()
```

for security-sensitive behavior.

---

# 84. Database Mutation Rule

Security-sensitive tables must not be casually updated through generic CRUD endpoints.

Particularly:

```text
payments
inventory
orders
adjustments
accounting
audit
permissions
```

must go through controlled domain operations.

---

# 85. Security Around Background Jobs

Jobs must not bypass authorization simply because they run asynchronously.

A job should:

- contain only the data/context necessary;
- validate that the initiating operation was authorized;
- avoid performing a privileged action based on stale assumptions;
- be safe to retry where applicable.

System-owned background work must still be auditable where it changes critical state.

---

# 86. Queue Poisoning / Replay Protection

Jobs affecting:

```text payment
inventory
invoice
financial state
```

should be idempotent where practical.

Retries must not create duplicate:

- payments;
- invoices;
- inventory movements;
- financial postings.

---

# 87. Security of Notifications

Notifications must not leak excessive confidential information.

For example, an email/SMS should not contain sensitive payment evidence or unnecessary accounting details unless explicitly required.

Notification access should be controlled separately from the underlying business record.

---

# 88. Browser Security

The frontend should:

- avoid storing secrets in browser local storage;
- avoid putting sensitive credentials in URLs;
- avoid logging sensitive payloads in browser console in production;
- clear sensitive temporary state appropriately;
- use secure session mechanisms.

---

# 89. Client-Side Storage Rule

Do not store authoritative:

```text inventory
payment truth
customer outstanding
accounting balance
permissions
```

in client storage as a source of truth.

The server remains authoritative.

---

# 90. Security of Search

Search endpoints must still enforce resource scope.

Example:

A Salesman searching:

```text ABC
```

must not receive customers/orders from other unauthorized scopes.

Search is not an exception to authorization.

---

# 91. Bulk Operations Security

Bulk actions must apply the same authorization to every selected resource.

Do not authorize based only on the first selected record.

Example:

```text selected orders:
1001
1002
1003
```

Backend must validate access for all three.

---

# 92. Export Security

Reports/CSV/PDF exports may contain sensitive information.

Exports must enforce:

- permission;
- resource scope;
- filters;
- reasonable limits;
- audit where appropriate.

Do not create an export endpoint with weaker access than the normal UI.

---

# 93. Administrative Search / Debug Tools

No hidden debug route should provide unrestricted database access in production.

Development-only tools must be disabled or protected in production.

---

# 94. Backup and Recovery Security

Backups must be:

- encrypted;
- access-controlled;
- retained according to policy;
- separated from normal application permissions;
- periodically restoration-tested.

---

# 95. Security Incident Response

Baseline process:

```text
Detect
 ↓
Contain
 ↓
Revoke credentials/sessions
 ↓
Preserve evidence
 ↓
Assess impact
 ↓
Correct
 ↓
Recover
 ↓
Review
```

Potential incidents:

```text
account takeover
payment evidence exposure
privilege escalation
inventory tampering
financial manipulation
credential leak
database compromise
```

---

# 96. Compromised Account Example

If an Admin account is suspected compromised:

```text
Suspend account
↓
Invalidate active sessions
↓
Review authentication/security logs
↓
Review business audit events
↓
Identify suspicious transactions
↓
Rotate affected credentials if required
↓
Correct/reverse compromised transactions
↓
Restore normal access only after review
```

---

# 97. Security Change Management

A future request such as:

> Allow Warehouse Manager to cancel order quantities directly

must trigger:

```text
PRD impact
Architecture impact
Permission changes
Resource-scope review
State-transition review
Inventory review
Financial review
Audit review
Frontend review
Testing review
```

Never implement such a request by only exposing a new button.

---

# 98. Security Testing Strategy

Security tests must include:

### Authentication

- brute-force controls;
- reset-token reuse;
- session invalidation;
- suspended user access.

### Authorization

- role bypass;
- permission bypass;
- IDOR;
- out-of-scope resources;
- stale-role access.

### Business security

- price manipulation;
- tax manipulation;
- quantity manipulation;
- payment manipulation;
- inventory manipulation;
- adjustment replay.

### File security

- invalid JPEG;
- oversized file;
- malicious file;
- unauthorized document access.

### Application security

- XSS;
- CSRF;
- SQL injection;
- mass assignment;
- sensitive error disclosure.

---

# 99. Authorization Test Pattern

For every sensitive capability:

```text
Authorized role
    → succeeds

Unauthorized role
    → denied

Authorized role + wrong resource scope
    → denied

Authorized role + invalid business state
    → denied
```

This four-part test pattern should be repeated across critical domains.

---

# 100. Security Acceptance Matrix

Examples:

| Scenario | Expected result |
|---|---|
| Salesman tries to approve own order | Denied |
| Salesman accesses another salesperson's customer | Denied |
| Delivery accesses unrelated order | Denied |
| Unauthorized user downloads cheque image | Denied |
| Salesman submits below-minimum price | Denied |
| Unauthorized user changes tax | Denied |
| Unauthorized user changes credit limit | Denied |
| Admin cancels valid quantity | Allowed |
| Admin cancels quantity above eligibility | Denied |
| Unauthorized payment reversal | Denied |
| Duplicate payment replay | No duplicate transaction |
| Suspended user uses old session | Denied/revoked |
| User modifies role via request payload | Denied |
| Client submits forged grand total | Server recalculates |
| User modifies posted accounting entry directly | Denied |

---

# 101. Security Traceability IDs

Use stable IDs across later documents.

## Authentication

```text
SEC-AUTH-001  Centralized authentication
SEC-AUTH-002  Secure password handling
SEC-AUTH-003  Login throttling
SEC-AUTH-004  Secure password reset
SEC-AUTH-005  Session security
SEC-AUTH-006  Session revocation
SEC-AUTH-007  Privileged MFA
SEC-AUTH-008  Step-up authentication
```

## Authorization

```text
SEC-RBAC-001  Default deny
SEC-RBAC-002  Backend authorization
SEC-RBAC-003  Permission-based authorization
SEC-RBAC-004  Resource-level authorization
SEC-RBAC-005  State-aware authorization
SEC-RBAC-006  Least privilege
SEC-RBAC-007  Role-change revocation
SEC-RBAC-008  Bulk-operation authorization
```

## Orders

```text
SEC-ORD-001  Salesman cannot approve own order
SEC-ORD-002  Adjustment authorization
SEC-ORD-003  Quantity limits server-side
SEC-ORD-004  State-transition enforcement
SEC-ORD-005  Order resource scope
```

## Inventory

```text
SEC-INV-001  Authorized inventory mutations
SEC-INV-002  Concurrent reservation protection
SEC-INV-003  Negative availability prevention
SEC-INV-004  Inventory movement audit
```

## Payments

```text
SEC-PAY-001  Payment creation authorization
SEC-PAY-002  Payment verification authorization
SEC-PAY-003  Payment reversal authorization
SEC-PAY-004  Payment evidence protection
SEC-PAY-005  Cheque state protection
SEC-PAY-006  Money Order evidence protection
SEC-PAY-007  Payment idempotency
```

## Financial

```text
SEC-FIN-001  Financial input recalculation
SEC-FIN-002  Posted history immutability
SEC-FIN-003  Refund approval controls
SEC-FIN-004  Accounting reversal controls
SEC-FIN-005  Credit-limit protection
```

## Files

```text
SEC-FILE-001  Private document storage
SEC-FILE-002  Upload validation
SEC-FILE-003  MIME/file-signature validation
SEC-FILE-004  Authorized document access
SEC-FILE-005  Non-public payment evidence
```

## Audit

```text
SEC-AUD-001  Critical action auditing
SEC-AUD-002  Audit immutability
SEC-AUD-003  Security/business log separation
SEC-AUD-004  Request correlation
SEC-AUD-005  Sensitive-data redaction
```

---

# 102. Antigravity / AI Coding Agent Security Rules

These rules are mandatory during implementation.

## Rule 1

Never trust frontend authorization.

## Rule 2

Never trust frontend financial values.

## Rule 3

Never trust frontend inventory values.

## Rule 4

Never trust frontend tax calculations.

## Rule 5

Never trust frontend status/approval fields.

## Rule 6

Never allow direct generic CRUD to bypass domain authorization.

## Rule 7

Never implement security by hiding a button.

## Rule 8

Never expose private payment/document files publicly.

## Rule 9

Never store secrets in source code.

## Rule 10

Never delete posted financial history to correct a mistake.

## Rule 11

Never overwrite historical price/tax/order quantity merely to simplify the current state.

## Rule 12

Never add a role or permission bypass without updating the Security document and related requirements.

## Rule 13

Every sensitive command must re-check current authorization and business state before commit.

## Rule 14

Every critical operation must be tested for unauthorized access as well as successful execution.

---

# 103. Secure Implementation Pattern

For a sensitive command:

```text
HTTP Request
    ↓
Authenticate
    ↓
Validate request shape
    ↓
Authorize permission
    ↓
Authorize resource scope
    ↓
Load current state
    ↓
Validate state transition
    ↓
Validate domain constraints
    ↓
Calculate authoritative result
    ↓
Begin transaction
    ↓
Apply changes
    ↓
Write audit
    ↓
Commit
    ↓
Dispatch secondary effects
```

If any precondition fails:

```text
STOP
```

and do not partially modify the transaction.

---

# 104. Security vs Usability

Security should be strong without making routine work painful.

For example:

Routine:

```text
View customer
Create order
Search order
```

should remain fast.

Sensitive:

```text
Reverse payment
Approve refund
Change role
Reverse accounting
```

should deliberately require more scrutiny.

---

# 105. Future Extensibility

Security must support future additions such as:

```text
Warehouse Portal
Customer Portal
Supplier Portal
Bank Transfer
Online Payment
Advanced Tax
Additional notification channels
```

without bypassing the existing security architecture.

Future modules must plug into:

```text
Authentication
Permissions
Resource scopes
Business policies
Audit
```

rather than inventing independent security systems.

---

# 106. Security Configuration

Potential configurable security values:

```text
session lifetime
idle timeout
MFA requirement
login throttling
password-reset lifetime
upload limits
signed-document URL lifetime
```

Security configuration changes themselves should be privileged and audited.

---

# 107. Security Completion Criteria

Document 03 is implementation-ready when:

- all roles are defined;
- permission catalogue exists;
- role-permission matrix exists;
- resource scopes are defined;
- authentication rules are defined;
- MFA requirements are defined;
- session behavior is defined;
- business-state authorization is defined;
- financial controls are defined;
- document/file security is defined;
- audit requirements are defined;
- security logging is defined;
- security tests are defined;
- incident response baseline exists;
- traceability IDs exist.

---

# 108. Relationship to Remaining Documents

```text
01 PRD
Business requirements
        ↓
02 Technical Architecture
Technical implementation
        ↓
03 Security & Access
Who can access/do what + security constraints
        ↓
04 Frontend Specification
How authorized/unauthorized actions appear in UI
        ↓
05 Feature Ticket List
Atomic implementation tasks
```

Document 04 must not weaken any security restriction established here.

Document 05 must reference the applicable `SEC-*` requirements for sensitive tickets.

---

# 109. Final Security Blueprint

```text
                         USER
                           |
                           v
                   AUTHENTICATION
                           |
                           v
                    USER IDENTITY
                           |
                           v
                         ROLE
                           |
                           v
                     PERMISSION
                           |
                           v
                  RESOURCE SCOPE
                           |
                           v
              BUSINESS-STATE POLICY
                           |
                           v
                 DOMAIN VALIDATION
                           |
                           v
                AUTHORITATIVE DATA
                     CALCULATION
                           |
                           v
                 ATOMIC TRANSACTION
                           |
                 ┌─────────┴─────────┐
                 v                   v
               AUDIT            SECONDARY
                                EFFECTS
```

The central security principle is:

> **A user being authenticated does not make them authorized. A user having permission does not make every resource/action valid. Every sensitive operation must pass authentication, permission, resource-scope, business-state, and domain validation before the system commits authoritative changes.**

---

# 110. Final V1 Security Priorities

The implementation should prioritize, in order:

```text
1. Authentication and session security
2. Authorization and resource scoping
3. Order and adjustment protection
4. Inventory integrity/concurrency
5. Payment and payment-evidence protection
6. Financial/accounting integrity
7. Pricing and tax integrity
8. Invoice/document access
9. Auditability
10. Security testing
```

---

# 111. Known Security/Policy TBDs

The following must not be silently invented:

```text
TBD-SEC-001  Exact password policy
TBD-SEC-002  Salesman MFA requirement
TBD-SEC-003  Warehouse MFA requirement
TBD-SEC-004  Delivery authentication mechanism
TBD-SEC-005  Exact step-up authentication thresholds
TBD-SEC-006  Exact refund approval matrix
TBD-SEC-007  Exact payment-verification authority matrix
TBD-SEC-008  Security log retention
TBD-SEC-009  Business audit retention
TBD-SEC-010  Exact session timeout values
TBD-SEC-011  Exact upload size limits
TBD-SEC-012  Exact cheque/money-order verification workflow
```

Until resolved, use conservative and configurable defaults.

---

# 112. Approval Checklist

Before treating this document as the final security contract:

- [ ] Role list approved
- [ ] Permission model approved
- [ ] Salesman access scope approved
- [ ] Admin access scope approved
- [ ] Accountant access scope approved
- [ ] Warehouse scope approved
- [ ] Delivery scope approved
- [ ] MFA policy approved
- [ ] Sensitive-action policy approved
- [ ] Adjustment authorization approved
- [ ] Payment verification authority approved
- [ ] Payment reversal authority approved
- [ ] Refund authority approved
- [ ] File/document access policy approved
- [ ] Invoice access policy approved
- [ ] Audit requirements approved
- [ ] Security logging approach approved
- [ ] Incident response baseline understood
- [ ] TBD items acknowledged

---

# End of Security & Access Document

**Next document in the specification chain:**

**04 — Frontend Specification**

The next document must translate these security constraints into screen-level UX and interaction rules without weakening any backend authorization or business-state controls.