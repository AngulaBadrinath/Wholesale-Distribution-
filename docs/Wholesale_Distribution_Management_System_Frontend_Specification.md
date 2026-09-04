# Document 04 — Frontend Specification

## Wholesale Distribution Management System

**Document Type:** Frontend Specification / UX Engineering Specification  
**Document Number:** 04  
**Document Version:** 1.0  
**Status:** Development Baseline — Design Direction Locked  
**Primary Market:** United States  
**Currency:** USD  
**Design Quality Target:** Premium B2B SaaS / ERP, suitable for a $3k–$5k production-quality client engagement and a professional developer portfolio  
**Depends On:** PRD v1.0, Technical Architecture v1.0, Security & Access v1.0  
**Implementation Target:** Laravel 13 + React 19.2 + TypeScript + Inertia 3 + Tailwind CSS 4 + shadcn/ui + Vite  

> **Locked design direction:** Premium B2B Commerce × Modern SaaS ERP.
>
> Use Linear/Vercel-level visual discipline and interaction consistency, Stripe-style financial/data hierarchy, premium ecommerce product discovery, operational ERP/WMS density, and purpose-designed mobile UX for Salesman and Delivery Partner. These references are inspiration for principles and information hierarchy, not a request to copy proprietary branding or layouts.

---

# 0. Executive Design Contract

This document defines the frontend as a **production-grade business product**, not a generic admin template.

The interface must simultaneously be:

- visually premium;
- operationally efficient;
- highly responsive;
- role-specific;
- accessible;
- data-dense where useful;
- calm rather than flashy;
- consistent through a shared design system;
- safe around financial, inventory, payment and authorization workflows;
- easy to extend for future modules and future client work.

The frontend is a usability layer. It must never become the security boundary. Backend authorization, resource scope, state validation, quantity validation, price validation, tax calculation, inventory integrity and financial authority remain server-side concerns.

Security architecture requires the sequence **authentication → identity → role → permission → resource scope → business-state validation → domain validation → atomic operation → audit**. The UI may reflect those decisions but must never substitute for them.

The Technical Architecture defines a modular monolith, PostgreSQL as transactional source of truth, quantity-based allocation, explicit adjustment records, private object storage, server-side authorization and historical transaction snapshots. The frontend must expose these concepts clearly without mutating history or pretending derived UI state is authoritative.

---

# 1. Design North Star

## 1.1 Product identity

The product should feel like a mature B2B operational platform:

**Premium B2B Commerce**  
+ **Modern SaaS Workspace**  
+ **Enterprise ERP Operations**

### Visual benchmark

| Quality axis | Target |
|---|---|
| Visual polish | Premium SaaS |
| Information density | Medium-high on desktop, controlled elsewhere |
| Brand expression | Restrained and confident |
| Navigation | Quiet, predictable, contextual |
| Data visualization | Useful, legible, non-decorative |
| Commerce UX | Strong and fast |
| Mobile UX | Purpose-designed, touch-first |
| Tablet UX | Adaptive operational workspace |
| Motion | Subtle micro-interactions |
| Accessibility | WCAG-oriented from component level |
| Consistency | Design-system driven |

## 1.2 What the product must NOT look like

Avoid:

- generic Bootstrap/admin-template appearance;
- rainbow dashboards;
- excessive gradients;
- giant rounded cards everywhere;
- glassmorphism as a default surface treatment;
- excessive shadows;
- cramped 12-column tables on mobile;
- desktop UI simply scaled down to phones;
- decorative charts with little decision value;
- inconsistent icon styles;
- arbitrary colors for statuses;
- modal overload;
- visual clutter caused by showing every possible field at once.

## 1.3 Design principles

1. **Hierarchy before decoration.**
2. **The work area gets visual priority over navigation chrome.**
3. **Every page answers “What should I do next?”**
4. **Progressive disclosure is preferred over visual overload.**
5. **Use cards for grouping, not because a card component exists.**
6. **Mobile is a first-class workflow, not a breakpoint accident.**
7. **Touch targets must be comfortable.**
8. **State must be visible without relying on color alone.**
9. **Every critical mutation has visible feedback.**
10. **The original transaction remains distinguishable from later adjustments.**
11. **Financial and inventory values are presented with precision and alignment.**
12. **The design system must make future screens cheaper to build, not harder.**

---

# 2. Reference Library and Visual Benchmarks

These references are the approved inspiration set. They should be reviewed by the designer/developer before implementing the corresponding surface.

## 2.1 Linear — interaction and workspace discipline

**Official reference:** https://linear.app/  
**2026 UI refresh:** https://linear.app/changelog/2026-03-12-ui-refresh  
**Design process:** https://linear.app/now/behind-the-latest-design-refresh

Use for:

- calm visual hierarchy;
- consistent headers and view controls;
- navigation that recedes behind the work area;
- precise iconography;
- predictable interaction placement;
- progressive disclosure.

## 2.2 Vercel / Geist — design system discipline

**Official reference:** https://vercel.com/geist/introduction  
**Typography:** https://vercel.com/geist/typography  
**Grid:** https://vercel.com/geist/grid

Use for:

- design tokens;
- typography system;
- grid logic;
- consistent components;
- accessibility-minded color system;
- spacing/rhythm.

## 2.3 Stripe Dashboard — financial and operational information architecture

**Official reference:** https://docs.stripe.com/dashboard/basics

Use for:

- resource-oriented navigation;
- transaction tables;
- financial summaries;
- search/filter behavior;
- detail pages;
- operational data density.

## 2.4 B2B product catalogue reference

**Way2Order:** https://way2order.com/  
Reference image: https://www.way2order.com/images/way2order_b2b-sales-ordering-app_order-steps_XsMax_2x.png

Use for:

- product-first B2B ordering;
- mobile catalogue/order flow;
- category/product discovery;
- compact order controls.

## 2.5 B2B ecommerce desktop reference

**Dribbble — A sophisticated B2B ecommerce portal:** https://dribbble.com/shots/1726195-A-sophisticated-B2B-ecommerce-portal

Reference image: https://cdn.dribbble.com/users/88335/screenshots/1726195/attachments/277632/B2b_e_commerce_portal.jpg

Use for:

- product grid;
- advanced filters;
- product-detail density;
- B2B purchase controls.

## 2.6 Inventory/WMS reference

Reference image/source: https://kanhasoft.com/production-and-logistics-ai-powered-erp-software.html  
Image: https://kanhasoft.com/assets/images/portfolio/erp-production-logistics/erp-production-logistics-5.png

Use for:

- inventory health overview;
- movement/valuation information;
- stock exception hierarchy;
- compact tables and metrics.

## 2.7 Driver / logistics mobile reference

**Dribbble — Logistics mobile app for drivers:** https://dribbble.com/shots/14512438-Logistics-mobile-app-for-drivers  
Reference image: https://cdn.dribbble.com/users/833700/screenshots/14512438/logistics_app.png

Use for:

- task-first driver UX;
- route/status progression;
- large touch targets;
- mobile operational clarity.

## 2.8 Finance SaaS reference

**Dribbble — Finance SaaS Dashboard / VisionUI:** https://dribbble.com/shots/25963203-Finance-SAAS-Dashboard-VisionUI  
Reference image: https://cdn.dribbble.com/userupload/43118896/file/original-3103de4b349c70a911c5bf4b76beb6aa.png?resize=1600x1200

Use for:

- revenue/expense hierarchy;
- charts with adjacent context;
- recent-activity surfaces;
- financial dashboard composition.

## 2.9 Responsive analytics reference

Reference image/source: https://hoa.ninja/  
Image: https://hoa.ninja/img.png

Use for:

- cross-device composition;
- preserving hierarchy across desktop and mobile;
- analytics visual adaptation.

---

# 3. Responsive Framework

## 3.1 Breakpoints

Use a mobile-first CSS strategy with these product review widths:

| Name | Range / target | Primary use |
|---|---:|---|
| Mobile S | 320px | smallest supported phone |
| Mobile | 375px | common phone baseline |
| Mobile L | 390–430px | modern phones |
| Tablet S | 640–767px | small tablets / landscape phones |
| Tablet | 768–1023px | tablets |
| Desktop | 1024–1279px | compact desktop |
| Desktop L | 1280–1439px | normal business desktop |
| Desktop XL | 1440–1919px | premium default review |
| Wide | 1920px+ | large monitor |

The implementation may use framework breakpoints, but behavior must be validated against the above widths.

## 3.2 Responsive philosophy

A breakpoint may change:

- navigation mode;
- information priority;
- column count;
- card composition;
- table representation;
- action placement;
- filter mechanism;
- form stacking;
- drawer/sheet/modal strategy;
- chart density;
- typography scale;
- amount of secondary metadata.

A breakpoint must **not** merely reduce every dimension proportionally.

## 3.3 Desktop

Default Admin experience at 1280–1440px:

- persistent sidebar;
- quiet header;
- content max-width when appropriate;
- dense but readable tables;
- multi-column dashboard compositions;
- contextual action bars;
- side detail sheets where useful.

## 3.4 Tablet

At 768–1023px:

- sidebar collapses into compact navigation/drawer;
- table columns reduce to priority fields;
- two-column forms become one column where needed;
- side sheets become larger or full-height;
- touch targets remain comfortable;
- charts reduce ticks before reducing legibility;
- bulk actions remain available but move into menus.

## 3.5 Mobile

Below 640px:

- use intentional mobile layouts;
- use bottom navigation for Salesman and Delivery Partner;
- Admin uses a drawer/compact top navigation rather than an always-visible sidebar;
- tables become stacked cards or evidence lists;
- filters become bottom sheets or full-screen filter panels;
- primary action may become sticky bottom CTA;
- forms become one logical step per screen section;
- detail surfaces may become full-screen routes rather than side panels;
- secondary actions move into overflow menus;
- page headers may stack.

## 3.6 Mobile touch target baseline

Interactive controls should generally provide at least ~44px usable touch target, with tighter visual density achieved via padding/layout rather than tiny controls.

---

# 4. Design System

## 4.1 Design tokens

All visual decisions must use tokens rather than scattered literals.

### Color roles

```text
--color-bg
--color-surface
--color-surface-elevated
--color-surface-muted
--color-border
--color-border-strong
--color-text
--color-text-secondary
--color-text-tertiary
--color-brand
--color-brand-foreground
--color-success
--color-warning
--color-danger
--color-info
--color-focus
```

Use semantic role names instead of product-specific names where possible.

### Status treatment

Every status should combine:

- short text label;
- visual indicator (dot/icon/badge);
- optional supporting explanation.

Do not communicate critical state using color alone.

### Typography

Use a highly readable modern sans-serif, with **Inter or Geist Sans as the preferred direction** depending on final asset/licensing and implementation convenience.

Suggested scale:

| Purpose | Size | Weight |
|---|---:|---:|
| Display | 30–34px | 600–700 |
| Page title | 22–26px | 600–700 |
| Section title | 16–18px | 600 |
| Body | 14–15px | 400–500 |
| Supporting | 13px | 400–500 |
| Caption | 12px | 400–500 |
| Numeric emphasis | 20–30px | 600–700 |

Exact values must be defined in tokens before implementation.

### Spacing

Use a predictable spacing scale, ideally based on 4px increments. Common spacing should be 4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / 48 / 64px.

### Radius

Recommended:

- 6px compact controls;
- 8px standard control/card radius;
- 12px grouped surfaces;
- 16px showcase/large containers;
- pill only for tags/statuses where semantically useful.

Avoid making every component pill-shaped.

### Elevation

Prefer borders and subtle tonal separation. Use shadows only for overlays, sheets, dialogs, floating controls and deliberate elevation.

---

# 5. Application Shell

## 5.1 Shared shell

Desktop structure:

```text
┌─────────────────────────────────────────────────────────────┐
│ Brand / Context       Search        Alerts       User       │
├──────────────┬──────────────────────────────────────────────┤
│ Navigation   │ Page header                                  │
│              │                                               │
│              │ Main content                                 │
│              │                                               │
└──────────────┴──────────────────────────────────────────────┘
```

The shell should feel stable while the page content changes.

## 5.2 Global header

Include, depending on role:

- page/workspace identity;
- global search where beneficial;
- notification trigger;
- help/support entry;
- profile/avatar;
- account/security state indicator only where useful.

Avoid stuffing the header with unnecessary controls.

## 5.3 Navigation

Navigation groups should be stable and predictable. Active state uses a subtle but unmistakable treatment.

Sidebar may support:

- expanded mode;
- collapsed icon mode;
- contextual badges/counts only for actionable items;
- keyboard focus.

## 5.4 Mobile shell

Salesman:

```text
Home | Customers | Orders | Products | More
```

Delivery Partner:

```text
Today | Deliveries | History | More
```

Admin:

- compact header;
- menu drawer;
- contextual breadcrumbs where helpful.

---

# 6. Portal A — Admin

## 6.1 Admin information architecture

```text
Dashboard

Sales
  ├── Orders
  ├── New Orders
  ├── Needs Attention
  ├── Processing
  ├── Delivery
  ├── Completed
  ├── Cancelled
  └── Returns

Customers

Products
  ├── Catalogue
  ├── Categories
  └── Pricing / Product Settings

Inventory

Payments

Accounting

Reports

Users & Access

Settings
```

Operational queues must be filtered views over the same authoritative order system, not duplicated order stores.

## 6.2 Admin dashboard

### Purpose

Answer:

1. What happened today?
2. What needs attention?
3. What is trending?
4. What happened recently?

### Layout — Desktop

```text
Page title + date/filter

[New Orders] [Pending Approval] [Deliveries Today] [Payment Verification]

┌───────────────────────────────┐ ┌─────────────────────────────┐
│ Sales / Order Trend           │ │ Needs Attention              │
│                               │ │ • Stock exceptions           │
│ line/area chart               │ │ • Adjustment requests        │
│                               │ │ • Failed deliveries          │
└───────────────────────────────┘ │ • Payment verification       │
                                  └─────────────────────────────┘

┌───────────────────────────────┐ ┌─────────────────────────────┐
│ Recent Orders                 │ │ Recent Activity              │
└───────────────────────────────┘ └─────────────────────────────┘
```

### Mobile transformation

- KPI row becomes horizontal scroll or compact 2-up blocks;
- Needs Attention appears before analytics;
- recent orders become cards;
- chart becomes simplified with fewer labels.

### Reference image

![Responsive analytics reference](https://hoa.ninja/img.png)

**Build lesson:** preserve hierarchy while reducing density; do not shrink the desktop dashboard blindly.

## 6.3 Orders — list / queue pages

### Queues

- New Orders
- Needs Attention
- Processing
- Delivery
- Completed
- Cancelled
- Returns

### New / Previous requirement

“New Orders” and “Previous Orders” are saved/filter views over the same order records. The UI should not present them as unrelated modules.

### Desktop

```text
Orders                     Search...   Filters   Saved View   Export

┌──────┬─────────────┬─────────┬──────────┬──────────┬────────────┐
│ Order│ Customer    │ Salesman│ Amount   │ Payment  │ Status     │
├──────┼─────────────┼─────────┼──────────┼──────────┼────────────┤
│10482 │ ABC Store   │ Alex    │ $842.20  │ Cash     │ Processing │
│10481 │ XYZ Mart    │ Jordan  │ $421.00  │ Cheque   │ Delivery   │
└──────┴─────────────┴─────────┴──────────┴──────────┴────────────┘
```

### Tablet

Retain:

- Order
- Customer
- Amount
- Status

Move low-priority metadata into row detail or sheet.

### Mobile

```text
┌──────────────────────────────┐
│ #10482              ● Processing
│ ABC Retail Store             │
│ 8 Items                      │
│ $842.20                      │
│ Payment: Cash                │
│                              │
│ View order →                 │
└──────────────────────────────┘
```

### Reference image

![Finance table reference](https://reetail.store/stripe.png)

**Build lesson:** use high scanability, compact status treatments and strong currency alignment.

## 6.4 Order detail

Order detail is one of the primary showcase surfaces.

### Information architecture

Header:

- order number;
- current operational status;
- customer;
- created time;
- actor;
- key actions.

Sections:

1. Order progress
2. Customer
3. Items
4. Payment
5. Delivery
6. Adjustments
7. Returns
8. Financial summary
9. Activity/audit context

### Order progress

```text
Created → Approved → Processing → Delivery → Completed
```

Payment, delivery and return statuses are separate dimensions where applicable.

### Item presentation

Every order item should make these distinguishable:

| Field | Meaning |
|---|---|
| Ordered Qty | Original customer-requested quantity |
| Allocated Qty | Quantity currently fulfilable / allocated |
| Adjusted/Cancelled Qty | Quantity removed from fulfilment |
| Unit Price | Historical transaction price |
| Tax | Historical applicable tax snapshot |
| Line Total | Derived financial result |

Never overwrite the original ordered quantity.

### Premium quantity treatment

```text
Ordered      10
Allocated     8
Adjusted      2
```

Visualize this as a compact quantity breakdown, not as three unrelated numbers.

### Reference image

![B2B ecommerce detail reference](https://cdn.dribbble.com/users/88335/screenshots/1726195/attachments/277632/B2b_e_commerce_portal.jpg)

**Build lesson:** detailed product information can remain visually elegant when hierarchy is deliberate.

## 6.5 Order adjustment / allocation

This workflow needs unusually strong UX because it changes fulfilment and financial results.

### Adjustment panel

Display:

- original order;
- item;
- ordered quantity;
- current allocation;
- proposed adjustment;
- remaining quantity;
- reason;
- notes;
- inventory impact;
- tax impact;
- financial impact;
- actor/requester;
- approval state.

### Example

```text
Product A

Ordered       10
Allocated      8
Adjustment     2

Reason
Damaged stock

Financial impact
Subtotal   -$25.00
Tax         -$1.75
Total      -$26.75

[Cancel] [Submit Adjustment]
```

### Approval view

```text
Adjustment Request

Requested by: Alex
Order: #10482
Product: Product A
Requested change: 10 → 8 allocated
Reason: Damaged stock

Inventory impact     -2 deliverable units
Tax impact            -$1.75
Financial impact      -$26.75

[Reject] [Approve]
```

Sensitive operations must show the quantity, inventory, tax and financial consequences before confirmation. The security document requires current-state, permission, resource-scope, quantity, inventory, tax and financial validation before atomic application.

## 6.6 Products / catalogue

### Desktop catalogue

```text
Products                        Search       Filters   Grid/List

Category chips

┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ image    │ │ image    │ │ image    │ │ image    │
│ brand    │ │ brand    │ │ brand    │ │ brand    │
│ product  │ │ product  │ │ product  │ │ product  │
│ $24.50   │ │ $18.20   │ │ $42.10   │ │ $12.80   │
│ ● 124    │ │ ● 82     │ │ ! 12     │ │ × 0      │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

Product images may be used in catalogue UI, but **invoices must not display product images**.

### Reference image

![B2B ecommerce catalogue reference](https://cdn.dribbble.com/users/88335/screenshots/1726195/attachments/277632/B2b_e_commerce_portal.jpg)

## 6.7 Product detail / pricing

Show:

- image;
- product identity;
- category;
- SKU/identifier;
- MRP;
- configured selling price;
- tax configuration;
- inventory availability;
- activation state;
- metadata.

Price editing must clearly identify current vs future/historical impact. Existing orders retain their historical price snapshots.

## 6.8 Inventory dashboard

### Primary questions

- What is low?
- What is out of stock?
- What needs replenishment?
- What moved recently?
- What is the inventory value?

### Reference image

![ERP inventory reference](https://kanhasoft.com/assets/images/portfolio/erp-production-logistics/erp-production-logistics-5.png)

Use the reference for hierarchy, not aesthetics copying.

### Inventory rules

Never expose a frontend affordance that implies “set available stock = X” when the domain requires inventory movements, reservations and allocations. Inventory screens should show:

- on hand;
- reserved;
- allocated;
- available;
- movement history;
- exception state;
- authorized adjustment entry point.

## 6.9 Customers

### List

Desktop table:

- Customer
- Contact
- Assigned Salesman
- Outstanding
- Credit Limit
- Status
- Last Order

Mobile card:

```text
ABC Retail Store
Assigned: Alex
Outstanding: $1,280
Credit: $5,000
● Active
```

### Detail

Sections:

- profile;
- address/contact;
- assigned salesman;
- order history;
- payment history;
- outstanding;
- credit limit;
- returns/credits where applicable;
- activity.

## 6.10 Payments

### Supported V1 methods

- Cash
- Cheque
- Money Order

### UI

Payment list should show:

- customer/order;
- method;
- amount;
- status;
- created time;
- verification state;
- evidence available state.

### Cheque / Money Order evidence

Upload workflow:

```text
Upload payment evidence

┌──────────────────────────────┐
│         Upload JPEG          │
│      drag or select file     │
└──────────────────────────────┘

→ Preview

┌──────────────────────────────┐
│ [thumbnail] cheque.jpg       │
│ 1.8 MB             ✓ Ready   │
│                              │
│ Replace    Remove            │
└──────────────────────────────┘
```

The evidence must be treated as a private business document. UI should never expose raw public storage URLs.

Payment verification UI should show:

- submitted amount;
- evidence preview;
- current payment state;
- verified by;
- verification time;
- reason/notes where relevant.

The security contract explicitly protects payment evidence and requires permission, resource-scope, current-state and evidence checks before verification.

## 6.11 Delivery management

Admin surfaces:

- unassigned;
- assigned;
- out for delivery;
- failed;
- completed.

Assignment UI should be fast:

```text
Order #10482
Delivery address
Preferred window

Assign delivery partner
[ Select partner ▼ ]

[Assign]
```

## 6.12 Accounting

Accounting should look like a serious financial workspace, not a generic report page.

Suggested navigation:

```text
Accounting
 ├── Overview
 ├── Accounts / Chart of Accounts
 ├── Transactions
 ├── Journal Entries
 ├── Receivables
 ├── Credits / Refunds
 ├── Reconciliation / Verification
 └── Audit / Reversals
```

### Reference image

![Finance SaaS reference](https://cdn.dribbble.com/userupload/43118896/file/original-3103de4b349c70a911c5bf4b76beb6aa.png?resize=1600x1200)

Posted accounting entries must not have normal “delete” controls. Reversal must be presented as an explicit controlled operation.

## 6.13 Reports

Report shell:

- report title;
- period selector;
- filter group;
- export/print;
- summary metrics;
- chart/table;
- drill-down.

Support the business-relevant periods:

- Daily
- Weekly
- Monthly
- Yearly

Avoid charts that become unreadable at mobile width. Prefer summary + drill-down.

## 6.14 Users & Access

Admin UI should make the distinction between:

- account state;
- role;
- permissions;
- resource scope.

Suggested screen:

```text
User
Role
Status
MFA state
Last active
Actions
```

Sensitive role/permission changes should clearly show impact and require confirmation. Confirmation is a UX aid, not the security mechanism.

---

# 7. Portal B — Salesman

## 7.1 Design objective

The Salesman portal is a **sales workspace**, not a mini Admin portal.

Primary jobs:

- visit customer;
- find customer;
- create order quickly;
- browse products;
- set authorized selling price;
- see customer/order status;
- record payment;
- follow order progress.

## 7.2 Mobile home

### Reference image

![B2B mobile ordering reference](https://www.way2order.com/images/way2order_b2b-sales-ordering-app_order-steps_XsMax_2x.png)

### Layout

```text
Good morning, Alex

Today

[12 Orders] [4 Pending]

[ + New Order ]

Quick access
[Customers] [Products]

Recent Orders
```

Primary CTA should be visually dominant.

## 7.3 Customer selection

Flow:

```text
New Order
   ↓
Select Customer
   ↓
Search customer
   ↓
Customer summary
   ↓
Continue
```

Show relevant financial constraints without overwhelming the salesman.

If customer credit is near/over configured limits, make the constraint visible and explain the next action. Do not let the UI imply it can override a blocked transaction unless backend policy permits it.

## 7.4 Product browsing

Product-first experience:

- search;
- category chips;
- optional brand/category filters;
- product image;
- product name;
- selling price;
- MRP;
- stock/availability;
- quantity control.

### Mobile card

```text
┌───────────────────────────┐
│ [image]                   │
│ Product Name              │
│ $24.50    MRP $28.00     │
│ ● 124 available           │
│                           │
│        −   4   +          │
└───────────────────────────┘
```

## 7.5 Order creation — flagship workflow

### Desktop

Split workspace:

```text
┌──────────────────────────────────────────────────────────────┐
│ Customer: ABC Retail Store                                   │
├───────────────────────────────┬──────────────────────────────┤
│ Product search/catalogue      │ Order summary                │
│                               │                              │
│ Product A                     │ 8 items                      │
│ $12.50   − 4 +               │ Subtotal                     │
│                               │ Tax                          │
│ Product B                     │ Total                        │
│ $18.20   − 2 +               │                              │
│                               │ [Review Order]               │
└───────────────────────────────┴──────────────────────────────┘
```

### Mobile

Use a staged flow with persistent summary context:

1. customer;
2. products;
3. quantities;
4. pricing/payment;
5. review;
6. submit.

Sticky footer:

```text
4 items       $128.40

[ Review Order ]
```

## 7.6 Pricing UX

Selling price input must remain within authorized backend constraints.

UI should communicate:

```text
Selling Price   $24.50
MRP             $28.00
```

If editing is restricted, disable or hide the input appropriately, but never treat frontend visibility as authorization.

## 7.7 Payment entry

For supported methods:

### Cash

Simple amount + confirmation.

### Cheque

- amount;
- cheque details if required;
- JPEG evidence;
- preview;
- replace/remove.

### Money Order

- amount;
- money order details if required;
- JPEG evidence;
- preview;
- replace/remove.

## 7.8 Order review

Review page must make consequences obvious before submission:

```text
Customer
Items
Quantity
Price
Tax
Subtotal
Total
Payment method
Evidence status (when required)
```

Never ask the salesman to re-enter derived totals.

## 7.9 Salesman order history

Use cards on mobile and compact tables on desktop.

Useful filters:

- date;
- customer;
- status;
- payment state.

## 7.10 Salesman customer detail

Show:

- customer summary;
- contact;
- outstanding;
- credit context;
- recent orders;
- quick “New Order”.

---

# 8. Portal C — Delivery Partner

## 8.1 Design objective

This is a **logistics task workspace**.

Do not expose accounting, pricing controls or unnecessary administrative detail.

## 8.2 Today dashboard

### Reference image

![Driver logistics reference](https://cdn.dribbble.com/users/833700/screenshots/14512438/logistics_app.png)

### Layout

```text
Good morning, Mike

TODAY
12 Deliveries
4 Completed
3 Out for Delivery
5 Remaining

NEXT DELIVERY
ABC Retail Store
123 Main Street

[ Start Delivery ]
```

## 8.3 Deliveries list

Cards should emphasize:

- customer/store;
- address;
- delivery window;
- order number;
- amount/payment method when operationally necessary;
- current delivery state.

## 8.4 Delivery detail

```text
Delivery #10482

ABC Retail Store
123 Main Street

Order summary
8 products

Payment
Cash

[Start Delivery]
```

State progression:

```text
Assigned → Out for Delivery → Delivered
                         ↘ Failed
```

## 8.5 Completion UX

Completion should provide:

- clear completion action;
- delivery confirmation where required by business policy;
- optional notes/evidence when the workflow supports it;
- failure reason path.

The Delivery Partner must not have UI controls for price, tax, accounting, refunds or arbitrary order changes.

---

# 9. Invoices and Printable Documents

## 9.1 Invoice design direction

Invoices are business documents, not marketing pages.

Must include:

- business identity;
- invoice/order identifier;
- customer information;
- dates;
- item descriptions;
- quantities;
- unit price;
- tax;
- totals;
- payment state/method as applicable.

**Do not display product images on invoices.**

## 9.2 Print behavior

Provide a dedicated print stylesheet and controlled printable layout.

Buttons:

- Print
- Download PDF where permitted

Avoid printing navigation, page chrome, interactive controls or decorative dashboard widgets.

---

# 10. Shared Component System

The following components must be reusable, tokenized and responsive.

## 10.1 Core

- Button
- IconButton
- Link
- Badge
- StatusBadge
- Avatar
- Tooltip
- Separator

## 10.2 Form

- Input
- Textarea
- Select
- Combobox
- SearchInput
- DatePicker
- DateRangePicker
- CurrencyInput
- QuantityInput
- PriceInput
- Checkbox
- Radio
- Switch
- FormField
- FieldError

## 10.3 Data

- DataTable
- MobileListCard
- Pagination
- SortHeader
- FilterBar
- FilterChip
- EmptyState
- Skeleton
- StatBlock
- MetricCard
- Timeline
- ActivityFeed

## 10.4 Overlay

- Modal
- Drawer
- BottomSheet
- Sheet
- Popover
- DropdownMenu
- CommandPalette if justified

Use a Sheet for persistent associated context and a Modal for blocking decisions. On mobile, use a Drawer/BottomSheet for filters and compact contextual actions where appropriate.

## 10.5 Workflow

- Stepper
- OrderSummary
- OrderItemRow
- QuantityBreakdown
- AdjustmentPanel
- ApprovalPanel
- PaymentEvidenceUploader
- ImagePreview
- DeliveryProgress
- FinancialSummary
- ConfirmationDialog

---

# 11. Component State Contract

Every important component must define at least:

- default;
- hover;
- focus-visible;
- pressed/active;
- disabled;
- loading;
- success;
- error;
- empty where applicable;
- read-only where applicable.

### Example — Button

```text
Default → Hover → Focus → Pressed → Loading → Success/Error
```

### Example — Upload

```text
Idle → Selecting → Uploading → Preview Ready → Failed → Replace
```

### Example — Order submit

```text
Review → Submitting → Created
                ↘ Validation Error
                ↘ Conflict / Stock Error
                ↘ Network Recovery State
```

Do not use optimistic UI for financial, payment, inventory or order-authoritative mutations unless the operation is demonstrably safe and reversible.

---

# 12. Interaction and Motion

## 12.1 Motion philosophy

Motion should explain change, not decorate it.

Use:

- 120–200ms micro transitions for controls;
- subtle drawer/sheet motion;
- table/filter state changes where helpful;
- success confirmation only when meaningful.

Avoid:

- bouncing controls;
- constant background animation;
- decorative particle effects;
- long page transitions.

## 12.2 Loading

Use skeletons for:

- dashboards;
- tables;
- product grids;
- detail sections.

Use inline spinners for short mutations.

## 12.3 Toasts

Use toasts for:

- completed background-friendly actions;
- successful saves;
- non-blocking warnings.

Do not rely on toast-only feedback for irreversible or high-risk operations.

---

# 13. Search, Filters and Data Density

Search/filtering is a first-class product capability.

## Desktop

```text
Search [........................]  Filters  Saved View  Export
```

## Mobile

```text
Search...
[Filter] [Status] [Customer]
```

Filters should open as a bottom sheet or dedicated filter page.

Preserve filter context when returning from detail where practical.

## Pagination

Default server-driven pages should target approximately 25–50 rows for data-heavy lists, subject to actual query cost and user testing.

Never load full customer, order or product history merely because a list page is opened.

---

# 14. Empty, Error and Edge States

Every production page must explicitly design:

## Empty

```text
No orders yet

Orders placed by Salesmen will appear here.

[Create Order]
```

## Error

```text
We couldn't load this order.

[Try again]
```

## Permission

```text
You don't have permission to perform this action.
```

## Stale/conflict state

```text
This order changed while you were editing it.
Review the latest values before continuing.
```

## Stock conflict

```text
Only 8 units are currently available.
Requested: 10
Available: 8
```

The UI should help the user understand the domain problem rather than displaying generic “400 error” messages.

---

# 15. Accessibility

Minimum implementation expectations:

- semantic HTML;
- visible focus styles;
- keyboard navigation;
- logical tab order;
- accessible names/labels;
- status not communicated by color alone;
- screen-reader-friendly form errors;
- table headers/relationships;
- dialog focus management;
- Escape handling for overlays where appropriate;
- touch target compliance;
- reduced-motion support;
- sufficient contrast.

Reference the interaction discipline of modern design systems; Vercel's Geist explicitly treats colors, typography, grid and components as system foundations rather than isolated styling decisions.

---

# 16. Security-Aware UX Rules

These are frontend rules derived from the security contract.

## 16.1 Never trust frontend values

Treat as untrusted:

- quantity;
- price;
- tax;
- subtotal;
- tax total;
- grand total;
- outstanding;
- inventory availability;
- status;
- approval state;
- role;
- permission;
- verified_by;
- approved_by.

The UI may calculate previews, but the backend is authoritative.

## 16.2 Permission-aware UX

The UI may:

- hide irrelevant actions;
- disable currently unavailable actions with explanation;
- show role-appropriate menus.

The UI must not imply hidden controls are secure controls. Backend authorization remains mandatory.

## 16.3 Sensitive action confirmation

Confirmation required/recommended for:

- cancel order;
- cancel item quantity;
- approve adjustment;
- reverse payment;
- approve refund;
- reverse journal;
- role changes;
- permission changes.

Where useful show:

```text
Quantity impact
Inventory impact
Tax impact
Financial impact
```

## 16.4 Private documents

Cheque/money-order images and invoice files should be retrieved through authorized backend flows. Do not hard-code public object URLs into the UI.

---

# 17. Data Formatting Rules

## Currency

- USD display by default;
- consistent currency symbol;
- two decimal places when appropriate;
- currency values right-aligned in tables;
- negative values visually distinguishable.

## Dates

Use one consistent localized format per product area. Avoid mixing multiple date styles on the same page.

## Quantities

Use numeric alignment and explicit unit labels where the product requires them.

## IDs

Order/customer/payment identifiers should remain easy to scan and copy.

---

# 18. Performance UX

Frontend must support a professional-feeling product even on average business hardware and mobile networks.

Use:

- server-side pagination;
- debounced search;
- skeleton loading;
- lazy-loaded secondary sections;
- responsive image sizing;
- compact payloads;
- progressive detail loading where safe;
- virtualized lists only where actual volume warrants it.

Do not:

- fetch entire catalogues unnecessarily;
- load complete order histories into every page;
- render large unused hidden DOM trees;
- block the interface on non-critical analytics.

---

# 19. Design QA and Review Viewports

Every major page must be reviewed at:

```text
320 × 720
375 × 812
390 × 844
430 × 932
768 × 1024
820 × 1180
1024 × 768
1280 × 800
1440 × 900
1920 × 1080
```

For every page, verify:

1. no horizontal overflow unless intentionally scoped;
2. primary CTA remains obvious;
3. important status remains visible;
4. no essential financial/quantity data is silently lost;
5. filters remain usable;
6. tables transform intentionally;
7. overlays fit safely;
8. keyboard flow works on desktop;
9. touch controls work on tablet/mobile;
10. empty/error/loading states remain polished.

---

# 20. Page-by-Page Reference Matrix

| Page / flow | Desktop reference | Tablet reference | Mobile reference |
|---|---|---|---|
| Admin Dashboard | Linear / Stripe / finance SaaS | responsive analytics composition | responsive analytics composition |
| Orders List | Stripe resource table | compact priority columns | card list |
| Order Detail | Stripe detail + B2B detail | stacked sections / sheet | full-screen sections |
| Adjustment | ERP operational panel | stacked review | bottom-sheet/fullscreen review |
| Product Catalogue | B2B ecommerce portal | 2–3 column adaptive grid | B2B mobile ordering |
| Product Detail | B2B ecommerce detail | stacked detail | compact detail + sticky action |
| Inventory | ERP inventory dashboard | tablet table/metrics | stock cards / exception list |
| Customers | Stripe-like resource list | reduced columns | customer cards |
| Payments | Stripe payment concepts | compact verification workspace | verification cards + evidence preview |
| Accounting | finance SaaS + Stripe | stacked financial widgets | summary-first mobile |
| Reports | finance/analytics | reduced chart density | summary + drill-down |
| Admin Users | SaaS admin management | compact table | user cards |
| Salesman Home | B2B operational app | adaptive dashboard | task-first mobile |
| Salesman Order | B2B ordering | split/stacked | staged mobile flow |
| Salesman Products | ecommerce/B2B catalogue | adaptive grid | mobile cards |
| Salesman Customers | CRM/B2B list | compact list | customer cards |
| Delivery Today | logistics reference | tablet task list | driver-first cards |
| Delivery Detail | operational detail | stacked | full-screen task flow |
| Invoice | business document | printable | printable, not dashboard UI |

---

# 21. Reference Image Set for AI-Assisted Implementation

The following image references should be supplied to Gemini/Claude/Antigravity when they design/implement the associated surfaces.

### Reference A — B2B mobile order journey

![B2B mobile ordering journey](https://www.way2order.com/images/way2order_b2b-sales-ordering-app_order-steps_XsMax_2x.png)

### Reference B — B2B ecommerce catalogue/detail

![B2B ecommerce portal](https://cdn.dribbble.com/users/88335/screenshots/1726195/attachments/277632/B2b_e_commerce_portal.jpg)

### Reference C — Logistics mobile workflow

![Logistics mobile app](https://cdn.dribbble.com/users/833700/screenshots/14512438/logistics_app.png)

### Reference D — Inventory dashboard

![ERP inventory dashboard](https://kanhasoft.com/assets/images/portfolio/erp-production-logistics/erp-production-logistics-5.png)

### Reference E — Finance dashboard

![Finance SaaS dashboard](https://cdn.dribbble.com/userupload/43118896/file/original-3103de4b349c70a911c5bf4b76beb6aa.png?resize=1600x1200)

### Reference F — Responsive analytics

![Responsive analytics dashboard](https://hoa.ninja/img.png)

### Reference G — Stripe-like payments table

![Stripe-style payments table reference](https://reetail.store/stripe.png)

### Reference H — Tablet inventory pattern

![Tablet inventory reference](https://talygen.com/Content/images/manufacturing-industries-img.jpg)

---

# 22. Visual Acceptance Criteria

The frontend is not considered visually complete merely because all routes render.

## A. Premium quality

- typography is consistent;
- spacing is consistent;
- component hierarchy is deliberate;
- icons are from one coherent family;
- shadows/borders are restrained;
- statuses are meaningful;
- no accidental UI clutter.

## B. Operational quality

- important tasks require minimal interaction;
- critical information is visible at a glance;
- tables scan efficiently;
- product ordering is fast;
- adjustments are understandable;
- payment evidence is clear;
- delivery state is obvious.

## C. Responsive quality

- desktop is not simply stacked desktop;
- tablet has explicit behavior;
- mobile has intentional navigation and action placement;
- no essential information disappears without a replacement affordance.

## D. Engineering quality

- reusable components;
- tokenized styling;
- clear state variants;
- no frontend-only authorization assumptions;
- no hard-coded business values where backend data is authoritative.

---

# 23. Portfolio-Quality Showcase Screens

The implementation should intentionally produce a strong set of portfolio screenshots without compromising product practicality.

Recommended showcase set:

1. Admin Dashboard — Desktop 1440px
2. Orders Workspace — Desktop 1440px
3. Order Detail + Adjustment — Desktop 1440px
4. Product Catalogue — Desktop 1440px
5. Salesman Home — Mobile 390px
6. Salesman Order Builder — Mobile 390px
7. Delivery Today — Mobile 390px
8. Inventory Dashboard — Tablet 820px
9. Accounting Overview — Desktop 1440px
10. Responsive multi-device composition — Desktop + Tablet + Mobile

The strongest portfolio narrative is:

> **One system, three operational roles, three responsive experiences, one coherent design system.**

---

# 24. Implementation Rules for Antigravity / Coding Agents

1. Read this document completely before creating or changing UI.
2. Do not create independent one-off styling when an existing token/component can serve the purpose.
3. Reuse existing components before introducing near-duplicates.
4. Every new page must define desktop, tablet and mobile behavior before implementation.
5. Build mobile transformations intentionally rather than relying on CSS wrapping alone.
6. Do not introduce a new color merely to make a component look interesting.
7. Do not introduce excessive gradients or decorative effects.
8. Never use a frontend control as the sole security enforcement mechanism.
9. Never allow the frontend to authoritatively set financial, pricing, tax, inventory or state fields.
10. Preserve original transaction values visually and semantically.
11. Use loading, empty, error, success and permission states.
12. Keep invoice rendering independent from dashboard UI.
13. Keep payment evidence retrieval private and authorization-aware.
14. Validate the UI at every required review viewport.
15. Compare the implementation to the supplied reference images for hierarchy and interaction quality, not for direct copying.
16. When a reference conflicts with this document's business workflow, this document wins.
17. When this document conflicts with the approved PRD, the PRD wins for business behavior; flag the conflict instead of silently deciding.
18. When security rules conflict with visual convenience, security wins.
19. Do not add speculative features merely because a reference includes them.
20. Keep the product aesthetically premium while preserving fast operational workflows.

---

# 25. Traceability to Other Specifications

## Technical Architecture

The frontend must align with:

- Laravel 13;
- React 19.2;
- TypeScript;
- Inertia 3;
- Tailwind CSS 4;
- shadcn/ui;
- PostgreSQL-backed authoritative data;
- Redis-supported secondary effects;
- S3 for private business documents.

## Security & Access

The frontend must respect:

- centralized authentication;
- role-aware navigation;
- permission-aware actions;
- resource-level scope;
- state-aware action availability;
- secure document access;
- audit visibility where permitted;
- privileged-action confirmations;
- no frontend-only security assumptions.

Relevant security traceability families include:

```text
SEC-AUTH-*
SEC-RBAC-*
SEC-ORD-*
SEC-INV-*
SEC-PAY-*
SEC-FIN-*
SEC-FILE-*
SEC-AUD-*
```

---

# 26. Deferred / Configurable Design Decisions

Do not silently invent these when implementing:

- final brand name;
- final brand accent color;
- exact logo;
- final organization-specific typography licensing decision;
- exact MFA experience for non-privileged roles;
- exact payment verification policy;
- exact refund/cancellation policies not finalized in PRD;
- exact customer-facing future portal behavior;
- exact accounting chart-of-accounts configuration;
- exact notification provider UI.

Use design tokens/placeholders so the final decision can be applied without rewriting the component architecture.

---

# 27. Final Design Lock

### Locked

**Overall:** Premium Modern SaaS  
**Business:** B2B Wholesale / Distribution ERP  
**Catalogue:** Premium ecommerce-inspired  
**Admin:** Data-rich command center  
**Salesman:** Fast mobile sales workspace  
**Delivery:** Task-focused logistics workspace  
**Desktop:** Elegant + information-dense  
**Tablet:** Adaptive operational workspace  
**Mobile:** Touch-first, intentionally redesigned  
**Color:** Neutral foundation + one strong brand accent + semantic status colors  
**Typography:** Modern readable sans-serif, Inter/Geist direction  
**Cards:** Moderate use, purposeful grouping  
**Borders:** Subtle  
**Shadows:** Very restrained  
**Icons:** One consistent outline family  
**Motion:** Subtle micro-interaction only  
**Dark mode:** Architecture-ready; V1 light-first  
**Accessibility:** Built into component contracts  
**Responsive:** 320px through 1920px review matrix  

### Quality bar

The target is **not** “a polished admin template.”

The target is:

> **A believable production SaaS product that looks expensive because its hierarchy, interaction quality, responsiveness and system discipline are excellent.**

---

# 28. Final Frontend Sign-Off Checklist

Before calling Document 04 implemented:

- [ ] Design system tokens exist.
- [ ] Global shell is stable across portals.
- [ ] Admin, Salesman and Delivery have distinct information hierarchy.
- [ ] Desktop, tablet and mobile behaviors are documented and implemented.
- [ ] Product catalogue is ecommerce-quality.
- [ ] Order creation is optimized for speed.
- [ ] Order detail preserves original ordered quantity.
- [ ] Allocation/adjustment is understandable.
- [ ] Payment evidence upload/preview is polished and private.
- [ ] Invoices contain no product images.
- [ ] Accounting surfaces use professional financial hierarchy.
- [ ] Tables transform intelligently on smaller screens.
- [ ] Loading/empty/error/permission states exist.
- [ ] Accessible focus and keyboard behavior exists.
- [ ] No frontend-only security decisions exist.
- [ ] Required review widths pass visual QA.
- [ ] Portfolio showcase screens look coherent as one product family.

---

## End of Document 04
