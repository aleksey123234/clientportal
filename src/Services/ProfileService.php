<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Profile data access + POST action handlers.
 */
final class ProfileService
{
    public static function getUser(PDO $db, int $id): array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public static function getProfile(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT * FROM client_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }

    public static function getPhones(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM client_phones WHERE user_id=? ORDER BY is_main DESC, id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getEmails(PDO $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM client_emails WHERE user_id=? ORDER BY is_main DESC, id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getAddress(PDO $db, int $userId, string $type): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM client_addresses WHERE user_id=? AND address_type=? LIMIT 1'
        );
        $stmt->execute([$userId, $type]);
        return $stmt->fetch() ?: [];
    }

    /**
     * @return array{saved: bool, error: ?string}
     */
    public static function handlePost(PDO $db, int $userId, array $post): array
    {
        $saved = false;
        $error = null;
        $action = $post['action'] ?? 'profile';

        switch ($action) {
            case 'profile':
                $firstName = trim($post['first_name'] ?? '');
                $middleName = trim($post['middle_name'] ?? '');
                $lastName = trim($post['last_name'] ?? '');
                $prefName = trim($post['preferred_name'] ?? '');
                $addContact = trim($post['additional_contact'] ?? '');
                $timezone = $post['timezone'] ?? '';
                $dobRaw = trim($post['dob_hidden'] ?? $post['dob'] ?? '');

                if ($dobRaw && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobRaw)) {
                    $parsed = \DateTime::createFromFormat('d/m/Y', $dobRaw)
                        ?: \DateTime::createFromFormat('d / m / Y', $dobRaw);
                    $dobRaw = $parsed ? $parsed->format('Y-m-d') : '';
                }

                $hasPrinterPost = $post['has_printer'] ?? '';
                $hasPrinter = ($hasPrinterPost !== '') ? (int) $hasPrinterPost : null;

                if ($dobRaw) {
                    $dob = \DateTime::createFromFormat('Y-m-d', $dobRaw);
                    if (!$dob) {
                        return ['saved' => false, 'error' => 'Invalid date of birth format.'];
                    }
                    $age = (int) $dob->diff(new \DateTime())->y;
                    if ($age < 18) {
                        return ['saved' => false, 'error' => 'You must be at least 18 years old.'];
                    }
                }

                $db->prepare(
                    'UPDATE users SET first_name=?, middle_name=?, last_name=?, updated_at=NOW() WHERE id=?'
                )->execute([$firstName, $middleName, $lastName, $userId]);

                self::upsertProfile($db, $userId, [
                    'preferred_name' => $prefName ?: null,
                    'additional_contact' => $addContact ?: null,
                    'timezone' => $timezone ?: null,
                    'dob' => $dobRaw ?: null,
                    'has_printer' => $hasPrinter,
                ]);

                $_SESSION['user_name'] = trim($firstName . ' ' . $middleName . ' ' . $lastName)
                    ?: ($_SESSION['user_email'] ?? 'Client');

                $saved = true;
                break;

            case 'add_phone':
                $number = trim($post['phone_number'] ?? '');
                $type = $post['phone_type'] ?? 'other';
                $extension = trim($post['phone_extension'] ?? '');
                $label = trim($post['phone_label'] ?? '');
                $isMain = !empty($post['phone_is_main']);

                $allowedTypes = ['alternative', 'home', 'cell', 'work', 'other'];
                if (!in_array($type, $allowedTypes, true)) {
                    $type = 'other';
                }

                $clean = preg_replace('/[^0-9+]/', '', $number);
                if (strlen($clean) < 7) {
                    return ['saved' => false, 'error' => 'Please enter a valid phone number.'];
                }

                if ($isMain) {
                    $db->prepare(
                        'UPDATE client_phones SET is_main=0, type="alternative"
                         WHERE user_id=? AND is_main=1'
                    )->execute([$userId]);
                }

                $db->prepare(
                    'INSERT INTO client_phones (user_id, type, number, extension, label, is_main)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$userId, $type, $number, $extension ?: null, $label ?: null, $isMain ? 1 : 0]);

                $saved = true;
                break;

            case 'toggle_phone_inactive':
                $phoneId = (int) ($post['phone_id'] ?? 0);
                $db->prepare(
                    'UPDATE client_phones SET is_old = IF(is_old=1, 0, 1) WHERE id=? AND user_id=? AND is_main=0'
                )->execute([$phoneId, $userId]);
                $saved = true;
                break;

            case 'add_email':
                $emailVal = trim($post['email_address'] ?? '');
                $isMain = !empty($post['email_is_main']);

                if (!filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
                    return ['saved' => false, 'error' => 'Please enter a valid email address.'];
                }

                if ($isMain) {
                    $db->prepare(
                        'UPDATE client_emails SET is_main=0, type="alternative"
                         WHERE user_id=? AND is_main=1'
                    )->execute([$userId]);
                    $db->prepare(
                        'UPDATE users SET email=?, updated_at=NOW() WHERE id=?'
                    )->execute([$emailVal, $userId]);
                    $_SESSION['user_email'] = $emailVal;
                }

                $db->prepare(
                    'INSERT INTO client_emails (user_id, type, email, is_main)
                     VALUES (?, ?, ?, ?)'
                )->execute([$userId, $isMain ? 'main' : 'alternative', $emailVal, $isMain ? 1 : 0]);

                $saved = true;
                break;

            case 'delete_email':
                $emailId = (int) ($post['email_id'] ?? 0);
                $db->prepare(
                    'DELETE FROM client_emails WHERE id=? AND user_id=? AND is_main=0'
                )->execute([$emailId, $userId]);
                $saved = true;
                break;

            case 'address_living':
            case 'address_mail':
                $addrType = $action === 'address_living' ? 'living' : 'mail';
                $country = trim($post['country'] ?? '');
                if ($country === 'Other') {
                    $country = trim($post['country_other'] ?? 'Other');
                }
                $isStandardCountry = in_array($country, ['Canada', 'United States'], true);

                $moveInRaw = trim($post['move_in_date_hidden'] ?? $post['move_in_date'] ?? '');
                if ($moveInRaw && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $moveInRaw)) {
                    $parsed = \DateTime::createFromFormat('d/m/Y', $moveInRaw);
                    $moveInRaw = $parsed ? $parsed->format('Y-m-d') : '';
                }

                self::upsertAddress($db, $userId, $addrType, [
                    'country' => $country,
                    'province_state' => $isStandardCountry ? trim($post['province_state'] ?? '') : '',
                    'city' => trim($post['city'] ?? ''),
                    'postal_code' => trim($post['postal_code'] ?? ''),
                    'street' => trim($post['street'] ?? ''),
                    'unit' => trim($post['unit'] ?? ''),
                    'move_in_date' => $moveInRaw ?: null,
                ]);
                $saved = true;
                break;
        }

        return ['saved' => $saved, 'error' => $error];
    }

    public static function upsertProfile(PDO $db, int $userId, array $fields): void
    {
        $stmt = $db->prepare('SELECT id FROM client_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $userId;
            $db->prepare("UPDATE client_profiles SET $sets, updated_at=NOW() WHERE user_id=?")
                ->execute($vals);
        } else {
            $cols = implode(', ', array_keys($fields));
            $phs = implode(', ', array_fill(0, count($fields), '?'));
            $vals = array_values($fields);
            array_unshift($vals, $userId);
            $db->prepare(
                "INSERT INTO client_profiles (user_id, $cols) VALUES (?, $phs)"
            )->execute($vals);
        }
    }

    public static function upsertAddress(PDO $db, int $userId, string $type, array $fields): void
    {
        $stmt = $db->prepare(
            'SELECT id FROM client_addresses WHERE user_id=? AND address_type=? LIMIT 1'
        );
        $stmt->execute([$userId, $type]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $userId;
            $vals[] = $type;
            $db->prepare(
                "UPDATE client_addresses SET $sets, updated_at=NOW() WHERE user_id=? AND address_type=?"
            )->execute($vals);
        } else {
            $cols = implode(', ', array_keys($fields));
            $phs = implode(', ', array_fill(0, count($fields), '?'));
            $vals = array_merge([$userId, $type], array_values($fields));
            $db->prepare(
                "INSERT INTO client_addresses (user_id, address_type, $cols) VALUES (?, ?, $phs)"
            )->execute($vals);
        }
    }
}
