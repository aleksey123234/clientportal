# Retell AI — Руководство по интеграции в CRM

**Дата:** 2026-04-17
**Статус:** Рабочие копии файлов готовы — интеграция в production

---

## Структура созданных файлов

```
development/retell_ai/
├── ctrl/
│   ├── retell.php              ← Главный класс RetellAI (бизнес-логика)
│   ├── retell_ajax.php         ← AJAX endpoints (роутер)
│   ├── retell_webhook.php      ← Webhook handler (Retell → CRM)
│   ├── orders.ORIGINAL.php     ← Оригинал (backup)
│   ├── leads.ORIGINAL.php      ← Оригинал (backup)
│   ├── vonage.ORIGINAL.php     ← Оригинал (backup)
│   └── cron.ORIGINAL.php       ← Оригинал (backup)
├── cron/
│   ├── retell_trigger.php      ← Cron: запуск pending звонков
│   └── retell_cleanup.php      ← Cron: сброс зависших in_progress
├── sql/
│   ├── retell_migration_2026-04-17.sql  ← SQL миграция
│   └── *.ORIGINAL.sql                   ← Оригиналы схем (backup)
└── view/
    └── orders/tmpls/
        └── tmpl_retell_ai_block.php     ← UI блок для страницы заказа
```

---

## Шаг 1 — SQL миграция

Запустить **один раз** на production DB:

```bash
mysql -u root -p crm_db < development/retell_ai/sql/retell_migration_2026-04-17.sql
```

Что добавляет:

- `core_followup_date`: `retell_status`, `retell_attempt_count`, `package_type`
- `core_called_to_client`: `followup_id`, `retell_call_id`, `call_outcome`, `call_transcript`
- `core_order_comments`: `is_ai`
- `core_cron_config`: `updated_at`, начальные lock-ключи

---

## Шаг 2 — Константы в sys/mainframe.php

Добавить в конец файла `sys/mainframe.php`:

```php
// ── Retell AI ────────────────────────────────────────────────────────────────
define('RETELL_API_KEY',        'key_a7f0b96f04672158cc4a60bd2b13');
define('RETELL_AGENT_ID',       'agent_82aac4cf9bda6164e0e5d683dc');
define('RETELL_FROM_NUMBER',    '+16474772387');
define('RETELL_WEBHOOK_SECRET', 'REPLACE_WITH_SECRET_FROM_RETELL_DASHBOARD');
// ────────────────────────────────────────────────────────────────────────────
```

> **RETELL_WEBHOOK_SECRET** — взять из Retell Dashboard → Webhooks → Signing Secret

---

## Шаг 3 — Скопировать файлы в production

```bash
cp development/retell_ai/ctrl/retell.php         admin/ctrl/retell.php
cp development/retell_ai/ctrl/retell_ajax.php    admin/ctrl/retell_ajax.php
cp development/retell_ai/ctrl/retell_webhook.php admin/ctrl/retell_webhook.php
cp development/retell_ai/cron/retell_trigger.php admin/ctrl/cron/retell_trigger.php
cp development/retell_ai/cron/retell_cleanup.php admin/ctrl/cron/retell_cleanup.php
cp development/retell_ai/view/orders/tmpls/tmpl_retell_ai_block.php \
   admin/view/orders/tmpls/tmpl_retell_ai_block.php
```

---

## Шаг 4 — Добавить AJAX роуты в admin/ctrl/orders.php

Найти метод `ajax($param)` (или `run()` → `case "ajax"`) и добавить:

```php
// Найти файл: admin/ctrl/orders.php
// Найти: case "ajax":   ... switch($action) {

// ДОБАВИТЬ в начало ajax() метода:
require_once dirname(__FILE__) . '/retell_ajax.php';
$retellAjax = new RetellAjax($this->core);

// ДОБАВИТЬ кейсы в switch($action):
case "retell_get_queue":
    return $retellAjax->getQueue($param);

case "retell_trigger_call":
    return $retellAjax->triggerCall($param);

case "retell_get_transcript":
    return $retellAjax->getTranscript($param);

case "retell_cancel":
    return $retellAjax->cancelFollowup($param);

case "retell_resend_email":
    return $retellAjax->resendEmail($param);

case "retell_get_email_types":
    // Вернуть список типов писем для dropdown
    $types = $this->core->db()->getList(
        "SELECT `id`, `lang_name` FROM `core_email_types`
         WHERE `type` = 'lead' AND `is_active` = 1
         ORDER BY `pos` ASC"
    );
    $out = array_map(function($t) {
        return ['id' => $t->id, 'label' => Language::_($t->lang_name)];
    }, $types);
    echo json_encode(['is_success' => true, 'types' => $out]);
    return;
```

---

## Шаг 5 — Добавить UI блок на страницу заказа

В `admin/view/orders/index.php` найти место где отображаются follow-up / комментарии заказа и добавить:

```php
<?php
// Передать переменные в шаблон
$data['retell_order_id'] = $order->id;         // UUID заказа
$data['retell_lead_id']  = $order->lead_id;    // UUID клиента (лида)
?>

<!-- AI Follow-up Calls block -->
<?php include dirname(__FILE__) . '/tmpls/tmpl_retell_ai_block.php'; ?>
```

---

## Шаг 6 — Добавить роут для webhook

В `index.php` (или `.htaccess` роутер) добавить URL без требования авторизации:

```php
case "retell":
    // URL: /retell/webhook/
    require_once PATH_CTRL . DS . 'retell_webhook.php';
    $ctrl = new RetellWebhookCTRL($core);
    $ctrl->run();
    break;
```

Или в `.htaccess`:

```apache
RewriteRule ^retell/webhook/?$ /index.php?ctrl=retell_webhook [L,QSA]
```

---

## Шаг 7 — Cron jobs

Добавить на сервере (`crontab -e`):

```bash
# Каждую минуту — запуск запланированных AI звонков
* * * * * curl -s https://mailnfly.com/cron/retell_trigger/ > /dev/null 2>&1

# Каждый час — сброс зависших звонков
0 * * * * curl -s https://mailnfly.com/cron/retell_cleanup/ > /dev/null 2>&1
```

---

## Шаг 8 — Стилизация AI-комментариев в timeline заказа

В существующем шаблоне комментариев заказа найти рендеринг `core_order_comments` и добавить класс:

```php
// Найти в admin/view/orders/index.php или соответствующем template:
// где выводится список комментариев заказа

$commentClass = !empty($comment->is_ai) ? 'order-comment-ai' : '';
// Добавить $commentClass к CSS классу элемента комментария
```

CSS уже включён в `tmpl_retell_ai_block.php`.

---

## Логика автоматических FUP (не более 3 попыток)

```
Заказ создан / менеджер ставит AI FUP
        ↓
core_followup_date: retell_status='pending', retell_attempt_count=0
        ↓
Cron (каждую минуту) → triggerRetellCall()
        ↓
retell_attempt_count++ (становится 1, 2 или 3)
        ↓
Webhook call_analyzed → switch(outcome):
  ├── no_answer          → scheduleNextFollowup() если count < 3 → новый FUP pending
  ├── resend_email       → resendEmail() + scheduleNextFollowup() если count < 3
  ├── callback_requested → alertManager() + SMS → НЕ создаём новый FUP
  ├── refused            → alertManager() → НЕ создаём новый FUP
  └── email_confirmed    → ничего → завершено ✅

Если attempt_count == 3 → scheduleNextFollowup() возвращает false → цикл останавливается
```

---

## Функционал по требованиям

| Требование                      | Реализация                                                                                                                  |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| Список звонков AI (не вручную)  | `retell_get_queue` → таблица в UI с автообновлением                                                                         |
| ИИ пишет что проделала          | `addCallComment()` → `core_order_comments` с `is_ai=1`, текст типа "Called client — no answer, left detailed voice message" |
| Просмотр транскрипций           | `retell_get_transcript` + Modal с построчным форматом AI / Client                                                           |
| ИИ создаёт FUP сама, не более 3 | `scheduleNextFollowup()` + `retell_attempt_count` + проверка `>= 3`                                                         |
| Переотправить email             | `retell_resend_email` — вручную кнопкой или автоматически по outcome `resend_email`                                         |
