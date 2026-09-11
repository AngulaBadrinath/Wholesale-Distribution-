# Client Testing & Demo Guide

**Document Version:** 1.0  
**Application:** Unique Distributors — Wholesale Distribution Management System  
**Audience:** QA Engineers, Product Owners, and Client Stakeholders  
**Demo Dataset:** Synthetic Non-Production Seed (`php artisan demo:seed`)

---

## 1. Demo Credentials Summary

| Role | Email Address | Password | Primary Workspace / Responsibility |
|---|---|---|---|
| **Super Admin** | `superadmin.qa@example.test` | `Password123!` | System configuration, user permissions, audit oversight |
| **Admin** | `admin.qa@example.test` | `Password123!` | Order approval, price overrides, inventory & catalogue management |
| **Accountant** | `accountant.qa@example.test` | `Password123!` | Payment verification, AR aging, statements, credit reviews |
| **Salesman (North)** | `salesman.a@example.test` | `Password123!` | Customer order builder, price negotiation, Cheque/Cash payment recording |
| **Salesman (South)** | `salesman.b@example.test` | `Password123!` | Scoped South territory customers, drafts, payment evidence upload |
| **Warehouse Manager** | `warehouse.manager@example.test` | `Password123!` | Order allocation, picking, packing, stock adjustment |
| **Delivery Partner** | `driver.dave@example.test` | `Password123!` | Active route deliveries, digital signature capture, POD photo upload |

---

## 2. End-to-End Client Testing Scenarios

### Scenario 1: Salesman Order Creation & Catalogue Browsing
1. Log in as `salesman.a@example.test`.
2. Navigate to **New Order** (`/salesman/orders/create`).
3. Select customer **Apex Supermarket Group (North)** (`CUST-APEX-01`).
4. Browse catalogue items across categories (`Beverages`, `Grocery & Staples`, etc.).
5. Verify product images render securely via signed URLs with 16:10 aspect ratio and price boundary indicators.
6. Add items to cart and submit order.

### Scenario 2: Admin Approval & Price Boundary Enforcement
1. Log in as `admin.qa@example.test`.
2. Navigate to **Orders** (`/admin/orders`).
3. Review submitted orders, inspect line items, tax breakdowns, and approved credit limits.
4. Click **Approve Order** to transition order to `APPROVED` status and generate authoritative invoice.

### Scenario 3: Warehouse Allocation & Dispatch
1. Log in as `warehouse.manager@example.test`.
2. Navigate to **Fulfillment** (`/warehouse/fulfillment`).
3. Allocate reserved inventory, complete picking/packing, and assign order to driver `driver.dave@example.test`.
4. Dispatch order into `DISPATCHED` status.

### Scenario 4: Driver Proof of Delivery & Digital Signature
1. Log in as `driver.dave@example.test`.
2. Navigate to **Deliveries** (`/deliveries`).
3. Open assigned delivery run for `Apex Supermarket Group`.
4. Capture recipient's digital signature on mobile/desktop canvas and take POD photo.
5. Click **Complete Delivery**. Verify files upload privately to S3 and status transitions to `DELIVERED`.

### Scenario 5: Payment Collection with Cheque Evidence Upload
1. Log in as `salesman.a@example.test`.
2. Navigate to **Payments** (`/payments`).
3. Record a Cheque payment of `$644.00` for Order `ORD-2026-0001`.
4. Upload Cheque photo (valid JPEG image).
5. Verify payment enters `PENDING_VERIFICATION` status without posting prematurely to GL.

### Scenario 6: Accountant Maker-Checker Verification & AR Review
1. Log in as `accountant.qa@example.test`.
2. Navigate to **Payment Verification** (`/payments/verification`).
3. Inspect cheque evidence via private preview (15-min temporary presigned S3 URL).
4. Click **Verify Payment**.
5. Navigate to **Accounts Receivable** (`/receivables`) and confirm operational customer balance reflects payment.

---

## 3. Environment Seeding & Reset Commands

To reset the demo environment to its clean initial state at any time:

```bash
# Seed or re-seed the test database and synthetic catalogue assets
php artisan demo:seed --with-images

# Full reset (wipes test DB and re-migrates fresh)
php artisan demo:reset --force --with-images
```

*Note: These commands contain strict environment guards and will automatically refuse to execute in a production environment.*
