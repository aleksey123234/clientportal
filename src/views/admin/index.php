<section class="page-head">
    <div><p class="eyebrow">Operations</p><h1>Clients</h1></div>
    <form class="search" method="get" action="/admin">
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Client ID, name, email or case">
        <button type="submit">Search</button>
    </form>
</section>

<section class="stats">
    <article><span>All clients</span><strong><?= $stats['clients'] ?></strong></article>
    <article><span>Active</span><strong><?= $stats['active'] ?></strong></article>
    <article><span>Documents to review</span><strong><?= $stats['documents'] ?></strong></article>
    <article><span>Payments needing attention</span><strong><?= $stats['payments'] ?></strong></article>
</section>

<section class="panel">
<table>
<thead><tr><th>Client</th><th>Case</th><th>Status</th><th>Documents</th><th>Payments</th><th>Last login</th></tr></thead>
<tbody>
<?php foreach ($clients as $row): ?>
<tr>
    <td><a href="/admin/client?id=<?= (int) $row['id'] ?>"><strong><?= htmlspecialchars(trim($row['first_name'].' '.$row['last_name']) ?: 'Unnamed client') ?></strong></a><small><?= htmlspecialchars($row['client_id'] ?: $row['email']) ?></small></td>
    <td><?= htmlspecialchars($row['case_number'] ?: '—') ?></td>
    <td><span class="badge"><?= htmlspecialchars($row['case_status'] ?: ($row['status'] ? 'Active' : 'Disabled')) ?></span></td>
    <td><?= (int) $row['document_count'] ?></td>
    <td><?= (int) $row['payment_attention'] ?></td>
    <td><?= htmlspecialchars($row['last_login_at'] ?: 'Never') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$clients): ?><tr><td colspan="6" class="empty">No clients found.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
