/**
 * Deterministic Audit Manifest Generator
 * Generates tests/manifest/audit-manifest.json from the authoritative reconciliation table
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const sourcePath = path.join(__dirname, '..', 'docs', 'reports', 'CODEX-AUDIT-COVERAGE-RECONCILIATION-2026-09-12.md');
const outputPath = path.join(__dirname, '..', 'tests', 'manifest', 'audit-manifest.json');

const content = fs.readFileSync(sourcePath, 'utf8');
const lines = content.split('\n');

const manifest = [];

function mapDomain(section) {
  const s = section.toUpperCase();
  if (s.includes('ZERO-FALSE-PASS') || s.includes('ENVIRONMENT') || s.includes('ROOT-CAUSE') || s.includes('METRICS')) return 'META_GOVERNANCE';
  if (s.includes('GLOBAL UI') || s.includes('NAVIGATION')) return 'SHELL_NAV';
  if (s.includes('RESPONSIVE') || s.includes('VISUAL')) return 'RESPONSIVE_VISUAL';
  if (s.includes('ACCESSIBILITY') || s.includes('KEYBOARD')) return 'ACCESSIBILITY';
  if (s.includes('AUTHENTICATION') || s.includes('SECURITY') || s.includes('HARDENING')) return 'SECURITY_AUTH';
  if (s.includes('CUSTOMER')) return 'CUSTOMERS';
  if (s.includes('PRODUCT') || s.includes('PRICING') || s.includes('TAX')) return 'PRICING_TAX_CATALOG';
  if (s.includes('SALESMAN') || s.includes('ORDER CREATION')) return 'ORDERS_SALESMAN';
  if (s.includes('ORDER APPROVAL') || s.includes('ADJUSTMENT')) return 'ORDERS_ADJUSTMENTS';
  if (s.includes('INVOICING') || s.includes('DOCUMENT')) return 'INVOICES_DOCUMENTS';
  if (s.includes('PAYMENTS') || s.includes('RECEIPT')) return 'PAYMENTS';
  if (s.includes('ACCOUNTS RECEIVABLE')) return 'ACCOUNTS_RECEIVABLE';
  if (s.includes('ACCOUNTS PAYABLE')) return 'ACCOUNTS_PAYABLE';
  if (s.includes('WAREHOUSE') || s.includes('PHYSICAL INVENTORY')) return 'INVENTORY_WAREHOUSE';
  if (s.includes('DELIVERY')) return 'DELIVERY';
  if (s.includes('RETURNS')) return 'RETURNS';
  if (s.includes('CREDITS') || s.includes('REFUNDS')) return 'CREDITS_REFUNDS';
  if (s.includes('ACCOUNTING') || s.includes('GENERAL LEDGER')) return 'ACCOUNTING_GL';
  if (s.includes('FINANCIAL REPORTING') || s.includes('REPORTING')) return 'REPORTING';
  if (s.includes('NOTIFICATIONS')) return 'NOTIFICATIONS';
  if (s.includes('AUDIT TRAILS')) return 'AUDIT_LOGS';
  if (s.includes('DATA INTEGRITY') || s.includes('STATE MACHINE')) return 'STATE_INTEGRITY';
  if (s.includes('CONCURRENCY')) return 'CONCURRENCY';
  if (s.includes('GOLDEN SCENARIO')) return 'FINANCIAL_GOLDEN';
  if (s.includes('STORAGE')) return 'STORAGE_SECURITY';
  if (s.includes('EDGE CASES')) return 'EDGE_CASES';
  if (s.includes('HYGIENE')) return 'CODE_HYGIENE';
  return 'GENERAL_DOMAIN';
}

function mapTestType(status, section, desc) {
  const s = section.toUpperCase();
  const d = desc.toUpperCase();
  if (s.includes('RESPONSIVE') || s.includes('VISUAL')) return 'PLAYWRIGHT_VISUAL_RESPONSIVE';
  if (s.includes('ACCESSIBILITY') || s.includes('KEYBOARD')) return 'PLAYWRIGHT_A11Y';
  if (s.includes('GOLDEN SCENARIO')) return 'COMPOSITE_FINANCIAL_E2E';
  if (s.includes('ACCOUNTING') || s.includes('GENERAL LEDGER') || d.includes('DEBIT') || d.includes('CREDIT') || d.includes('EQUATION') || d.includes('BALANCE')) return 'POSTGRES_DB_INVARIANT';
  if (d.includes('PRICING') || d.includes('TAX') || d.includes('CALCULATION') || d.includes('FORMULA') || d.includes('ROUNDING')) return 'DOMAIN_UNIT_PHP';
  if (d.includes('IDOR') || d.includes('ROLE') || d.includes('403') || d.includes('401') || d.includes('UNAUTHORIZED') || d.includes('TOKEN') || d.includes('MIDDLEWARE')) return 'HTTP_API_SECURITY';
  if (d.includes('CLICK') || d.includes('MODAL') || d.includes('FORM') || d.includes('INPUT') || d.includes('SHELL') || d.includes('PAGE') || d.includes('NAVIGATION')) return 'PLAYWRIGHT_E2E';
  if (status === '[-]') return 'META_AUDIT_RULE';
  return 'PLAYWRIGHT_E2E';
}

function mapRoles(section, desc) {
  const text = (section + ' ' + desc).toLowerCase();
  const roles = [];
  if (text.includes('admin')) roles.push('admin');
  if (text.includes('salesman')) roles.push('salesman');
  if (text.includes('accountant')) roles.push('accountant');
  if (text.includes('warehouse')) roles.push('warehouse_manager');
  if (text.includes('delivery')) roles.push('delivery_partner');
  if (roles.length === 0) roles.push('all_authenticated');
  return roles;
}

let tableStarted = false;
for (const line of lines) {
  if (line.startsWith('| Checklist ID | Section |')) {
    tableStarted = true;
    continue;
  }
  if (tableStarted && line.startsWith('|---|')) {
    continue;
  }
  if (tableStarted && line.startsWith('---')) {
    break;
  }
  if (tableStarted && line.startsWith('| CHK-')) {
    const parts = line.split('|').map(p => p.trim());
    // parts[0] is '', parts[1] is CHK-xxx, parts[2] is Section, parts[3] is Desc, parts[4] is Test Spec, parts[5] is Test Case, parts[6] is Evidence, parts[7] is Status, parts[8] is Reason
    const checklistId = parts[1];
    const section = parts[2];
    const description = parts[3];
    const testSpec = parts[4] !== 'None' ? parts[4] : null;
    const testCase = parts[5] !== 'None' ? parts[5] : null;
    const evidence = parts[6] !== 'None' ? parts[6].split(',').map(e => e.trim()) : [];
    const rawStatus = parts[7];
    const reason = parts[8];

    let normalizedStatus = 'NOT_TESTED';
    if (rawStatus === '[x]') normalizedStatus = 'PASS';
    else if (rawStatus === '[~]') normalizedStatus = 'PARTIAL';
    else if (rawStatus === '[-]') normalizedStatus = 'NOT_APPLICABLE';
    else if (rawStatus === '[!]') normalizedStatus = 'FAIL';
    else if (rawStatus === '[B]' || rawStatus === '[?]') normalizedStatus = 'BLOCKED';
    else if (rawStatus === '[ ]') normalizedStatus = 'NOT_TESTED';

    const domain = mapDomain(section);
    const testType = mapTestType(rawStatus, section, description);
    const roles = mapRoles(section, description);

    manifest.push({
      checklistId,
      section,
      domain,
      description,
      testType,
      testFile: testSpec,
      testCase,
      sourceOfTruth: 'docs/FULL_EXHAUSTIVE_REAL_BROWSER_AUDIT_ZERO_FALSE_PASSES.md',
      assertions: [description],
      evidence,
      roles,
      status: normalizedStatus,
      notes: reason
    });
  }
}

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify(manifest, null, 2), 'utf8');

console.log(`Generated manifest with ${manifest.length} items at ${outputPath}`);
const statusCounts = manifest.reduce((acc, item) => {
  acc[item.status] = (acc[item.status] || 0) + 1;
  return acc;
}, {});
console.log('Status counts:', statusCounts);
