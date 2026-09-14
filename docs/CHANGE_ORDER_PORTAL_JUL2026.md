# Portal change order (Jul 2026) — locked decisions + Phase 8

**Phases 1–7: DONE** (git history). Living CIF howto: **[CIF_REFERENCE.md](CIF_REFERENCE.md)**.  
DB / Documents / email CTA: **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)**.

Sources: Personal Inform.ini (A), CRM.ini (B), Documents catalog.ini (C).

---

## Locked service rules

| Rule | Consequence |
|------|-------------|
| **`waiver-renewal` ≡ `waiver`** | Same CIF / Documents / Services data. Docs: shared catalog + **Granted Pardon** + **Port of entry**. WR UI color `#1a5c2a`. |
| **`trp` ≈ `criminal-rehab`** | US clients (permanent CR / temporary TRP). Docs: TRP = CR + Travel Itinerary. CIF together except explicit exceptions (e.g. CR-only military). Eligibility applies to **CR and TRP**. |
| **Pardon / Expg** | Canadian record suspension / cleanup after Pardon |
| **Waiver** | Canadian clients entering the US; WR = renewal |
| **Nexus** | Border fast-track for all |

---

## §0 Locked decisions (U1–U10)

| ID | Decision |
|----|----------|
| U1 | Optional day → store `YYYY-MM` or `YYYY-MM-DD` |
| U2 | One offences column key (`statute`); label **Statute** (CR/TRP) vs **Police** (Pardon/W/WR); W CA/US = two JSON keys (`offences_table_ca` / `offences_table_us`) |
| U3 | Keep only `conviction_ages` among Pardon conviction textareas |
| U4 | Doc rename/delete = replace everywhere (code + DB) |
| U5 | “Only” doc lists = strictly those slots |
| U6 | `service_courts_police.received` column |
| U7 | Alien Registration Number = Waiver (+WR) only |
| U8 | WR≡W; TRP≈CR with rare exceptions |
| U9 | TRP docs = CR + Travel Itinerary |
| U10 | Two DB fields `us_citizenship` + `green_card`; without W → combined label writes only `us_citizenship` |

### Other locked CIF rules

| Topic | Rule |
|-------|------|
| Citizenship UI | No W/WR → one **US citizenship/Green Card**; with W/WR → split fields |
| Eligibility | Finish blocks; draft OK; Client Care message shared JS+PHP |
| Timeline | CR/TRP since 18 **and** Pardon last **10** years — both must pass |
| Travel rename | `proposed_entry_date` → `intended_travel_date` |
| CR military | Separate keys from Pardon; abroad follow-ups in **Military** section (not Travel) |
| Granted Pardon doc | `waiver-granted-pardon` on W/WR Documents; CIF Yes → deep-link + highlight |

---

## Status

| Phase | Status |
|-------|--------|
| 0 Locked decisions | DONE |
| 1 Documents catalog | DONE |
| 2 Portal CRM/UI | DONE |
| 3–7 CIF | DONE — see [CIF_REFERENCE.md](CIF_REFERENCE.md) |
| **8 Matrix check** | **Remaining** |

---

## Phase 8 — Verification matrix

| Service | Documents | Eligibility | Travel | Addresses | Criminal | Services | Email |
|---------|-----------|-------------|--------|-----------|----------|----------|-------|
| CR | full CR | combined/split + CR rules | ✓ + CR military | since 18 | Statute table | received + exp | ✓ |
| TRP | CR + Travel Itinerary | as CR | ✓ (no CR-only military) | since 18 | Statute | same | ✓ |
| W / WR | Waiver + Granted Pardon | split + W rules | ✓ + inadmissibility; A-number | 5y | CA+US Police tables | — | ✓ |
| Pardon | Pardon list | — | no date/stay | 10y | Police table + ages | Police received | ✓ |
| Expg | 4 only | — | — | — | — | no Courts | ✓ |
| Nexus | 2 only | — | — | — | — | — | ✓ |
| CR+Pardon | — | — | — | both timelines | — | — | — |
| no W: citizenship UI | — | one label → `us_citizenship` | — | — | — | — | — |

Also verify: optional-day denied; `intended_travel_date` migrate; draft vs Finish; gap highlight; obsolete doc keys gone; Granted Pardon deep-link.
