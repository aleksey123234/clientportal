<?php
use App\Services\Csrf;
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$methodLabels = [
    'fingerprint_by_mail' => 'Fingerprint by mail', 'online_name_based' => 'Online — name based',
    'mail_name_based' => 'Mail — name based', 'livescan_in_person' => 'Livescan — in person',
    'fax_or_email' => 'Fax or email', 'other' => 'Other',
];
?>
<section class="page-head"><div><a class="back" href="/admin/reference-data?type=<?= $h($record['directory_type']) ?>">← Back to directory</a><h1>Edit reference record</h1><p><?= $h($record['name']) ?></p></div></section>
<?php if ($saved): ?><div class="admin-alert success">Changes saved.</div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert error"><?= $h($error) ?></div><?php endif; ?>
<form method="post" action="/admin/reference-data/edit?id=<?= (int) $record['id'] ?>" class="reference-edit-form">
<input type="hidden" name="<?= Csrf::FIELD ?>" value="<?= $h(Csrf::ensureToken()) ?>"><input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
<section class="panel form-section"><h2>Location</h2><div class="form-grid">
<label class="span-2">Name *<input required name="name" value="<?= $h($record['name']) ?>"></label>
<label>Province / State *<select required name="region_id"><?php foreach ($regions as $region): ?><option value="<?= (int) $region['id'] ?>" <?= (int)$record['region_id']===(int)$region['id']?'selected':'' ?>><?= $h($region['name']) ?> (<?= $h($region['code']) ?>)</option><?php endforeach; ?></select></label>
<label>City<input name="city" value="<?= $h($record['city_name']) ?>"></label><label class="span-2">Address<input name="address_line" value="<?= $h($record['address_line']) ?>"></label><label>Postal / ZIP code<input name="postal_code" value="<?= $h($record['postal_code']) ?>"></label>
<label class="span-3">Covered cities <small>Separate cities with semicolons</small><textarea name="covered_cities" rows="3"><?= $h($coveredCities) ?></textarea></label>
</div></section>
<section class="panel form-section"><h2>Contacts</h2><div class="form-grid">
<label>Phone<input name="phone" value="<?= $h($record['phone']) ?>"></label><label>Alternative phone<input name="alternate_phone" value="<?= $h($record['alternate_phone']) ?>"></label><label>Fax<input name="fax" value="<?= $h($record['fax']) ?>"></label>
<label>Email<input type="email" name="email" value="<?= $h($record['email']) ?>"></label><label class="span-2">Website<input type="url" name="website" value="<?= $h($record['website']) ?>"></label>
<label>Contact name<input name="contact_name" value="<?= $h($record['contact_name']) ?>"></label><label>Contact phone<input name="contact_phone" value="<?= $h($record['contact_phone']) ?>"></label><label>Contact email<input type="email" name="contact_email" value="<?= $h($record['contact_email']) ?>"></label>
<label>Manager name<input name="manager_name" value="<?= $h($record['manager_name']) ?>"></label><label class="span-2">Manager phone / email<input name="manager_contact" value="<?= $h($record['manager_contact']) ?>"></label>
</div></section>
<section class="panel form-section"><h2>Payment and instructions</h2><div class="form-grid">
<label>Payable to<input name="payable_to" value="<?= $h($record['payable_to']) ?>"></label><label>Payment type<input name="payment_type" value="<?= $h($record['payment_type']) ?>"></label><label>Mailing type<input name="mailing_type" value="<?= $h($record['mailing_type']) ?>"></label>
<label class="span-3">Fee<input name="fee_text" value="<?= $h($record['fee_text']) ?>"></label><label class="span-3">Additional information<textarea name="additional_information" rows="5"><?= $h($record['additional_information']) ?></textarea></label><label class="span-3">Special instructions<textarea name="special_instructions" rows="5"><?= $h($record['special_instructions']) ?></textarea></label>
</div></section>
<?php if ($record['directory_type'] === 'sbc'): ?><section class="panel form-section"><h2>SBC request methods</h2><div class="method-stack">
<?php foreach ($methodLabels as $methodType => $methodLabel): $m = $methods[$methodType] ?? []; ?><details class="method-card" <?= !empty($m['is_active']) ? 'open' : '' ?>><summary><?= $h($methodLabel) ?></summary><div class="form-grid">
<label class="check"><input type="checkbox" name="methods[<?= $h($methodType) ?>][is_active]" <?= !empty($m['is_active'])?'checked':'' ?>> Enabled</label><label class="check"><input type="checkbox" name="methods[<?= $h($methodType) ?>][is_primary]" <?= !empty($m['is_primary'])?'checked':'' ?>> Primary</label><label class="check"><input type="checkbox" name="methods[<?= $h($methodType) ?>][no_fee]" <?= !empty($m['no_fee'])?'checked':'' ?>> No fee</label>
<label>Processing time<input name="methods[<?= $h($methodType) ?>][processing_time]" value="<?= $h($m['processing_time'] ?? '') ?>"></label><label>Results return to<input name="methods[<?= $h($methodType) ?>][results_return_to]" value="<?= $h($m['results_return_to'] ?? '') ?>"></label><label>Fee<input name="methods[<?= $h($methodType) ?>][fee_text]" value="<?= $h($m['fee_text'] ?? '') ?>"></label>
<label>Payable to<input name="methods[<?= $h($methodType) ?>][payable_to]" value="<?= $h($m['payable_to'] ?? '') ?>"></label><label>Payment type<input name="methods[<?= $h($methodType) ?>][payment_type]" value="<?= $h($m['payment_type'] ?? '') ?>"></label><label>Link<input type="url" name="methods[<?= $h($methodType) ?>][website]" value="<?= $h($m['website'] ?? '') ?>"></label>
<label class="span-3">Required documents<textarea name="methods[<?= $h($methodType) ?>][required_documents]" rows="3"><?= $h($m['required_documents'] ?? '') ?></textarea></label><label class="span-3">FP/CF restrictions<textarea name="methods[<?= $h($methodType) ?>][restrictions]" rows="3"><?= $h($m['restrictions'] ?? '') ?></textarea></label><label class="span-3">Special instructions<textarea name="methods[<?= $h($methodType) ?>][special_instructions]" rows="4"><?= $h($m['special_instructions'] ?? '') ?></textarea></label>
</div></details><?php endforeach; ?></div></section><?php endif; ?>
<section class="panel form-section"><h2>Record status</h2><div class="status-options"><label class="check"><input type="checkbox" name="is_active" <?= $record['is_active']?'checked':'' ?>> Active</label><label class="check"><input type="checkbox" name="no_fee" <?= $record['no_fee']?'checked':'' ?>> No fee</label><label class="check"><input type="checkbox" name="needs_review" <?= $record['needs_review']?'checked':'' ?>> Needs review</label><label class="review-reason">Review reason<input name="review_reason" value="<?= $h($record['review_reason']) ?>"></label></div></section>
<div class="sticky-save"><a href="/admin/reference-data?type=<?= $h($record['directory_type']) ?>">Cancel</a><button type="submit">Save changes</button></div>
</form>
