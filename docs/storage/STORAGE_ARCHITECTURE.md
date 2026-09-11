# Storage Architecture & Canonical Object Key Standard

**Document Version:** 1.0  
**Application:** Unique Distributors — Wholesale Distribution Management System  
**Primary Storage Adapter:** AWS S3 (League Flysystem S3 v3.35.3 via Laravel Filesystem)

---

## 1. Architectural Principles

1. **Private by Design:** S3 buckets maintain Block Public Access enabled and ACLs disabled (Bucket Owner Enforced). Direct public object URLs never exist.
2. **Server-Authorized Access:** All reads of private evidence, product images, and invoices require server authentication, permission verification, and resource scoping (salesman/driver scope). Access is granted solely via short-lived (15-minute) presigned URLs.
3. **Canonical Object Key Namespacing:** Object keys follow strict, collision-safe UUID-based paths without exposing PII (names, emails, phone numbers, tax IDs, or browser-provided filenames).
4. **Authoritative Binary Magic Byte Inspection:** Server-side fileinfo and byte-header validation enforces genuine JPEG/PNG/WebP format before persistence, explicitly rejecting SVGs, XML, and spoofed extensions.
5. **Compensating Rollback Isolation:** When a database transaction fails after an S3 upload, the staged S3 object is safely deleted to avoid orphaned cloud files.

---

## 2. Canonical Object Key Namespaces

| Domain | Entity | SubType | Canonical S3 Key Pattern | Access Control & TTL |
|---|---|---|---|---|
| **Payments** | Payment (`id`) | `evidence` | `payments/{payment_id}/evidence/{uuid}.jpg` | 15-min Presigned URL / Role & Salesman scoped |
| **Deliveries** | Delivery (`id`) | `signatures` | `deliveries/{delivery_id}/signatures/{uuid}.png` | 15-min Presigned URL / Driver & Salesman scoped |
| **Deliveries** | Delivery (`id`) | `pod` | `deliveries/{delivery_id}/pod/{uuid}.{ext}` | 15-min Presigned URL / Driver & Salesman scoped |
| **Returns** | ReturnRequest (`id`) | `evidence` | `returns/{return_request_id}/evidence/{uuid}.{ext}` | 15-min Presigned URL / Warehouse & Salesman scoped |
| **Products** | Product (`id`) | `images` | `products/{product_id}/images/{uuid}.{ext}` | 15-min Presigned URL / Authenticated catalogue |
| **Invoices** | Invoice (`id`) | `pdf` | `invoices/{year}/{month}/{invoice_number}.pdf` | Scoped stream via `InvoicePdfController` |

---

## 3. Storage Gateway Service (`StorageManagerService`)

All domain services interact with cloud storage exclusively through `App\Services\Storage\StorageManagerService`.

### Core Capabilities:
- `getDisk(?string $preferredDisk)`: Resolves default disk (`s3` or `local`).
- `generateKey(string $domain, int|string $entityId, string $subType, string $extension)`: Returns collision-safe canonical key.
- `put(string $path, mixed $contents, ?string $disk)`: Stores raw content into storage.
- `putFileAs(string $directory, UploadedFile $file, string $filename, ?string $disk)`: Stores uploaded file.
- `exists(string $path, ?string $disk)`: Verifies object presence.
- `get(string $path, ?string $disk)`: Retrieves raw bytes.
- `delete(string $path, ?string $disk)`: Deletes object.
- `temporaryUrl(string $path, int $minutes, array $options, ?string $disk)`: Generates presigned GET URL (or fallback).
- `compensateDelete(string $path, ?string $disk)`: Non-throwing cleanup on database transaction failure.
- `validateImageBinary(UploadedFile $file, ...)`: Validates image integrity and magic bytes.
- `validateJpegBinary(UploadedFile $file, ...)`: Validates strict `\xFF\xD8\xFF` JPEG binary header.

---

## 4. Test Environment Isolation

To ensure that automated PHPUnit feature and unit test suites never mutate live AWS S3 buckets:
- `phpunit.xml` explicitly defines `<env name="FILESYSTEM_DISK" value="local"/>`.
- Feature tests employ `Storage::fake('s3')` and `Storage::fake('local')`.
- Live S3 integration tests are isolated in `tests/Feature/Storage/S3IntegrationTest.php` and skip automatically if live credentials are not present.
