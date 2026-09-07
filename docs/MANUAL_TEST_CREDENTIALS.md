# Manual Testing QA Credentials Reference

> [!CAUTION]
> **STRICT NON-PRODUCTION WARNING**
>
> The credentials documented below are **SYNTHETIC TEST ACCOUNTS** generated exclusively for local development, staging verification, manual regression, and responsive UI testing.
>
> **THESE CREDENTIALS MUST NEVER BE USED IN OR COMMITTED TO A PRODUCTION ENVIRONMENT.**
>
> Automated safeguards in `database/seeders/ManualTestingSeeder.php` explicitly abort if executed in a `production` environment.

---

## 1. Document Metadata

- **Document Version:** 1.0
- **Generated / Updated:** September 2026
- **Target Audience:** Developers, Manual QA Testers, Solutions Architects
- **Seeder Source:** [`database/seeders/ManualTestingSeeder.php`](file:///f:/Wholesale%20Distribution%20Management%20System/database/seeders/ManualTestingSeeder.php)
- **Default Test Password:** `Password123!` (Hash: Bcrypt)

---

## 2. Test Accounts Directory

| Account Name | System Role | Account Status | Login Identifier (Email) | Password | Primary Testing Purpose |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Super Administrator (QA)** | `SUPER_ADMIN` | `ACTIVE` | `superadmin.qa@example.test` | `Password123!` | System configuration, security logging, audit reviews, tenant/warehouse settings |
| **Operations Administrator (QA)** | `ADMIN` | `ACTIVE` | `admin.qa@example.test` | `Password123!` | Catalog management, customer approvals, price overrides, user management |
| **Senior Accountant (QA)** | `ACCOUNTANT` | `ACTIVE` | `accountant.qa@example.test` | `Password123!` | Payment verification/rejection, invoice generation, AR/AP ledgers, financial reports |
| **Sales Executive A (North)** | `SALESMAN` | `ACTIVE` | `salesman.a@example.test` | `Password123!` | Mobile/tablet order creation, draft resumption, scoped customer access (Territory A) |
| **Sales Executive B (South)** | `SALESMAN` | `ACTIVE` | `salesman.b@example.test` | `Password123!` | Cross-salesman boundary isolation testing, independent territory customer ordering |
| **Warehouse Dispatch Manager (QA)** | `WAREHOUSE_MANAGER`| `ACTIVE` | `warehouse.qa@example.test` | `Password123!` | Stock allocation, pick/pack flows, delivery run scheduling, inventory balances |
| **Delivery Driver Partner (QA)** | `DELIVERY_PARTNER` | `ACTIVE` | `driver.qa@example.test` | `Password123!` | Delivery portal, route execution, mobile POD (Proof of Delivery), cash collection |
| **Terminated Representative (QA)** | `SALESMAN` | `SUSPENDED` | `suspended.qa@example.test` | `Password123!` | Authentication rejection testing, inactive account session revocation verification |

---

## 3. Account Testing Scenarios & Boundaries

### 3.1 Super Administrator (`superadmin.qa@example.test`)
- **Portal Shell:** Admin Portal Shell (`/admin/*`)
- **Access Level:** Unrestricted platform-wide visibility.
- **Key Test Checks:**
  - View full audit logs (`/admin/audit-logs`), security events, and configuration parameters.
  - Manage all warehouses, tax profiles, and user accounts.

### 3.2 Operations Administrator (`admin.qa@example.test`)
- **Portal Shell:** Admin Portal Shell (`/admin/*`)
- **Access Level:** Administrative operational authority.
- **Key Test Checks:**
  - Product catalog CRUD, price tier updates, inventory counts.
  - Approve pending customer applications and supervisor price overrides.

### 3.3 Senior Accountant (`accountant.qa@example.test`)
- **Portal Shell:** Admin Portal Shell (`/admin/*`)
- **Access Level:** Authoritative financial operations.
- **Key Test Checks:**
  - Verify Cash, Cheque, and Money Order transactions.
  - Inspect JPEG evidence attachments with temporary presigned URLs.
  - Post reversing journal entries and review balance sheets.

### 3.4 Sales Executive A (`salesman.a@example.test`)
- **Portal Shell:** Salesman Portal Shell (`/salesman/*`)
- **Assigned Territory:** North Region (Apex Supermarket Group, Beacon Gourmet & Deli, Echo Corner Grocers).
- **Key Test Checks:**
  - Responsive order creation flow across Mobile ($320\text{px}-430\text{px}$), Tablet ($768\text{px}-1023\text{px}$), and Desktop ($1024\text{px}+$ split-workspace).
  - Verify scoping: **Cannot view or create orders for Salesman B customers.**
  - Test draft creation, auto-saving, local recovery, and server authority recalculation.

### 3.5 Sales Executive B (`salesman.b@example.test`)
- **Portal Shell:** Salesman Portal Shell (`/salesman/*`)
- **Assigned Territory:** South Region (Crestline Wholesale Mart, Delta Convenience Stores).
- **Key Test Checks:**
  - Verify customer isolation: Cannot access Apex Supermarket or Beacon Gourmet.
  - Test order blocking on `ON_HOLD` customer account (`CUST-DLTA-04`).

### 3.6 Warehouse Dispatch Manager (`warehouse.qa@example.test`)
- **Portal Shell:** Admin / Fulfillment Portal Shell
- **Key Test Checks:**
  - Allocate inventory to submitted orders.
  - Generate dispatch batches and assign delivery runs to drivers.

### 3.7 Delivery Driver Partner (`driver.qa@example.test`)
- **Portal Shell:** Delivery Partner Portal Shell (`/delivery/*`)
- **Key Test Checks:**
  - Dedicated mobile-first card interface (`/delivery/today`).
  - Update delivery status (`OUT_FOR_DELIVERY` $\to$ `DELIVERED`).
  - Verify access restriction: Cannot access administrative or accounting routes.

### 3.8 Suspended User (`suspended.qa@example.test`)
- **Key Test Checks:**
  - Attempt login: Should be immediately rejected with an account suspension notice.
  - Verify no API tokens or session cookies are issued.

---

## 4. Multi-Factor Authentication (MFA) Instructions

- In the local testing and QA seed environment, synthetic test accounts are initialized without hardware MFA tokens enabled to streamline automated and manual test runs.
- If MFA workflows are explicitly activated via the user profile settings (`/profile/security`), use the standard TOTP authenticator test secret key generated by the application.

---

## 5. Environment Reset & Re-Seeding

To restore the manual testing dataset to a pristine initial state at any point during testing, execute:

```powershell
php artisan db:seed --class=ManualTestingSeeder
```

*(Non-destructive `updateOrCreate` logic ensures test accounts and foundational dataset are preserved or cleanly synchronized).*
