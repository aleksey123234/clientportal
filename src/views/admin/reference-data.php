<?php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$query = $_GET;
$pageUrl = static function (int $target) use ($query): string {
    $query['p'] = $target;
    return '/admin/reference-data?' . http_build_query($query);
};
$methodLabels = [
    'fingerprint_by_mail' => 'Fingerprint by mail', 'online_name_based' => 'Online (name based)',
    'mail_name_based' => 'Mail (name based)', 'livescan_in_person' => 'Livescan (in person)',
    'fax_or_email' => 'Fax or email', 'other' => 'Other',
];
?>
<section class="page-head reference-head">
    <div><p class="eyebrow">Administration</p><h1>Reference Data</h1><p>Courts, police services and background-check authorities.</p></div>
</section>

<nav class="directory-tabs" aria-label="Reference directories">
<?php foreach ($types as $key => $meta): ?>
    <a class="<?= $key === $type ? 'active' : '' ?>" href="/admin/reference-data?type=<?= $h($key) ?>">
        <?= $h($meta['label']) ?><strong><?= (int) ($summary[$key] ?? 0) ?></strong>
    </a>
<?php endforeach; ?>
</nav>

<form class="filter-panel" method="get" action="/admin/reference-data">
    <input type="hidden" name="type" value="<?= $h($type) ?>">
    <label class="filter-search">Search<input type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="Name, address, phone or email"></label>
    <label>Province / State<select name="region"><option value="0">All</option><?php foreach ($regions as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $filters['region'] === (int) $r['id'] ? 'selected' : '' ?>><?= $h($r['name']) ?> (<?= $h($r['code']) ?>)</option><?php endforeach; ?></select></label>
    <label>City<select name="city" <?= !$cities ? 'disabled' : '' ?>><option value="0">All</option><?php foreach ($cities as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['city'] === (int) $c['id'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option><?php endforeach; ?></select></label>
    <label>Covered city<select name="covered_city" <?= !$cities ? 'disabled' : '' ?>><option value="0">All</option><?php foreach ($cities as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['covered_city'] === (int) $c['id'] ? 'selected' : '' ?>><?= $h($c['name']) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status"><option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option><option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Archived</option></select></label>
    <label>Review<select name="review"><option value="all">All</option><option value="yes" <?= $filters['review'] === 'yes' ? 'selected' : '' ?>>Needs review</option><option value="no" <?= $filters['review'] === 'no' ? 'selected' : '' ?>>Verified</option></select></label>
    <label>Fee<select name="fee"><option value="all">All</option><option value="free" <?= $filters['fee'] === 'free' ? 'selected' : '' ?>>No fee</option><option value="paid" <?= $filters['fee'] === 'paid' ? 'selected' : '' ?>>Paid</option></select></label>
    <?php if ($type === 'sbc'): ?><label>Method<select name="method"><option value="">All</option><?php foreach ($methodLabels as $key => $label): ?><option value="<?= $h($key) ?>" <?= $filters['method'] === $key ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <label>Rows<select name="per_page"><?php foreach ([25,50,100,200] as $size): ?><option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></label>
    <div class="filter-actions"><button type="submit">Apply filters</button><a href="/admin/reference-data?type=<?= $h($type) ?>">Reset</a></div>
</form>

<div class="result-meta"><strong><?= number_format($total) ?></strong> records found · Page <?= $page ?> of <?= $pages ?></div>
<section class="panel reference-table">
<table>
<thead><tr><th>Name / address</th><th>Province / State</th><th>City</th><th>Covered cities</th><th>Contact</th><th>Fee / method</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
    <td><strong><?= $h($row['name']) ?></strong><small><?= $h($row['address_line'] ?: 'Address not provided') ?><?= $row['postal_code'] ? ' · '.$h($row['postal_code']) : '' ?></small></td>
    <td><?= $h($row['region_name']) ?><small><?= $h($row['region_code']) ?></small></td>
    <td><?= $h($row['city_name'] ?: '—') ?></td>
    <td class="covered-cell"><?= $h($row['covered_cities'] ?: '—') ?></td>
    <td><?= $h($row['phone'] ?: $row['contact_phone'] ?: '—') ?><small><?= $h($row['email'] ?: $row['contact_email'] ?: '') ?></small></td>
    <td><?= $h($row['no_fee'] ? 'No fee' : ($row['fee_text'] ?: '—')) ?><small><?= $h($row['sbc_methods'] ?: $row['payment_type'] ?: '') ?></small></td>
    <td><span class="badge <?= $row['is_active'] ? '' : 'badge-muted' ?>"><?= $row['is_active'] ? 'Active' : 'Archived' ?></span><?php if ($row['needs_review']): ?><span class="badge badge-warning">Review</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No records match these filters.</td></tr><?php endif; ?>
</tbody>
</table>
</section>

<?php if ($pages > 1): ?><nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?><a href="<?= $h($pageUrl($page - 1)) ?>">Previous</a><?php endif; ?>
    <span><?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= $h($pageUrl($page + 1)) ?>">Next</a><?php endif; ?>
</nav><?php endif; ?>

<?php if ($type === 'canada_court' && $sources): ?>
<section class="panel source-panel">
    <h2>Court directory sources</h2>
    <table>
        <thead><tr><th>Province / State</th><th>Source</th><th>Link</th></tr></thead>
        <tbody><?php foreach ($sources as $source): ?><tr>
            <td><?= $h($source['region_name'] ?: '—') ?></td>
            <td><?= $h($source['name']) ?></td>
            <td><a href="<?= $h($source['url']) ?>" target="_blank" rel="noopener noreferrer">Open source</a></td>
        </tr><?php endforeach; ?></tbody>
    </table>
</section>
<?php endif; ?>
