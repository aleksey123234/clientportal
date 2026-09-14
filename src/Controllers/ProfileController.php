<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Csrf;
use App\Services\ProfileService;

/**
 * Profile page — thin dispatch + render; mutations in ProfileService.
 *
 * @see src/Services/ProfileService.php
 * @see src/views/profile/profile-page.php
 */
class ProfileController extends BaseController
{
    public function index(): void
    {
        Csrf::ensureToken();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $saved = false;
        $error = null;
        $db = $this->db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST[Csrf::FIELD] ?? null)) {
                $error = 'Invalid request. Please try again.';
            } else {
                $result = ProfileService::handlePost($db, $userId, $_POST);
                $saved = $result['saved'];
                $error = $result['error'];
            }
        }

        $user = ProfileService::getUser($db, $userId);
        $profile = ProfileService::getProfile($db, $userId);
        $phones = ProfileService::getPhones($db, $userId);
        $emails = ProfileService::getEmails($db, $userId);
        $addrLiving = ProfileService::getAddress($db, $userId, 'living');
        $addrMail = ProfileService::getAddress($db, $userId, 'mail');

        $pageTitle = 'Profile';
        $activePage = 'profile';

        $content = $this->render(
            __DIR__ . '/../views/profile/profile-page.php',
            compact(
                'user',
                'profile',
                'phones',
                'emails',
                'addrLiving',
                'addrMail',
                'saved',
                'error',
                'pageTitle',
                'activePage'
            )
        );

        require __DIR__ . '/../views/layouts/main.php';
    }
}
