#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();
App\Support\AppTimezone::applyPhp();
require $root . '/src/config/database.php';

$defaults = [
    'canada_court' => $root . '/database/reference-import/canada-courts.md',
    'usa_court' => $root . '/database/reference-import/usa-courts.md',
    'sbc' => $root . '/database/reference-import/sbc-locations.md',
    'lprc' => $root . '/database/reference-import/lprc-locations.md',
];
$only = $argv[1] ?? '';
if ($only !== '' && !isset($defaults[$only])) {
    fwrite(STDERR, "Usage: php bin/import-reference-data.php [canada_court|usa_court|sbc|lprc]\n");
    exit(1);
}

$regionRows = $pdo->query('SELECT id, country_code, code, name FROM geographic_regions')->fetchAll(PDO::FETCH_ASSOC);
$regions = [];
foreach ($regionRows as $r) {
    $regions[$r['country_code']][$r['code']] = $r;
    $regions[$r['country_code']]['name:' . normalize($r['name'])] = $r;
}

$targets = $only === '' ? $defaults : [$only => $defaults[$only]];
$pdo->beginTransaction();
try {
    foreach ($targets as $type => $path) {
        if (!is_file($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }
        $result = importFile($pdo, $regions, $type, $path);
        echo sprintf("%s: %d imported, %d skipped, %d flagged for review\n", $type, $result['imported'], $result['skipped'], $result['review']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function importFile(PDO $pdo, array $regions, string $type, string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $stats = ['imported' => 0, 'skipped' => 0, 'review' => 0];
    foreach ($lines as $lineNo => $line) {
        if ($lineNo < 2 || strpos($line, '|') !== 0 || strpos($line, '---') === 2) { continue; }
        $columns = splitColumns($line);
        if (!$columns || trim(strip_tags(str_replace('<br>', ' ', $columns[0]))) === '') { continue; }
        $record = $type === 'sbc' ? parseSbc($columns, $regions) : parseLocation($columns, $type, $regions);
        if ($record === null) { $stats['skipped']++; continue; }
        $locationId = saveLocation($pdo, $record, $type);
        saveCoveredCities($pdo, $locationId, $record['region_id'], $record['covered_cities']);
        if ($type === 'sbc' && $record['method'] !== null) { saveSbcMethod($pdo, $locationId, $record); }
        $stats['imported']++;
        if ($record['needs_review']) { $stats['review']++; }
    }
    return $stats;
}

function splitColumns(string $line): array
{
    $line = preg_replace('/^\|\s*|\s*\|\s*$/', '', trim($line));
    return preg_split('/\s+\|\s+/', (string) $line) ?: [];
}

function parseLocation(array $c, string $type, array $regions): ?array
{
    $lines = htmlLines($c[0] ?? '');
    $name = extractName($c[0] ?? '', $lines);
    $country = $type === 'usa_court' ? 'US' : 'CA';
    [$region, $city, $postal, $address] = locateAddress($lines, $country, $regions);
    if ($region === null || $name === '') { return null; }
    $phoneCell = plain($c[3] ?? '');
    $phone = matchValue('/\(P\):\s*([^\n]+)/i', $phoneCell);
    $fax = matchValue('/\(F\):\s*([^\n]+)/i', $phoneCell);
    $fakePhone = $phone !== null && preg_match('/(?:000-000-0000|416-000-0000)/', $phone) === 1;
    $contact = plain($c[4] ?? '');
    $email = matchValue('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $contact . "\n" . plain($c[5] ?? ''));
    $contactPhone = matchValue('/(?:\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]\d{3}[\s.\-]\d{4}(?:\s*(?:x|ext\.?)[\s:]*\d+)?/i', $contact);
    $contactLines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $contact) ?: [])));
    $contactName = $contactLines[0] ?? '';
    if ($contactName === (string) $email || $contactName === (string) $contactPhone || strlen($contactName) > 120) {
        $contactName = '';
    }
    $covered = array_values(array_filter(array_map('cleanCity', preg_split('/\s*;\s*/', plain($c[1] ?? '')) ?: [])));
    $reviewReasons = [];
    if ($fakePhone) { $phone = null; $reviewReasons[] = 'Placeholder phone removed'; }
    if (strpos($name, '?') !== false || strpos($address, '?') !== false) { $reviewReasons[] = 'Question mark in legacy data'; }
    return [
        'region_id' => (int) $region['id'], 'name' => cleanText($name), 'city' => cleanCity($city),
        'address' => cleanText($address), 'postal' => cleanText($postal), 'phone' => $phone, 'fax' => $fax,
        'email' => $email, 'website' => extractUrl($c[0] ?? ''), 'contact_name' => cleanText($contactName),
        'contact_phone' => $contactPhone, 'contact_email' => $email,
        'fee_text' => cleanText(plain($c[2] ?? '')), 'additional' => cleanText($contact . "\n" . plain($c[5] ?? '')),
        'instructions' => cleanText(plain($c[6] ?? '')), 'covered_cities' => $covered,
        'no_fee' => 0, 'needs_review' => $reviewReasons !== [], 'review_reason' => implode('; ', $reviewReasons),
        'method' => null, 'required_documents' => null, 'results_return_to' => null,
    ];
}

function parseSbc(array $c, array $regions): ?array
{
    $html = $c[0] ?? '';
    $lines = htmlLines($html);
    $name = extractName($html, $lines);
    $state = null;
    foreach ($lines as $line) {
        $key = 'name:' . normalize(preg_replace('/^(Puerto Rico|Wisconsin)$/i', '$1', $line));
        if (isset($regions['US'][$key])) { $state = $regions['US'][$key]; break; }
    }
    if ($state === null) { return null; }
    $methodRaw = matchValue('/Complete SBC:\s*([^\n]+)/i', plain($html));
    $method = mapSbcMethod($methodRaw ?? 'Other');
    $details = plain($c[2] ?? '');
    $required = matchValue('/Required Docs:\s*(.*?)(?:\n(?:Results|Processing Time):|$)/is', $details);
    $results = matchValue('/(?:Results|Processing Time):\s*([^\n]+)/i', $details);
    $review = strpos($name, '?') !== false || ($methodRaw === null);
    return [
        'region_id' => (int) $state['id'], 'name' => cleanText($name ?: $state['name'] . ' State Authority'), 'city' => '',
        'address' => '', 'postal' => '', 'phone' => null, 'fax' => null, 'email' => null, 'website' => extractUrl($html),
        'contact_name' => '', 'contact_phone' => null, 'contact_email' => null, 'fee_text' => '', 'additional' => '', 'instructions' => '', 'covered_cities' => [],
        'no_fee' => 0, 'needs_review' => $review, 'review_reason' => $review ? 'Incomplete or uncertain legacy SBC data' : '',
        'method' => $method, 'required_documents' => cleanText($required), 'results_return_to' => cleanText($results),
    ];
}

function locateAddress(array $lines, string $country, array $regions): array
{
    $region = null; $city = ''; $postal = ''; $regionIndex = -1;
    foreach ($lines as $i => $line) {
        if ($country === 'CA' && preg_match('/^(.*?),\s*(AB|BC|MB|NB|NL|NF|N\.L\.|NS|NT|NU|ON|PE|PEI|QC|SK|YT)$/i', $line, $m)) {
            $code = strtoupper(str_replace('.', '', $m[2])); if ($code === 'PEI') { $code = 'PE'; } if ($code === 'NF') { $code = 'NL'; }
            $region = $regions['CA'][$code] ?? null; $city = $m[1]; $regionIndex = $i; break;
        }
        if ($country === 'US' && preg_match('/^(.*?),\s*([A-Z]{2})(?:\s|\?|,|$)/', $line, $m) && isset($regions['US'][$m[2]])) {
            $region = $regions['US'][$m[2]]; $city = $m[1]; $regionIndex = $i; break;
        }
    }
    if ($regionIndex >= 0 && isset($lines[$regionIndex + 1])) { $postal = $lines[$regionIndex + 1]; }
    $addressLines = $regionIndex > 1 ? array_slice($lines, 1, $regionIndex - 1) : [];
    return [$region, $city, $postal, implode(', ', $addressLines)];
}

function saveLocation(PDO $pdo, array $r, string $type): int
{
    $cityId = $r['city'] !== '' ? cityId($pdo, $r['region_id'], $r['city']) : null;
    $key = sha1(implode('|', [$r['region_id'], normalize($r['name']), normalize($r['address']), normalize($r['city'])]));
    $sql = 'INSERT INTO reference_locations (directory_type,region_id,city_id,name,address_line,postal_code,phone,fax,email,website,contact_name,contact_phone,contact_email,fee_text,additional_information,special_instructions,no_fee,needs_review,review_reason,source_key)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE region_id=VALUES(region_id),city_id=VALUES(city_id),name=VALUES(name),address_line=VALUES(address_line),postal_code=VALUES(postal_code),phone=VALUES(phone),fax=VALUES(fax),email=VALUES(email),website=VALUES(website),contact_name=VALUES(contact_name),contact_phone=VALUES(contact_phone),contact_email=VALUES(contact_email),fee_text=VALUES(fee_text),additional_information=VALUES(additional_information),special_instructions=VALUES(special_instructions),no_fee=VALUES(no_fee),needs_review=VALUES(needs_review),review_reason=VALUES(review_reason),updated_at=NOW(),id=LAST_INSERT_ID(id)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type,$r['region_id'],$cityId,$r['name'],nullIfEmpty($r['address']),nullIfEmpty($r['postal']),$r['phone'],$r['fax'],$r['email'],$r['website'],nullIfEmpty($r['contact_name']),$r['contact_phone'] ?? null,$r['contact_email'] ?? null,nullIfEmpty($r['fee_text']),nullIfEmpty($r['additional']),nullIfEmpty($r['instructions']),$r['no_fee'] ? 1 : 0,$r['needs_review'] ? 1 : 0,nullIfEmpty($r['review_reason']),$key]);
    return (int) $pdo->lastInsertId();
}

function saveCoveredCities(PDO $pdo, int $locationId, int $regionId, array $cities): void
{
    $pdo->prepare('DELETE FROM reference_location_cities WHERE location_id = ?')->execute([$locationId]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO reference_location_cities (location_id, city_id) VALUES (?, ?)');
    foreach ($cities as $city) { if ($city !== '') { $stmt->execute([$locationId, cityId($pdo, $regionId, $city)]); } }
}

function saveSbcMethod(PDO $pdo, int $locationId, array $r): void
{
    $stmt = $pdo->prepare('INSERT INTO sbc_request_methods (location_id,method_type,is_primary,required_documents,results_return_to,website,no_fee,fee_text,sort_order) VALUES (?,?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary),required_documents=VALUES(required_documents),results_return_to=VALUES(results_return_to),website=VALUES(website),no_fee=VALUES(no_fee),fee_text=VALUES(fee_text)');
    $stmt->execute([$locationId,$r['method'],1,nullIfEmpty($r['required_documents']),nullIfEmpty($r['results_return_to']),$r['website'],$r['no_fee'] ? 1 : 0,nullIfEmpty($r['fee_text'])]);
}

function cityId(PDO $pdo, int $regionId, string $name): int
{
    $normalized = normalize($name);
    $stmt = $pdo->prepare('INSERT INTO geographic_cities (region_id,name,normalized_name) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),name=VALUES(name),is_active=1');
    $stmt->execute([$regionId, cleanCity($name), $normalized]);
    return (int) $pdo->lastInsertId();
}

function htmlLines(string $html): array
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', (string) $text);
    $text = str_replace(['**', '\\@', '\\&'], ['', '@', '&'], (string) $text);
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return array_values(array_filter(array_map('trim', preg_split('/\R+/', $text) ?: []), static fn($v) => $v !== ''));
}

function plain(string $html): string { return implode("\n", htmlLines($html)); }
function extractName(string $html, array $lines): string { return preg_match('/\*\*(.*?)\*\*/s', $html, $m) ? plain($m[1]) : ($lines[0] ?? ''); }
function extractUrl(string $html): ?string { return preg_match('/https?:\/\/[^\s\]\)<]+/', $html, $m) ? rtrim(str_replace('\\', '', $m[0]), '.,') : null; }
function matchValue(string $pattern, string $text): ?string { return preg_match($pattern, $text, $m) ? trim($m[1] ?? $m[0]) : null; }
function normalize(string $value): string { $value = cleanText($value); return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function cleanText(?string $value): string { return trim((string) preg_replace('/\s+/u', ' ', (string) $value)); }
function cleanCity(?string $value): string { return trim(cleanText($value), " \t\n\r\0\x0B,.;"); }
function nullIfEmpty(?string $value): ?string { $value = cleanText($value); return $value === '' ? null : $value; }
function mapSbcMethod(string $value): string { $v = normalize($value); if (strpos($v, 'online') !== false) return 'online_name_based'; if (strpos($v, 'name') !== false) return 'mail_name_based'; if (strpos($v, 'fax') !== false || strpos($v, 'email') !== false) return 'fax_or_email'; if (strpos($v, 'live') !== false) return 'livescan_in_person'; if (strpos($v, 'fp') !== false || strpos($v, 'finger') !== false) return 'fingerprint_by_mail'; return 'other'; }
