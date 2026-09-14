# Visual QA checklist (Phase 5)

Manual smoke after frontend / asset changes. Desktop + ~375px width.

## Pages

| Page | Desktop | Mobile | Notes |
|------|:-------:|:------:|-------|
| Login | ☐ | ☐ | Forgot-password toast/error (no `alert()`) |
| Register | ☐ | ☐ | Token wizard steps |
| Dashboard | ☐ | ☐ | Sidebar collapse resets on navigate (OK) |
| CIF | ☐ | ☐ | Module script order; progress bar |
| Documents | ☐ | ☐ | Upload / inbox |
| Payments | ☐ | ☐ | Pay modal listeners; CC stub |
| Services | ☐ | ☐ | Accordion |
| Profile | ☐ | ☐ | Address country→province; modals |
| Settings | ☐ | ☐ | Theme + sidebar live preview; password eyes |
| FAQ | ☐ | ☐ | Search / filters |

## Assets

- [ ] Local CSS/JS URLs include `?v=` (mtime) — edit a file, hard refresh, query changes
- [ ] CDN tags have `integrity=` + `crossorigin="anonymous"` (Bootstrap 5.3.3, Icons 1.11.3, Flatpickr 4.6.13)
- [ ] No `onclick=` / `onchange=` in `src/views`
- [ ] Dark mode + sidebar side persist after Settings save (session + DB)
- [ ] Auth pages stay light theme (intentional)

## Selective a11y (spot-check)

- [ ] Pay / profile modals have titles (`aria-labelledby` or modal-title)
- [ ] Settings appearance cards are keyboard-focusable (`tabindex="0"`)
- [ ] Form controls have visible `<label>` on pay card fields and profile address
- [ ] CIF progress has readable label / percentage text

Known gaps (backlog, not blocking Phase 5): full keyboard trap audit; persist sidebar collapse; auth dark theme.

## CIF script order

`cif-datepicker` → `dates` → `eligibility` → `visibility` → `timeline` → `tables` → `data` → `validate` → `cif.js`
