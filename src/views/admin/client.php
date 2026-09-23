<section class="page-head">
    <div><a class="back" href="/admin">← All clients</a><h1><?= htmlspecialchars(trim(($client['first_name'] ?? '').' '.($client['last_name'] ?? '')) ?: 'Client') ?></h1><p><?= htmlspecialchars($client['client_id'] ?? '') ?> · <?= htmlspecialchars($client['email'] ?? '') ?></p></div>
    <span class="badge"><?= !empty($client['status']) ? 'Active' : 'Disabled' ?></span>
</section>

<section class="detail-grid">
<article class="panel"><h2>Client information</h2>
<dl>
<dt>Case number</dt><dd><?= htmlspecialchars($client['case_number'] ?: '—') ?></dd>
<dt>Case status</dt><dd><?= htmlspecialchars($client['case_status'] ?: '—') ?></dd>
<dt>Phone</dt><dd><?= htmlspecialchars($client['phone'] ?: '—') ?></dd>
<dt>Date of birth</dt><dd><?= htmlspecialchars($client['dob'] ?: '—') ?></dd>
<dt>Address</dt><dd><?= htmlspecialchars(trim(($client['address'] ?? '').' '.($client['city'] ?? '').' '.($client['state'] ?? '').' '.($client['zip_code'] ?? '')) ?: '—') ?></dd>
<dt>Last login</dt><dd><?= htmlspecialchars($client['last_login_at'] ?: 'Never') ?></dd>
</dl></article>
<article class="panel"><h2>Internal notes</h2><p class="notes"><?= nl2br(htmlspecialchars($client['notes'] ?: 'No notes yet.')) ?></p></article>
</section>

<section class="panel"><h2>Services</h2><table><thead><tr><th>Service</th><th>Status</th><th>Added</th></tr></thead><tbody>
<?php foreach ($services as $row): ?><tr><td><?= htmlspecialchars($row['service_label']) ?></td><td><?= htmlspecialchars($row['current_status']) ?></td><td><?= htmlspecialchars($row['date_added']) ?></td></tr><?php endforeach; ?>
<?php if (!$services): ?><tr><td colspan="3" class="empty">No services.</td></tr><?php endif; ?>
</tbody></table></section>

<section class="detail-grid">
<article class="panel"><h2>Documents</h2><table><thead><tr><th>Document</th><th>Status</th></tr></thead><tbody>
<?php foreach ($documents as $row): ?><tr><td><?= htmlspecialchars($row['title'] ?? $row['document_type'] ?? ('Document #'.$row['id'])) ?></td><td><?= htmlspecialchars($row['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$documents): ?><tr><td colspan="2" class="empty">No documents.</td></tr><?php endif; ?>
</tbody></table></article>
<article class="panel"><h2>Payments</h2><table><thead><tr><th>Due</th><th>Amount</th><th>Status</th></tr></thead><tbody>
<?php foreach ($payments as $row): ?><tr><td><?= htmlspecialchars($row['due_date'] ?? '—') ?></td><td>$<?= number_format((float) $row['amount'], 2) ?></td><td><?= htmlspecialchars($row['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$payments): ?><tr><td colspan="3" class="empty">No payments.</td></tr><?php endif; ?>
</tbody></table></article>
</section>
