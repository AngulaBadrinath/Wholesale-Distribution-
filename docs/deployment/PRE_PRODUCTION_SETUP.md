# Pre-Production Environment & AWS S3 Storage Setup Guide

**Document Version:** 1.0  
**Target Environment:** Pre-Production / Staging / Client Demo  
**Application:** Unique Distributors — Wholesale Distribution Management System  
**AWS Region:** `us-east-1`

---

## 1. AWS S3 Pre-Production Bucket Specification

### Bucket Identity
- **Bucket Name:** `unq-distributors-files-preprod-537124933486-us-east-1-an`
- **Region:** `us-east-1`
- **Bucket Purpose:** Dedicated private object storage for pre-production customer, payment, delivery, catalogue, and invoice evidence assets.

### Security Baseline (Non-Negotiable)
1. **Block Public Access:** `Enabled (All 4 settings ON)`
   - `BlockPublicAcls = True`
   - `IgnorePublicAcls = True`
   - `BlockPublicPolicy = True`
   - `RestrictPublicBuckets = True`
2. **Object Ownership:** `Bucket Owner Enforced` (ACLs disabled).
3. **Default Encryption:** `SSE-S3 (AES-256)` with S3 Bucket Key enabled.
4. **Versioning:** `Enabled` (prevents accidental object destruction and supports non-destructive audit compliance).
5. **CORS:** Disabled by default (all access is mediated via backend presigned URLs).
6. **Public Website Hosting:** Disabled.

---

## 2. Pre-Production Dedicated IAM Policy Template

This policy adheres strictly to least-privilege principles, granting read/write/delete access **only** to the pre-production bucket and denying access to production resources.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PreProdBucketLocationAndList",
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket",
                "s3:GetBucketLocation"
            ],
            "Resource": "arn:aws:s3:::unq-distributors-files-preprod-537124933486-us-east-1-an"
        },
        {
            "Sid": "PreProdObjectOperations",
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::unq-distributors-files-preprod-537124933486-us-east-1-an/*"
        }
    ]
}
```

---

## 3. OIDC Trust Relationship (Provider-Neutral Template)

When deploying to a container hosting provider or CI/CD platform (e.g. AWS ECS/EKS, GitHub Actions, GitLab CI, Render, Railway, or Fly.io), static AWS long-lived access keys **must be avoided**. Use OpenID Connect (OIDC) to assume a short-lived IAM role.

### OIDC Trust Policy Template
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Federated": "arn:aws:iam::537124933486:oidc-provider/<OIDC_PROVIDER_DOMAIN>"
            },
            "Action": "sts:AssumeRoleWithWebIdentity",
            "Condition": {
                "StringEquals": {
                    "<OIDC_PROVIDER_DOMAIN>:aud": "<OIDC_AUDIENCE>"
                },
                "StringLike": {
                    "<OIDC_PROVIDER_DOMAIN>:sub": "<OIDC_SUBJECT_FILTER>"
                }
            }
        }
    ]
}
```

---

## 4. Environment Variables Specification

The pre-production `.env` configuration file must supply the following parameters:

```env
APP_NAME="Unique Distributors (Staging)"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.uniquedistributors.test

# Database & Cache
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=wdms_staging
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Canonical S3 Filesystem Storage
FILESYSTEM_DISK=s3
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=unq-distributors-files-preprod-537124933486-us-east-1-an
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_THROW=false

# AWS Credentials (OIDC Web Identity Tokens or Provider Secrets)
# AWS_ACCESS_KEY_ID=... (Only if static IAM fallback is strictly required)
# AWS_SECRET_ACCESS_KEY=...
# AWS_ROLE_ARN=arn:aws:iam::537124933486:role/unique-distributors-preprod-app
# AWS_WEB_IDENTITY_TOKEN_FILE=/var/run/secrets/aws/token
```

---

## 5. Pre-Production Deployment & Resource Boundary Status

| Component | Status | Implementation Notes |
|---|---|---|
| **Primary/Dev S3 Bucket** | `Active & Verified` | `unq-distributors-files-537124933486-us-east-1-an` |
| **Storage Gateway (`StorageManagerService`)** | `Implemented` | Centralized disk resolution, key generation, signed URLs, MIME checks, compensating rollbacks |
| **Domain S3 Integration** | `Implemented` | Payments, Deliveries, Returns, Products, and Invoices all use canonical S3 paths |
| **Demo Data Seeding (`php artisan demo:seed`)** | `Implemented` | Generates complete synthetic catalogue and operational demo assets |
| **Storage Audit Tool (`php artisan storage:audit`)** | `Implemented` | Non-destructive diagnostic check for DB vs S3 consistency |
| **Pre-Production Bucket Creation** | `Deferred` | To be created in AWS account upon hosting provider selection |
| **Pre-Production IAM Role & OIDC** | `Prepared / Deferred` | IAM policy template finalized; exact OIDC claims await hosting platform decision |
