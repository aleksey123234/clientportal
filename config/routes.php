<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CifController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentsController;
use App\Controllers\FaqController;
use App\Controllers\HealthController;
use App\Controllers\PasswordResetController;
use App\Controllers\PaymentsController;
use App\Controllers\ProfileController;
use App\Controllers\ReferenceDataController;
use App\Controllers\RegisterController;
use App\Controllers\ServicesController;
use App\Controllers\SettingsController;

/**
 * Route map: path => [Controller::class, method, requiresAuth, requiredRole]
 *
 * Add a new portal page with one line here. Front controller: public/index.php
 */
return [
    'login'              => [AuthController::class, 'login', false],
    'register'           => [RegisterController::class, 'index', false],
    'logout'             => [AuthController::class, 'logout', true],
    'forgot-password'    => [PasswordResetController::class, 'forgotPassword', false],
    'reset-password'     => [PasswordResetController::class, 'resetPassword', false],
    'dashboard'          => [DashboardController::class, 'index', true],
    'cif'                => [CifController::class, 'index', true],
    'documents'          => [DocumentsController::class, 'index', true],
    'documents/download' => [DocumentsController::class, 'index', true],
    'documents/email'    => [DocumentsController::class, 'index', true],
    'payments'           => [PaymentsController::class, 'dispatch', true],
    'services'           => [ServicesController::class, 'index', true],
    'faq'                => [FaqController::class, 'index', true],
    'health'             => [HealthController::class, 'index', false],
    'profile'            => [ProfileController::class, 'index', true],
    'settings'           => [SettingsController::class, 'index', true],
    'admin'              => [AdminController::class, 'index', true, 'admin'],
    'admin/client'       => [AdminController::class, 'client', true, 'admin'],
    'admin/reference-data' => [ReferenceDataController::class, 'index', true, 'admin'],
    'admin/reference-data/edit' => [ReferenceDataController::class, 'edit', true, 'admin'],
];
