<?php
/**
 * Global Application Configuration
 * 
 * Defines system constants, role hierarchies, status mappings, 
 * and operational constraints (uploads, pagination).
 */

define('APP_NAME',    'Nabaviyyah Charitable Trust');
define('APP_SHORT',   'NCT');
define('APP_VERSION', '1.0.0');

// ── Role Definitions (RBAC) ─────────────────────────────────────────────────
// These identifiers map to the 'role' column in the 'users' table.

define('ROLE_DATA_ENTRY',       'data_entry');       // 1.a: Field/Office Data Entry
define('ROLE_VILLAGE_INCHARGE', 'village_incharge'); // 1.b: Village-level Validation
define('ROLE_OVERALL_INCHARGE', 'overall_incharge'); // 1.c: Final Approval & Management
define('ROLE_VERIFICATION',     'verification');     // 1.d: External/Internal Verification
define('ROLE_SYSADMIN',         'sysadmin');         // Technical System Admin

/** Friendly labels for roles displayed in the UI */
define('ROLE_LABELS', [
    ROLE_DATA_ENTRY       => 'Data Entry (1.a)',
    ROLE_VILLAGE_INCHARGE => 'Village In-Charge (1.b)',
    ROLE_OVERALL_INCHARGE => 'Overall In-Charge (1.c)',
    ROLE_VERIFICATION     => 'Verification Personnel (1.d)',
    ROLE_SYSADMIN         => 'System Administrator',
]);

// ── Application Workflow Statuses ───────────────────────────────────────────
// Defines the lifecycle stages of a Zakath application.

define('STATUS_DRAFT',              'draft');              // Partially filled, not submitted
define('STATUS_PENDING_VALIDATION', 'pending_validation'); // Submitted by 1.a, waiting for 1.b
define('STATUS_SUBMITTED',          'submitted');          // Ready for overall review (or 1.b self-submit)
define('STATUS_UNDER_REVIEW',       'under_review');       // Management is currently evaluating
define('STATUS_APPROVED',           'approved');           // Formally approved, awaiting disbursement schedule
define('STATUS_REJECTED',           'rejected');           // Application denied
define('STATUS_DISBURSING',         'disbursing');         // Currently receiving scheduled payments
define('STATUS_COMPLETED',          'completed');          // All installments paid
define('STATUS_ON_HOLD',            'on_hold');            // Temporarily paused due to investigation

/** Human-readable status labels */
define('STATUS_LABELS', [
    STATUS_DRAFT              => 'Draft',
    STATUS_PENDING_VALIDATION => 'Pending Validation',
    STATUS_SUBMITTED          => 'Submitted',
    STATUS_UNDER_REVIEW       => 'Under Review',
    STATUS_APPROVED           => 'Approved',
    STATUS_REJECTED           => 'Rejected',
    STATUS_DISBURSING         => 'Disbursing',
    STATUS_COMPLETED          => 'Completed',
    STATUS_ON_HOLD            => 'On Hold',
]);

/** CSS classes for status badges */
define('STATUS_BADGE', [
    STATUS_DRAFT              => 'badge-gray',
    STATUS_PENDING_VALIDATION => 'badge-yellow',
    STATUS_SUBMITTED          => 'badge-blue',
    STATUS_UNDER_REVIEW       => 'badge-purple',
    STATUS_APPROVED           => 'badge-green',
    STATUS_REJECTED           => 'badge-red',
    STATUS_DISBURSING         => 'badge-orange',
    STATUS_COMPLETED          => 'badge-teal',
    STATUS_ON_HOLD            => 'badge-yellow',
]);

// ── Disbursement Frequency & Scheduling ─────────────────────────────────────

define('DISB_ONE_TIME',    'one_time');
define('DISB_WEEKLY',      'weekly');
define('DISB_MONTHLY',     'monthly');
define('DISB_QUARTERLY',   'quarterly');
define('DISB_HALF_YEARLY', 'half_yearly');
define('DISB_YEARLY',      'yearly');

/** UI labels for payment frequencies */
define('DISB_LABELS', [
    DISB_ONE_TIME    => 'One Time',
    DISB_WEEKLY      => 'Weekly',
    DISB_MONTHLY     => 'Monthly',
    DISB_QUARTERLY   => 'Quarterly',
    DISB_HALF_YEARLY => 'Half-Yearly',
    DISB_YEARLY      => 'Yearly',
]);

// ── Disbursement Payment Statuses ────────────────────────────────────────────

define('DISB_PENDING',    'pending');    // Scheduled, but not yet authorized
define('DISB_AUTHORIZED', 'authorized'); // Approved by management for release
define('DISB_RELEASED',   'released');   // Paid out to applicant
define('DISB_CANCELLED',  'cancelled');  // Voided payment

/** UI labels for installment statuses */
define('DISB_STATUS_LABELS', [
    DISB_PENDING    => 'Pending',
    DISB_AUTHORIZED => 'Authorized',
    DISB_RELEASED   => 'Released',
    DISB_CANCELLED  => 'Cancelled',
]);

/** CSS classes for installment badges */
define('DISB_STATUS_BADGE', [
    DISB_PENDING    => 'badge-yellow',
    DISB_AUTHORIZED => 'badge-blue',
    DISB_RELEASED   => 'badge-green',
    DISB_CANCELLED  => 'badge-red',
]);

// ── File Upload Constraints ──────────────────────────────────────────────────

define('UPLOAD_DIR',          __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE',     10 * 1024 * 1024); // 10MB limit
define('UPLOAD_ALLOWED_EXT',  ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif']);
define('UPLOAD_ALLOWED_MIME', ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff']);

// ── UI / UX Settings ─────────────────────────────────────────────────────────

define('PER_PAGE', 25); // Default pagination limit
