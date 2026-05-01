<?php
define('APP_NAME',    'Nabaviyyah Charitable Trust');
define('APP_SHORT',   'NCT');
define('APP_VERSION', '1.0.0');

// ── Roles ────────────────────────────────────────────────────────────────────
define('ROLE_DATA_ENTRY',       'data_entry');
define('ROLE_VILLAGE_INCHARGE', 'village_incharge');
define('ROLE_OVERALL_INCHARGE', 'overall_incharge');
define('ROLE_VERIFICATION',     'verification');
define('ROLE_SYSADMIN',         'sysadmin');

define('ROLE_LABELS', [
    ROLE_DATA_ENTRY       => 'Data Entry (1.a)',
    ROLE_VILLAGE_INCHARGE => 'Village In-Charge (1.b)',
    ROLE_OVERALL_INCHARGE => 'Overall In-Charge (1.c)',
    ROLE_VERIFICATION     => 'Verification Personnel (1.d)',
    ROLE_SYSADMIN         => 'System Administrator',
]);

// ── Application Statuses ─────────────────────────────────────────────────────
define('STATUS_DRAFT',              'draft');
define('STATUS_PENDING_VALIDATION', 'pending_validation');
define('STATUS_SUBMITTED',          'submitted');
define('STATUS_UNDER_REVIEW',       'under_review');
define('STATUS_APPROVED',           'approved');
define('STATUS_REJECTED',           'rejected');
define('STATUS_DISBURSING',         'disbursing');
define('STATUS_COMPLETED',          'completed');

define('STATUS_LABELS', [
    STATUS_DRAFT              => 'Draft',
    STATUS_PENDING_VALIDATION => 'Pending Validation',
    STATUS_SUBMITTED          => 'Submitted',
    STATUS_UNDER_REVIEW       => 'Under Review',
    STATUS_APPROVED           => 'Approved',
    STATUS_REJECTED           => 'Rejected',
    STATUS_DISBURSING         => 'Disbursing',
    STATUS_COMPLETED          => 'Completed',
]);

define('STATUS_BADGE', [
    STATUS_DRAFT              => 'badge-gray',
    STATUS_PENDING_VALIDATION => 'badge-yellow',
    STATUS_SUBMITTED          => 'badge-blue',
    STATUS_UNDER_REVIEW       => 'badge-purple',
    STATUS_APPROVED           => 'badge-green',
    STATUS_REJECTED           => 'badge-red',
    STATUS_DISBURSING         => 'badge-orange',
    STATUS_COMPLETED          => 'badge-teal',
]);

// ── Disbursement Types ────────────────────────────────────────────────────────
define('DISB_ONE_TIME', 'one_time');
define('DISB_WEEKLY',   'weekly');
define('DISB_MONTHLY',  'monthly');
define('DISB_YEARLY',   'yearly');

define('DISB_LABELS', [
    DISB_ONE_TIME => 'One Time',
    DISB_WEEKLY   => 'Weekly',
    DISB_MONTHLY  => 'Monthly',
    DISB_YEARLY   => 'Yearly',
]);

// ── Disbursement Statuses ─────────────────────────────────────────────────────
define('DISB_PENDING',    'pending');
define('DISB_AUTHORIZED', 'authorized');
define('DISB_RELEASED',   'released');
define('DISB_CANCELLED',  'cancelled');

define('DISB_STATUS_LABELS', [
    DISB_PENDING    => 'Pending',
    DISB_AUTHORIZED => 'Authorized',
    DISB_RELEASED   => 'Released',
    DISB_CANCELLED  => 'Cancelled',
]);

define('DISB_STATUS_BADGE', [
    DISB_PENDING    => 'badge-yellow',
    DISB_AUTHORIZED => 'badge-blue',
    DISB_RELEASED   => 'badge-green',
    DISB_CANCELLED  => 'badge-red',
]);

// ── Upload Settings ───────────────────────────────────────────────────────────
define('UPLOAD_DIR',          __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE',     10 * 1024 * 1024);
define('UPLOAD_ALLOWED_EXT',  ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif']);
define('UPLOAD_ALLOWED_MIME', ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff']);

// ── Pagination ────────────────────────────────────────────────────────────────
define('PER_PAGE', 25);
