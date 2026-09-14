<?php

use App\Support\Asset;
use App\Support\Html;

/**
 * FAQ page view
 *
 * Features:
 *  - Keyword search across questions & answers
 *  - Service-section filter pills (multi-select)
 *  - Accordion Q&A grouped by section
 *  - Auto-select client's services from session (future: from DB)
 */

?>
<link rel="stylesheet" href="<?= Asset::url('/css/faq.css') ?>">
<?php

/* ── FAQ Data ─────────────────────────────────────────────────
   Each section has an id, label, icon and array of Q&A pairs.
   ─────────────────────────────────────────────────────────── */
$sections = [
    [
        'id'    => 'general',
        'label' => 'General',
        'icon'  => 'bi-info-circle',
        'color' => 'primary',
        'faqs'  => [
            [
                'q' => 'How do I update my personal information?',
                'a' => 'Navigate to the <strong>Profile</strong> page from the sidebar. You can edit your name, date of birth, timezone, phone numbers, emails, and addresses there. Changes are saved immediately when you click <em>Save Profile</em>.',
            ],
            [
                'q' => 'How do I change my password?',
                'a' => 'Go to <strong>Settings → Password</strong> tab. Enter your current password and the new password twice. The new password must be at least 8 characters long.',
            ],
            [
                'q' => 'How do I change my primary email address?',
                'a' => 'On the <strong>Profile</strong> page, click <em>Add Email</em> and check "Set as new primary email". Your previous primary email will be kept as an alternative.',
            ],
            [
                'q' => 'How do I upload documents?',
                'a' => 'Go to the <strong>Documents</strong> page from the sidebar. Click the <em>Upload</em> button and select the file from your computer. Accepted formats include PDF, JPG, PNG and common document types. Maximum file size is 10 MB.',
            ],
            [
                'q' => 'Who is my attorney or case manager?',
                'a' => 'Your assigned attorney is listed in the <strong>CIF (Client Information Form)</strong> section. If you do not see this information, please contact our office.',
            ],
            [
                'q' => 'How do I contact support?',
                'a' => 'You can email us at the address provided in your welcome email, or call our office during business hours (Monday–Friday, 9 AM – 5 PM EST). For urgent matters, please call directly.',
            ],
            [
                'q' => 'Can I switch the portal to dark mode?',
                'a' => 'Yes! Go to <strong>Settings → Appearance</strong> tab and click the <em>Dark</em> theme card. The change takes effect immediately.',
            ],
            [
                'q' => 'Can I move the sidebar to the right side?',
                'a' => 'Yes. Go to <strong>Settings → Appearance</strong> and choose "Right" under Sidebar position. The layout flips instantly.',
            ],
        ],
    ],
    [
        'id'    => 'pardon',
        'label' => 'Pardon',
        'icon'  => 'bi-shield-check',
        'color' => 'success',
        'rgb'   => '#b8860b',
        'rgbText' => '#fff',
        'rgbTextInactive' => '#8b6914',
        'faqs'  => [
            [
                'q' => 'What is a Pardon (Record Suspension)?',
                'a' => 'A Pardon, officially called a <strong>Record Suspension</strong> in Canada, allows people who were convicted of a criminal offence, but have completed their sentence and demonstrated they are law-abiding citizens, to have their criminal record kept separate and apart from other criminal records.',
            ],
            [
                'q' => 'Am I eligible for a Pardon?',
                'a' => 'Eligibility depends on the type of offence and the time that has passed since you completed all sentences, including probation and fines. For summary offences, you must wait <strong>5 years</strong>; for indictable offences, <strong>10 years</strong>. Our team will assess your specific situation.',
            ],
            [
                'q' => 'How long does the Pardon process take?',
                'a' => 'The total process typically takes <strong>12 to 24 months</strong>, depending on the complexity of your case and processing times at the Parole Board of Canada. We begin preparing your application immediately upon engagement.',
            ],
            [
                'q' => 'What documents do I need for a Pardon application?',
                'a' => 'You will need: criminal record (RCMP), court information for each conviction, local police records checks, military conduct sheet (if applicable), measurable benefit / sustained rehabilitation statement, and identity documents. We guide you through obtaining each item.',
            ],
            [
                'q' => 'Will a Pardon erase my record completely?',
                'a' => 'A Record Suspension does not erase the record — it sets it apart so it does not show on standard criminal record checks. However, it can be revoked if you commit a new offence.',
            ],
        ],
    ],
    [
        'id'    => 'criminal-rehab',
        'label' => 'Criminal Rehabilitation',
        'icon'  => 'bi-globe-americas',
        'color' => 'info',
        'rgb'   => 'rgb(163,141,229)',
        'rgbText' => '#fff',
        'rgbTextInactive' => 'rgb(103,81,169)',
        'faqs'  => [
            [
                'q' => 'What is Criminal Rehabilitation?',
                'a' => '<strong>Criminal Rehabilitation</strong> is a Canadian immigration process that allows foreign nationals who were convicted of a crime outside Canada (or in Canada) to overcome criminal inadmissibility and enter or remain in Canada permanently.',
            ],
            [
                'q' => 'When can I apply for Criminal Rehabilitation?',
                'a' => 'You may apply once at least <strong>5 years</strong> have passed since the completion of your sentence (including probation, fines, and any other conditions).',
            ],
            [
                'q' => 'How long does the Criminal Rehabilitation process take?',
                'a' => 'Processing times vary, but typically range from <strong>6 to 12 months</strong> after submission. Complex cases or those requiring additional background checks may take longer.',
            ],
            [
                'q' => 'Is Criminal Rehabilitation permanent?',
                'a' => 'Yes. Once approved, Criminal Rehabilitation permanently resolves your inadmissibility for the offence(s) covered. You will not need to reapply unless you are convicted of a new offence.',
            ],
            [
                'q' => 'What is the difference between Criminal Rehabilitation and a TRP?',
                'a' => 'Criminal Rehabilitation is a <strong>permanent</strong> solution. A Temporary Resident Permit (TRP) is a <strong>temporary</strong> measure that allows entry for a specific period and purpose. We help determine which option is best for your situation.',
            ],
        ],
    ],
    [
        'id'    => 'waiver',
        'label' => 'Waiver',
        'icon'  => 'bi-globe-americas',
        'color' => 'warning',
        'rgb'   => 'lightgreen',
        'rgbText' => '#1a5c2a',
        'faqs'  => [
            [
                'q' => 'What is a US Entry Waiver (I-212 / I-192)?',
                'a' => 'A <strong>US Entry Waiver</strong> (officially known as an Application for Permission to Reapply for Admission) allows individuals who are inadmissible to the United States due to a criminal record or immigration violation to legally enter the country.',
            ],
            [
                'q' => 'How long does a US Entry Waiver last?',
                'a' => 'A waiver is typically granted for <strong>1 to 5 years</strong>, depending on the nature of the offence and the applicant\'s history. You must reapply before it expires if you wish to continue entering the US.',
            ],
            [
                'q' => 'What documents are needed for a Waiver application?',
                'a' => 'Common requirements include: police clearance certificates, court records, proof of rehabilitation, employment letters, personal statement, reference letters, and identity documents. Our team provides a detailed checklist tailored to your case.',
            ],
            [
                'q' => 'How long does the Waiver process take?',
                'a' => 'From filing to decision, expect approximately <strong>6 to 12 months</strong>. We recommend applying well in advance of any planned travel dates.',
            ],
            [
                'q' => 'Can I be denied a Waiver?',
                'a' => 'Yes. Waivers are discretionary. However, a well-prepared application that demonstrates rehabilitation significantly increases approval chances. If denied, you may reapply with a stronger case.',
            ],
        ],
    ],
    [
        'id'    => 'trp',
        'label' => 'Temporary Resident Permit',
        'icon'  => 'bi-clock-history',
        'color' => 'secondary',
        'rgb'   => 'rgb(39,211,230)',
        'rgbText' => '#0c4a50',
        'faqs'  => [
            [
                'q' => 'What is a Temporary Resident Permit (TRP)?',
                'a' => 'A <strong>TRP</strong> is a Canadian immigration document that allows someone who is criminally inadmissible to enter or stay in Canada temporarily. It is issued when there is a compelling reason for the visit.',
            ],
            [
                'q' => 'When should I apply for a TRP instead of Criminal Rehabilitation?',
                'a' => 'A TRP is best if you need to travel to Canada <strong>urgently</strong> and have not yet become eligible for Criminal Rehabilitation (less than 5 years since sentence completion), or if you have a time-sensitive reason such as a business trip, family emergency, or court appearance.',
            ],
            [
                'q' => 'How long is a TRP valid?',
                'a' => 'A TRP can be valid for up to <strong>3 years</strong>, depending on the officer\'s assessment. It may also be issued for a single entry. The validity is at the discretion of the immigration officer.',
            ],
            [
                'q' => 'Can a TRP be renewed?',
                'a' => 'Yes, you can apply for a new TRP before or after the current one expires. However, approval is never guaranteed — each application is assessed independently.',
            ],
            [
                'q' => 'What fees are associated with a TRP?',
                'a' => 'The government processing fee for a TRP is <strong>$200 CAD</strong>. Our service fees depend on case complexity and are discussed during your consultation.',
            ],
        ],
    ],
    [
        'id'    => 'expunging',
        'label' => 'Expunging',
        'icon'  => 'bi-eraser',
        'color' => 'danger',
        'rgb'   => 'lightskyblue',
        'rgbText' => '#1a3e5c',
        'faqs'  => [
            [
                'q' => 'What does expungement mean?',
                'a' => '<strong>Expungement</strong> is the legal process of having a criminal record permanently erased or sealed. In Canada, the Expungement Act (2018) allows expungement of convictions for offences that never should have been offences (e.g., certain historical offences related to consensual sexual activity).',
            ],
            [
                'q' => 'What is the difference between expungement and a Pardon?',
                'a' => 'A Pardon (Record Suspension) <em>sets aside</em> a record — it still technically exists but is not disclosed on standard checks. Expungement <em>destroys</em> the record entirely, as if the conviction never happened.',
            ],
            [
                'q' => 'Am I eligible for expungement?',
                'a' => 'Expungement in Canada is currently limited to historically unjust convictions (under the Expungement of Historically Unjust Convictions Act). In the US, eligibility varies by state. We will assess your situation and advise which route applies.',
            ],
            [
                'q' => 'How long does expungement take?',
                'a' => 'Processing time varies by jurisdiction. In Canada, expect <strong>6 to 12 months</strong>. In US states, it can range from a few weeks to over a year depending on the state and court backlog.',
            ],
        ],
    ],
    [
        'id'    => 'waiver-renewal',
        'label' => 'Waiver Renewal',
        'icon'  => 'bi-globe-americas',
        'color' => 'success',
        'rgb'   => '#1a6b2a',
        'rgbText' => '#fff',
        'rgbTextInactive' => '#1a6b2a',
        'faqs'  => [
            [
                'q' => 'When should I start my Waiver Renewal?',
                'a' => 'We recommend starting the renewal process <strong>8 to 12 months</strong> before your current waiver expires. This allows enough time for processing without a gap in coverage.',
            ],
            [
                'q' => 'Is a Waiver Renewal easier than the first application?',
                'a' => 'Generally yes, especially if your circumstances have remained stable. A strong renewal application shows continued rehabilitation, steady employment, and no new offences.',
            ],
            [
                'q' => 'What if my Waiver has already expired?',
                'a' => 'You can still apply for a new waiver. However, you should not attempt to enter the US until the new waiver is approved. Attempting entry without a valid waiver can result in further complications.',
            ],
            [
                'q' => 'Can my Waiver validity be extended on renewal?',
                'a' => 'Yes. If your track record is clean and your documentation is thorough, officers may grant a longer validity period (e.g., from 1 year to 3 or 5 years) on renewal.',
            ],
        ],
    ],
    [
        'id'    => 'nexus',
        'label' => 'NEXUS',
        'icon'  => 'bi-airplane',
        'color' => 'success',
        'rgb'   => 'rgb(46,142,245)',
        'rgbText' => '#fff',
        'rgbTextInactive' => 'rgb(26,102,205)',
        'faqs'  => [
            [
                'q' => 'What is the NEXUS program?',
                'a' => '<strong>NEXUS</strong> is a joint Canada-US trusted traveler program that allows pre-approved, low-risk travelers to use expedited processing when entering Canada or the United States. Members get dedicated lanes at border crossings and airports.',
            ],
            [
                'q' => 'Can I apply for NEXUS if I have a criminal record?',
                'a' => 'Generally no — a criminal record makes you ineligible for NEXUS. However, if you have obtained a Pardon (Record Suspension) or US Entry Waiver, you may become eligible. We can advise based on your specific record.',
            ],
            [
                'q' => 'How do I apply for NEXUS?',
                'a' => 'You apply online through the Trusted Traveller Programs (TTP) website. The process involves an application form, background check by both CBSA and CBP, and an in-person interview at a NEXUS enrolment centre.',
            ],
            [
                'q' => 'How long does NEXUS membership last?',
                'a' => 'A NEXUS card is valid for <strong>5 years</strong>. You can renew online before it expires. The renewal fee is <strong>$50 USD</strong>.',
            ],
            [
                'q' => 'What are the benefits of NEXUS?',
                'a' => 'Benefits include: dedicated lanes at land border crossings, expedited airport security (TSA PreCheck in the US, priority screening in Canada), self-serve kiosks at major airports, dedicated phone-in reporting for marine entry, and it includes Global Entry benefits for the US.',
            ],
        ],
    ],
    [
        'id'    => 'payments',
        'label' => 'Payments',
        'icon'  => 'bi-credit-card',
        'color' => 'warning',
        'faqs'  => [
            [
                'q' => 'What payment methods do you accept?',
                'a' => 'We accept payments via credit card (Visa, Mastercard, American Express), debit, e-Transfer (Interac), bank wire, and certified cheque. Payment options are detailed in your engagement agreement.',
            ],
            [
                'q' => 'Can I set up a payment plan?',
                'a' => 'Yes. We offer flexible payment plans on most services. The terms are discussed during your initial consultation and formalized in your service agreement. Payments can be spread over the duration of your case.',
            ],
            [
                'q' => 'Where can I see my payment history?',
                'a' => 'Navigate to the <strong>Payments</strong> page from the sidebar. All completed and pending payments are listed with dates, amounts, and status indicators.',
            ],
            [
                'q' => 'What happens if I miss a payment?',
                'a' => 'If you miss a scheduled payment, you will receive a reminder email 3 days prior and on the due date. If payment is not received within 7 days, your case manager will reach out to discuss options. We understand life happens and will work with you.',
            ],
            [
                'q' => 'Are government filing fees included in my service fee?',
                'a' => 'Government filing fees are typically <strong>not included</strong> in our service fee and are billed separately. These include fees to the Parole Board, IRCC, or US DHS. We always provide a clear breakdown in advance.',
            ],
        ],
    ],
    [
        'id'    => 'portal',
        'label' => 'Portal & Account',
        'icon'  => 'bi-laptop',
        'color' => 'info',
        'faqs'  => [
            [
                'q' => 'How do I log into the portal?',
                'a' => 'Visit the portal URL provided in your welcome email. Enter your email address and password. If you have forgotten your password, use the password reset link on the login page.',
            ],
            [
                'q' => 'Is my data secure on the portal?',
                'a' => 'Yes. All connections use <strong>HTTPS</strong> encryption. Passwords are hashed with bcrypt. Sessions expire after inactivity. We follow industry best practices for data protection.',
            ],
            [
                'q' => 'Can multiple people access my account?',
                'a' => 'Each portal account is intended for a single client. If someone else (e.g., a spouse) needs access to your information, please contact your case manager to discuss appropriate arrangements.',
            ],
            [
                'q' => 'How do I enable or disable notifications?',
                'a' => 'Go to <strong>Settings → Notifications</strong> tab. You can toggle individual notification types (Payment Reminders, Case Status Updates) or disable all notifications at once with the master toggle.',
            ],
        ],
    ],
    [
        'id'    => 'travel',
        'label' => 'Travel & Border',
        'icon'  => 'bi-airplane',
        'color' => 'dark',
        'faqs'  => [
            [
                'q' => 'Can I travel to the US while my case is being processed?',
                'a' => 'It depends on the type of application. If you have a valid TRP or US Entry Waiver, you may travel. If you do not have valid documentation, attempting to cross the border may result in denial of entry and could negatively impact your application.',
            ],
            [
                'q' => 'What should I bring when crossing the border?',
                'a' => 'Always carry: your valid passport, any applicable waiver or TRP documents, a copy of your application acknowledgment letter, and any supporting documents recommended by your case manager. Keep originals and copies separate.',
            ],
            [
                'q' => 'What happens if I am denied entry at the border?',
                'a' => 'If you are denied entry, remain calm and polite. Ask for a written refusal if possible. Contact our office as soon as you can — the details of the denial will be important for future applications.',
            ],
            [
                'q' => 'How do I set my Intended Travel Date in the portal?',
                'a' => 'On the <strong>Profile</strong> page, find the "Intended Travel Date" field and use the date picker to select your planned travel date. This helps us prioritize and time your application appropriately.',
            ],
        ],
    ],
];

/* ── Colours for section badges ─────────────────────────────── */
$sectionMap = [];
foreach ($sections as $sec) {
    $sectionMap[$sec['id']] = $sec;
}
?>

<!-- ════════════════════════════════════════════════════════════
     FAQ — Search Bar
     ════════════════════════════════════════════════════════════ -->
<div class="mb-4">
    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden" style="max-width:640px;">
        <span class="input-group-text bg-white border-0">
            <i class="bi bi-search text-muted"></i>
        </span>
        <input type="text" class="form-control border-0" id="faqSearch"
            placeholder="Search questions & answers…"
            autocomplete="off" autofocus>
        <button class="btn btn-outline-secondary border-0" type="button" id="faqSearchClear"
            style="display:none" title="Clear search">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="form-text mt-1">
        <i class="bi bi-lightbulb me-1"></i>Type keywords like "payment", "pardon", "border", "password"…
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     FAQ — Section Filter Pills
     ════════════════════════════════════════════════════════════ -->
<div class="d-flex flex-wrap gap-2 mb-4" id="faqFilters">
    <button type="button" class="btn btn-sm btn-primary rounded-pill faq-filter-pill is-active"
        data-section="all">
        <i class="bi bi-grid me-1"></i>All Sections
    </button>
    <?php foreach ($sections as $sec): ?>
        <?php if (!empty($sec['rgb'])): ?>
            <?php $inactiveText = $sec['rgbTextInactive'] ?? $sec['rgbText'] ?? '#333'; ?>
            <button type="button"
                class="btn btn-sm rounded-pill faq-filter-pill"
                data-section="<?= Html::e($sec['id']) ?>"
                style="border: 2px solid <?= $sec['rgb'] ?>; color: <?= $inactiveText ?>; background: transparent;"
                data-faq-rgb="<?= $sec['rgb'] ?>" data-faq-rgb-text="<?= $sec['rgbText'] ?? '#333' ?>" data-faq-rgb-text-inactive="<?= $inactiveText ?>">
                <i class="bi <?= Html::e($sec['icon']) ?> me-1"></i><?= Html::e($sec['label']) ?>
            </button>
        <?php else: ?>
            <button type="button"
                class="btn btn-sm btn-outline-<?= Html::e($sec['color']) ?> rounded-pill faq-filter-pill"
                data-section="<?= Html::e($sec['id']) ?>">
                <i class="bi <?= Html::e($sec['icon']) ?> me-1"></i><?= Html::e($sec['label']) ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="text-muted small mb-3" id="faqFilterStatus">
    Showing all sections &middot; <span id="faqVisibleCount"><?= array_sum(array_map(fn($s) => count($s['faqs']), $sections)) ?></span> questions
</div>

<!-- ════════════════════════════════════════════════════════════
     FAQ — Accordion Sections
     ════════════════════════════════════════════════════════════ -->
<div id="faqSections">
    <?php foreach ($sections as $sIdx => $sec): ?>
        <div class="faq-section mb-4" data-section="<?= Html::e($sec['id']) ?>">
            <h5 class="fw-bold d-flex align-items-center gap-2 mb-3">
                <?php if (!empty($sec['rgb'])): ?>
                    <span class="badge p-2 rounded-3"
                        style="background: <?= $sec['rgb'] ?>; color: <?= $sec['rgbText'] ?? '#333' ?>;">
                        <i class="bi <?= Html::e($sec['icon']) ?>"></i>
                    </span>
                <?php else: ?>
                    <span class="badge bg-<?= Html::e($sec['color']) ?> bg-opacity-10 text-<?= Html::e($sec['color']) ?> p-2 rounded-3">
                        <i class="bi <?= Html::e($sec['icon']) ?>"></i>
                    </span>
                <?php endif; ?>
                <?= Html::e($sec['label']) ?>
                <span class="badge bg-light text-secondary border ms-auto faq-section-count">
                    <?= count($sec['faqs']) ?> questions
                </span>
            </h5>
            <div class="accordion" id="faqAcc_<?= Html::e($sec['id']) ?>">
                <?php foreach ($sec['faqs'] as $fIdx => $faq): ?>
                    <div class="accordion-item border-0 shadow-sm mb-2 faq-item"
                        data-section="<?= Html::e($sec['id']) ?>"
                        data-search="<?= Html::e(strtolower($faq['q'] . ' ' . strip_tags($faq['a']))) ?>">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-semibold" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#faq_<?= Html::e($sec['id']) ?>_<?= $fIdx ?>">
                                <?= Html::e($faq['q']) ?>
                            </button>
                        </h2>
                        <div id="faq_<?= Html::e($sec['id']) ?>_<?= $fIdx ?>"
                            class="accordion-collapse collapse"
                            data-bs-parent="#faqAcc_<?= Html::e($sec['id']) ?>">
                            <div class="accordion-body text-muted">
                                <?= $faq['a'] /* safe HTML — authored by us */ ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- No results message -->
    <div class="text-center text-muted py-5 d-none" id="faqNoResults">
        <i class="bi bi-search fs-1 d-block mb-2"></i>
        <p class="mb-0">No questions match your search. Try different keywords.</p>
    </div>
</div>


<script src="<?= Asset::url('/js/faq.js') ?>"></script>
