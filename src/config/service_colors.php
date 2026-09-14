<?php

/**
 * Shared service colour & label constants.
 *
 * Used across multiple controllers (Dashboard, Payments, Services, CIF)
 * so the colour palette stays consistent everywhere.
 *
 * Usage:
 *   require_once __DIR__ . '/../config/service_colors.php';
 *   $serviceColors = SERVICE_COLORS;
 *
 * @see src/controllers/DashboardController.php
 * @see src/controllers/PaymentsController.php
 * @see src/controllers/ServicesController.php
 * @see src/controllers/CifController.php
 */

/**
 * Background, text, border, and icon for each service type.
 * Keys match `service_costs.service_type` in the database.
 */
define('SERVICE_COLORS', [
    'pardon'         => ['bg' => '#f5f0dc', 'text' => '#6b5b3a', 'border' => '#c8b67e', 'icon' => 'bi-shield-check'],
    'criminal-rehab' => ['bg' => '#c9b9f0', 'text' => '#3d1a5c', 'border' => '#8b6fc7', 'icon' => 'bi-globe-americas'],
    'trp'            => ['bg' => '#b3f0f7', 'text' => '#0c4a50', 'border' => '#1db5c8', 'icon' => 'bi-clock-history'],
    'nexus'          => ['bg' => '#9acbfb', 'text' => '#0d3b7a', 'border' => '#1a70d7', 'icon' => 'bi-airplane'],
    'expunging'      => ['bg' => '#c4e5f7', 'text' => '#1a3e5c', 'border' => '#6bb8e8', 'icon' => 'bi-eraser'],
    'waiver'         => ['bg' => '#c4f0c4', 'text' => '#1a5c2a', 'border' => '#6bc86b', 'icon' => 'bi-globe-americas'],
    // WR darker than W — primary #1a5c2a
    'waiver-renewal' => ['bg' => '#1a5c2a', 'text' => '#ffffff', 'border' => '#0f3d1c', 'icon' => 'bi-globe-americas'],
]);

/**
 * Human-readable labels for each service type.
 * Keys match `service_costs.service_type` in the database.
 */
define('SERVICE_LABELS', [
    'pardon'         => 'Pardon',
    'criminal-rehab' => 'Criminal Rehab',
    'trp'            => 'TRP',
    'nexus'          => 'NEXUS',
    'expunging'      => 'Expungement',
    'waiver'         => 'US Waiver',
    'waiver-renewal' => 'Waiver Renewal',
]);

/**
 * Payment method labels — maps DB enum values to display strings.
 * Keys match `payments.method` column values.
 */
define('METHOD_LABELS', [
    'credit_card' => 'Credit Card',
    'debit'       => 'Debit',
    'e_transfer'  => 'e-Transfer',
    'cash'        => 'Cash',
    'cheque'      => 'Cheque',
]);
