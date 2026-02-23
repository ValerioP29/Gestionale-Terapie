# Gap Analysis modulo “Adesione Terapie” (Legacy vs Laravel/Filament)

## Contesto e metodo
Questa analisi confronta la SPEC legacy (`docs/legacy_adesione_terapie_full_spec.md`) con lo stato attuale della repo Laravel + Filament.

**Obiettivo pratico:** arrivare a un modulo “Adesione Terapie” testabile end-to-end in locale, con priorità operative per PR incrementali.

---

## 1) COPERTURA ATTUALE (COSA C’È GIÀ)

### 1.1 Flussi già implementati

- **Gestione Terapie in Filament** con CRUD completo su terapia, paziente associato, blocchi chronic care, survey, consensi, assistenti/caregiver (wizard multi-step).  
  Evidenze principali:
  - wizard con step paziente / tipologia percorso / terapia / chronic care / survey / consensi / assistenti (`TherapyResource`).
  - create/update orchestrati da servizi dedicati (`CreateTherapyService`, `UpdateTherapyService`).

- **Checklist per terapia**:
  - seed automatico checklist default per condizione clinica in creazione terapia (`EnsureTherapyChecklistService` + `ChecklistRegistry`).
  - gestione checklist in relazione Filament (create custom, reorder, toggle attivo, edit).

- **Check periodico e follow-up manuali**:
  - check periodico avviabile da relazione “Checks”, con salvataggio risposte checklist.
  - follow-up manuali gestiti in relazione dedicata.
  - cancellazione follow-up supportata a livello service (`CancelFollowupService`) e colonna dedicata `canceled_at`.

- **Reminder**:
  - CRUD reminder da relation manager.
  - “Mark done” con calcolo prossima scadenza e lock idempotenza (`ReminderService` + `ComputeNextDueAt`).
  - comando console per dispatch reminder dovuti in tabella dispatch (`reminders:dispatch-due`).

- **Report/PDF**:
  - generazione report terapia con token pubblico (`GenerateTherapyReportService`).
  - job async per PDF (`GenerateReportPdfJob`) con output su storage pubblico.
  - route pubbliche per visualizzazione e download PDF (`PublicReportController`).

### 1.2 Modelli/tabelle usate oggi

Core già presenti e coerenti con dominio legacy:

- `jta_therapies`
- `jta_patients`
- `jta_therapy_chronic_care`
- `jta_therapy_condition_surveys`
- `jta_therapy_consents`
- `jta_assistants` + pivot `jta_therapy_assistant`
- `jta_therapy_followups`
- `jta_therapy_checklist_questions`
- `jta_therapy_checklist_answers`
- `jta_therapy_reminders`
- `jta_reminder_dispatches`
- `jta_therapy_reports`
- `audit_logs` (supporto audit operativo)
- `message_logs` + job WhatsApp (infrastruttura notifiche parziale)

### 1.3 Pagine Filament già funzionanti

- `TherapyResource`:
  - index/list con filtri (stato, paziente, condizione, range date)
  - create/edit wizard
  - view/infolist
  - relation managers attivi: checklist, checks, followups, reminders

- `MessageLogResource` disponibile per consultazione messaggi.

- `AgendaWidget` presente (overdue/today/upcoming) lato dashboard.

### 1.4 Reminder/follow-up/report già presenti

- Reminder:
  - creazione/modifica/cancellazione logica (paused)
  - mark done one-shot/ricorrente
  - dispatch record dei reminder dovuti in tabella dedicata

- Follow-up:
  - entry type check/followup
  - risposte checklist normalizzate in tabella answers
  - metadata follow-up (risk_score, follow_up_date, notes)

- Report:
  - report persistito con payload JSON di sintesi
  - share token pubblico con scadenza
  - PDF asincrono (job)

### 1.5 Tenant-safety (riassunto)

Copertura buona a livello foundation:

- trait `BelongsToPharmacy` con **global scope tenant** e **autopopolamento `pharmacy_id`** in create.
- `CurrentPharmacy` risolto da sessione, utente e header.
- middleware `ResolveCurrentPharmacy` su API.
- query Filament dei resource principali già filtrate per tenant.
- test automatici di isolamento tenant presenti (`TenancyIsolationTest`, `MessageLogTenantIsolationTest`).

**Nota:** esistono ancora punti eterogenei (`pharma_id` vs `pharmacy_id`) nel perimetro messaggistica/WhatsApp che meritano allineamento per evitare leakage o confusioni applicative.

---

## 2) GAP FUNZIONALI (PRIORITÀ ALTA/MEDIA/BASSA)

> Riferimenti SPEC: wizard 7 step, obbligatorietà consenso step 7, agenda/reminder canali+retry, report therapy/follow-up PDF dedicati, endpoint/azioni complete follow-up/checklist.

### PRIORITÀ ALTA

#### GAP A1 — Mancanza enforcement “minimi obbligatori legacy” nel wizard
- **Rif. SPEC legacy:** step 1 obbligatorio (paziente + condizione), step 7 obbligatorio (3 consensi + firma).
- **Mancanza/differenza:** nel nuovo wizard molti campi risultano opzionali o con logica non equivalente (es. `primary_condition` nullable a livello request/service; consensi non modellati come 3 checkbox hard-required con firma obbligatoria).
- **Impatto:** rischio blocchi test UAT (criteri accettazione non allineati), qualità dato clinico non omogenea, possibile impatto commerciale (flusso “presa in carico” incompleto).
- **File coinvolti:**
  - `app/Filament/Resources/TherapyResource.php`
  - `app/Http/Requests/TherapyStoreRequest.php`
  - `app/Http/Requests/TherapyUpdateRequest.php`
  - `app/Services/Therapies/TherapyPayloadNormalizer.php`
- **Tipo fix:** **quick win** (validazioni + UI constraints).

#### GAP A2 — Stato/semantica reminder non allineata al legacy
- **Rif. SPEC legacy:** stati `active|done|cancelled`; frequenze `one_shot|weekly|biweekly|monthly`; agenda + semantica “segna fatto”.
- **Mancanza/differenza:** nel nuovo è presente anche `paused` (non previsto in spec), con trasformazione migration da `canceled` a `paused`; UX e terminologia non coerenti col legacy.
- **Impatto:** test funzionali e training utenti non allineati; ambiguità su “annullato” vs “in pausa”.
- **File coinvolti:**
  - `app/Filament/Resources/TherapyResource/RelationManagers/RemindersRelationManager.php`
  - `database/migrations/2026_02_19_000200_upgrade_reminders_and_dispatches.php`
  - `app/Services/Reminders/ReminderService.php`
- **Tipo fix:** **refactor** (allineamento semantico + migrazione futura documentata).

#### GAP A3 — Report/PDF troppo “grezzo” rispetto al legacy
- **Rif. SPEC legacy:** report multi-modalità (all/single), PDF terapia e follow-up strutturati, contenuto clinico leggibile.
- **Mancanza/differenza:** template PDF attuale stampa JSON quasi raw; mancano output dedicati “therapy summary” e “followup report” in formato leggibile clinico-operativo.
- **Impatto:** output non presentabile a farmacista/paziente, basso valore commerciale demo/UAT.
- **File coinvolti:**
  - `resources/views/reports/pdf.blade.php`
  - `app/Services/Therapies/GenerateTherapyReportService.php`
  - `app/Jobs/GenerateReportPdfJob.php`
  - `app/Http/Controllers/PublicReportController.php`
- **Tipo fix:** **nuova feature** (template + presenter/report builder).

#### GAP A4 — Pagine Filament dedicate ancora placeholder
- **Rif. SPEC legacy:** flussi reminder/follow-up/report accessibili e completi.
- **Mancanza/differenza:** `ManageTherapyReports`, `ManageTherapyReminders`, `ManageTherapyFollowups` puntano a view placeholder “Modulo in preparazione”.
- **Impatto:** esperienza incompleta, percorso utente frammentato, rischio blocco smoke test funzionali.
- **File coinvolti:**
  - `app/Filament/Resources/TherapyResource/Pages/ManageTherapyReports.php`
  - `app/Filament/Resources/TherapyResource/Pages/ManageTherapyReminders.php`
  - `app/Filament/Resources/TherapyResource/Pages/ManageTherapyFollowups.php`
  - `resources/views/filament/resources/therapy-resource/pages/placeholder.blade.php`
- **Tipo fix:** **quick win** (rimozione/redirect) + **nuova feature** (pagine reali se richieste).

### PRIORITÀ MEDIA

#### GAP M1 — Coerenza condizione clinica e mapping label/chiavi
- **Rif. SPEC legacy:** select guidata (Diabete, BPCO, Ipertensione, Dislipidemia, Altro) con normalizzazione coerente.
- **Mancanza/differenza:** esiste rischio disallineamento tra label UI e chiavi tecniche (`diabete`, `bpco`, ecc.) e fallback `unspecified`.
- **Impatto:** checklist default non sempre seedata correttamente; analytics/reporting sporchi.
- **File coinvolti:**
  - `app/Domain/Checklist/ChecklistRegistry.php`
  - `app/Services/Checklist/EnsureTherapyChecklistService.php`
  - `app/Services/Therapies/TherapyPayloadNormalizer.php`
- **Tipo fix:** **quick win**.

#### GAP M2 — Follow-up/checklist: esperienza unificata parziale
- **Rif. SPEC legacy:** check periodico + follow-up + checklist con azioni complete e stati chiari.
- **Mancanza/differenza:** nel nuovo flusso è già normalizzato, ma manca una UX unica end-to-end (timeline completa, stato derivato user-friendly, azioni rapide su record).
- **Impatto:** usabilità inferiore al legacy JS-driven, tempi operativi maggiori in farmacia.
- **File coinvolti:**
  - relation managers `ChecksRelationManager`, `FollowupsRelationManager`, `ChecklistRelationManager`
  - presenter/view dedicata timeline (da introdurre)
- **Tipo fix:** **refactor UX**.

#### GAP M3 — Reminder dispatch incompleto sul tratto “invio reale”
- **Rif. SPEC legacy:** tracking invio canale/esito/retry.
- **Mancanza/differenza:** esiste tabella dispatch + job WhatsApp, ma non risulta pipeline completa reminder->message log->send per tutti i canali in modo orchestrato e tracciato a livello UI modulo terapie.
- **Impatto:** non testabile interamente il valore “promemoria attivi”, KPI invio non consolidati.
- **File coinvolti:**
  - `app/Console/Commands/DispatchDueRemindersCommand.php`
  - `app/Models/ReminderDispatch.php`
  - `app/Http/Controllers/Api/WhatsAppController.php`
  - `app/Jobs/SendWhatsAppMessageJob.php`
  - `app/Filament/Resources/MessageLogResource.php`
- **Tipo fix:** **nuova feature**.

### PRIORITÀ BASSA

#### GAP B1 — Terminologia UI mista IT/EN e microcopy non uniforme
- **Rif. SPEC legacy:** modulo orientato a utenti farmacia italiani.
- **Mancanza/differenza:** label tipo “Generate report PDF”, “Mark done”, “Cancel”, “One shot”, ecc.
- **Impatto:** UX percepita meno professionale, onboarding più lento.
- **File coinvolti:** resource/relation manager therapy + widget agenda.
- **Tipo fix:** **quick win**.

#### GAP B2 — Agenda widget non completamente localizzata/clinica
- **Rif. SPEC legacy:** agenda today/upcoming/overdue operativa e leggibile.
- **Mancanza/differenza:** etichette EN in parte, nessun deep-link operativo al record reminder/follow-up.
- **Impatto:** utilità pratica ridotta del widget.
- **File coinvolti:** `app/Filament/Widgets/AgendaWidget.php`, `resources/views/filament/widgets/agenda-widget.blade.php`.
- **Tipo fix:** **quick win**.

---

## 3) GAP DATI E CAMPI

### 3.1 Campi legacy importanti non coperti (o non vincolati) nel nuovo

1. **Consenso “triplo” obbligatorio** (`consentCareFollowup`, `consentContact`, `consentAnonymous`) + firma obbligatoria step finale.
2. **Distinzione forte tra check periodico e follow-up manuale** con stato user-facing pienamente esplicitato.
3. **Campi di wizard legacy espliciti** (alcuni nel nuovo confluiscono in JSON tecnici senza naming business uniforme).
4. **Output report strutturato per sezioni cliniche** (oggi troppo JSON-centric nel PDF base).

### 3.2 Campi nel nuovo troppo tecnici/opachi

- JSON keys come `detailed_intake`, `adherence_base`, `flags`, `care_context` sono corrette tecnicamente ma poco leggibili in UI se esposte senza etichetta semantica.
- `question_key`, `condition_key`, `is_custom` utili internamente ma da schermare in UI operativa standard.
- in area messaggistica: `pharma_id` vs `pharmacy_id` crea ambiguità di dominio.

### 3.3 Mapping legacy -> nuovo (campo/tabella)

| Legacy (SPEC) | Nuovo Laravel | Note gap/allineamento |
|---|---|---|
| patient.* | `jta_patients` + `jta_therapies.patient_id` | coperto |
| primary_condition | `jta_therapy_chronic_care.primary_condition` | da vincolare meglio in create/update |
| chronic care JSON | `jta_therapy_chronic_care.*` (jsonb) | coperto, naming tecnico |
| survey condition/answers | `jta_therapy_condition_surveys` | coperto |
| consent/firma | `jta_therapy_consents` + `jta_therapy_chronic_care.consent` | duplicazione storica da governare |
| assistants/caregiver | `jta_assistants` + `jta_therapy_assistant` | coperto |
| checklist questions | `jta_therapy_checklist_questions` | coperto |
| checklist answers | `jta_therapy_checklist_answers` | coperto |
| followup/check | `jta_therapy_followups` | coperto con colonna `canceled_at` (migliore del legacy JSON flag) |
| reminders | `jta_therapy_reminders` | coperto, ma semantica stato/frequenza da consolidare |
| report share/pdf | `jta_therapy_reports` | coperto base, qualità output da elevare |

### 3.4 Proposte label italiane UI (prioritarie)

- `One shot` -> **Una tantum**
- `Weekly` -> **Settimanale**
- `Biweekly` -> **Ogni 2 settimane**
- `Monthly` -> **Mensile**
- `Mark done` -> **Segna come eseguito**
- `Cancel` -> **Annulla promemoria**
- `Generate report PDF` -> **Genera report PDF**
- `Checks` -> **Check periodici**
- `Followups` -> **Follow-up manuali**
- `Chronic care` -> **Presa in carico clinica**
- `Survey` -> **Questionario patologia**

---

## 4) GAP FLUSSO UX

### 4.1 Wizard
- presente e già ricco, ma non ancora perfettamente aderente ai gate legacy (step 1 + step 7 hard required).
- manca una “barra compliance” che segnali chiaramente quando una terapia è “completa per presa in carico”.

### 4.2 Checklist
- funzionalità core ok (default + custom + reorder + enable/disable).
- manca help contestuale per distinguere domande standard da custom e impatto sui report.

### 4.3 Follow-up
- check periodico avviabile e riapribile nel giorno.
- manca timeline unica cronologica check + follow-up con stato visivo (pianificato/eseguito/annullato).

### 4.4 Reminder
- base operativa presente.
- mancano UX di canale/recipient per invio reminder e monitoraggio outcome direttamente dal contesto terapia.

### 4.5 Report/PDF
- pipeline tecnica presente.
- manca presentazione clinica leggibile (template “da consegna”) e selezione rapida modalità (all/single) nel percorso utente.

### 4.6 Placeholder da rimuovere
- pagine manage dedicate ancora placeholder: da sostituire/redirectare prima di UAT.

### 4.7 Microcopy/tooltips mancanti
- spiegazioni su `risk_score`, `follow_up_date`, differenza tra check periodico e follow-up manuale.
- tooltip su consensi e ambiti (`privacy`, `profilazione`, `dati clinici`) per ridurre errori compilazione.

---

## 5) GAP LOGICO/TECNICO

### 5.1 Validazioni mancanti
- enforcement `primary_condition` nel percorso cronico.
- enforcement consenso finale in modalità cronica con vincoli più aderenti al legacy.
- coerenza tra `status`, `start_date`, `end_date` (es. completed/suspended).

### 5.2 Regole business mancanti/parziali
- distinzione funzionale forte fra percorso “fidelity” e “chronic” ancora soprattutto UI, non completamente riflessa in regole di dominio/validazione.
- semantica reminder annullato vs pausa non allineata alla spec legacy.

### 5.3 Scheduling/date/timezone
- base timezone clinica presente (Europe/Rome nei punti principali), ma serve checklist tecnica per garantire copertura uniforme su widget/query/report/follow-up nuovi.

### 5.4 Idempotenza/log/retry
- reminder mark-done e dispatch hanno lock/idempotenza base.
- invio reminder multi-canale e retry/outcome end-to-end non ancora chiusi nel modulo terapia.

### 5.5 Policy/tenant coverage
- framework tenancy robusto su modelli principali.
- da estendere audit/controlli su componenti con naming non uniforme (`pharma_id`) e su eventuali nuove query raw introdotte nei prossimi PR.

---

## 6) PIANO PR CONSIGLIATO (PR4+)

> Nessuna migrazione in questo step. Dove necessario, proposta documentata per PR successiva.

## PR4 — “Allineamento validazioni wizard e consenso legacy”

- **Obiettivo:** chiudere i gap bloccanti di compilazione minima (step 1/7 equivalenti legacy) e criteri di completezza terapia.
- **File da toccare:**
  - `app/Filament/Resources/TherapyResource.php`
  - `app/Http/Requests/TherapyStoreRequest.php`
  - `app/Http/Requests/TherapyUpdateRequest.php`
  - `app/Services/Therapies/TherapyPayloadNormalizer.php`
- **Rischio:** medio (potenziali regressioni su record già incompleti).
- **Test plan manuale:**
  1. Creazione terapia cronica senza condizione -> blocco con errore chiaro.
  2. Creazione senza consenso minimo -> blocco.
  3. Creazione completa -> salvataggio riuscito.
  4. Edit parziale terapia esistente -> comportamento coerente con regole update.

## PR5 — “UX follow-up/checklist/reminder pronta per UAT”

- **Obiettivo:** consolidare esperienza operativa in Filament (timeline, azioni rapide, label IT, rimozione placeholder).
- **File da toccare:**
  - relation managers therapy (`Checklist`, `Checks`, `Followups`, `Reminders`)
  - pagine `ManageTherapy*` + view placeholder
  - `AgendaWidget` + blade
- **Rischio:** medio-basso (prevalenza UI).
- **Test plan manuale:**
  1. Aprire terapia e gestire checklist custom + reorder.
  2. Eseguire check periodico e verificare persistenza risposte.
  3. Creare follow-up manuale.
  4. Creare reminder, segnare done, verificare agenda.
  5. Verificare assenza placeholder nei percorsi utente.

## PR6 — “Report clinico leggibile + PDF professionale”

- **Obiettivo:** sostituire output JSON raw con layout clinico (anagrafica, terapia, checklist, follow-up, consensi, caregiver).
- **File da toccare:**
  - `resources/views/reports/pdf.blade.php`
  - `app/Services/Therapies/GenerateTherapyReportService.php`
  - eventuale presenter/report mapper dedicato
  - `resources/views/reports/public-show.blade.php`
- **Rischio:** medio (render PDF e performance payload).
- **Test plan manuale:**
  1. Generare report da terapia con dati completi.
  2. Aprire URL pubblico con token.
  3. Scaricare PDF e verificare leggibilità sezioni.
  4. Verificare timezone Europe/Rome nel documento.

## PR7 — “Reminder dispatch end-to-end e tracciamento canali”

- **Obiettivo:** completare pipeline reminder -> dispatch -> invio -> esito, con visibilità da modulo terapia.
- **File da toccare:**
  - `app/Console/Commands/DispatchDueRemindersCommand.php`
  - `app/Jobs/SendWhatsAppMessageJob.php`
  - `app/Models/ReminderDispatch.php`
  - `app/Models/MessageLog.php`
  - `app/Http/Controllers/Api/WhatsAppController.php`
  - resource/log UI dove necessario
- **Rischio:** medio-alto (queue/retry/idempotenza/tenant).
- **Test plan manuale:**
  1. Creare reminder dovuto.
  2. Eseguire comando dispatch.
  3. Verificare creazione dispatch record.
  4. Simulare invio ok/errore e verificare outcome + retry.
  5. Verificare isolamento tenant su log/dispatch.

## PR8 — “Hardening dati + allineamento nomenclature”

- **Obiettivo:** ridurre debito tecnico su naming e semantiche (`pharma_id` vs `pharmacy_id`, paused vs cancelled, mapping condizione).
- **File da toccare:**
  - modelli messaggistica/reminder
  - servizi normalizzazione
  - test di regressione tenancy/business
  - **eventuale proposta migrazione** (da formalizzare in PR dedicata, non in questo step)
- **Rischio:** alto (impatto dati esistenti).
- **Test plan manuale:**
  1. Smoke CRUD terapia completo.
  2. Smoke reminder + dispatch.
  3. Verifica policy/tenant su query principali.
  4. Verifica compatibilità dati preesistenti.

---

## Nota finale operativa

Per avere “Adesione Terapie” **pronta da test locale**, la sequenza minima consigliata è: **PR4 -> PR5 -> PR6**.

- Dopo PR4: dati e regole minime affidabili.
- Dopo PR5: UX operativa usabile dal team farmacia.
- Dopo PR6: output report/PDF presentabile in demo/UAT.

PR7/PR8 completano la robustezza enterprise (dispatch reale e hardening dati).
