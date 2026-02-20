# Review completa modulo “Adesione Terapie”

## BLOCKER immediati (da mettere in cima backlog)
1. **Data leakage su MessageLogResource**: nessuno scope tenant in query Filament, quindi un utente può vedere log WhatsApp di altre farmacie. (`app/Filament/Resources/MessageLogResource.php`)
2. **Checklist answer non valida per `input_type`**: il salvataggio forza tutto a stringa senza validazione tipo/opzioni; si possono salvare valori incompatibili con la domanda. (`app/Services/Therapies/Followups/SaveFollowupAnswersService.php`)
3. **Timezone clinica non rispettata**: app in UTC, mentre requisito è Europe/Rome per reminder/follow-up/PDF. (`config/app.php`)
4. **Flussi follow-up/reminder/report dedicati non implementati**: pagine Filament “Manage...” puntano a placeholder, quindi il prodotto risulta incompleto in demo/commerciale. (`app/Filament/Resources/TherapyResource/Pages/ManageTherapy*.php` + `resources/views/filament/resources/therapy-resource/pages/placeholder.blade.php`)

---

## A) MAPPA DEL MODULO (panoramica)

### Modelli principali
- `Therapy` (root aggregate terapia, scope tenant, soft-delete, relazioni paziente/reminder/followup/report/checklist/assistenti).  
  Path: `app/Models/Therapy.php`
- `Patient`, `Assistant` (anagrafiche correlate alla farmacia con naming non uniforme `pharmacy_id` vs `pharma_id`).  
  Path: `app/Models/Patient.php`, `app/Models/Assistant.php`
- `TherapyChronicCare`, `TherapyConditionSurvey`, `TherapyConsent` (blocchi clinici/consensi/survey, fortemente JSON-centrici).  
  Path: `app/Models/TherapyChronicCare.php`, `app/Models/TherapyConditionSurvey.php`, `app/Models/TherapyConsent.php`
- `TherapyChecklistQuestion` + `TherapyChecklistAnswer` (domande/risposte periodiche).  
  Path: `app/Models/TherapyChecklistQuestion.php`, `app/Models/TherapyChecklistAnswer.php`
- `TherapyReminder` + `ReminderDispatch` (schedulazione reminder e coda dispatch).  
  Path: `app/Models/TherapyReminder.php`, `app/Models/ReminderDispatch.php`
- `TherapyReport` + `AuditLog` (report condivisibili + audit trail).  
  Path: `app/Models/TherapyReport.php`, `app/Models/AuditLog.php`

### Migrazioni chiave (Postgres)
- Core: `jta_therapies`, `jta_patients`, `jta_assistants`, `jta_therapy_assistant`.  
  Path: `database/migrations/2026_02_14_000003...000006_*.php`
- Dominio terapia: chronic care, survey, followups, reminders, reports, consents, checklist question/answer.  
  Path: `database/migrations/2026_02_14_000007...000014_*.php`
- Hardening: vincoli/index tenant, backfill `pharmacy_id`, compatibilità schema.  
  Path: `2026_02_16_000100_harden_jta_postgres_schema.php`, `2026_02_19_000001_harden_followups_and_checklist_answers.php`, `2026_02_19_000200_upgrade_reminders_and_dispatches.php`, `2026_02_20_000100_upgrade_therapy_reports_and_create_audit_logs.php`

### Services (business logic)
- Creazione/aggiornamento terapia: orchestrazione di chronic care/survey/consent/assistenti/checklist bootstrap.  
  Path: `app/Services/Therapies/CreateTherapyService.php`, `UpdateTherapyService.php`
- Checklist registry + bootstrap automatico.  
  Path: `app/Domain/Checklist/ChecklistRegistry.php`, `app/Services/Checklist/EnsureTherapyChecklistService.php`
- Follow-up/check: inizializzazione check giornaliero e salvataggio risposte.  
  Path: `app/Services/Therapies/Followups/*`
- Reminder engine: `markDone` + compute next due + command dispatch.  
  Path: `app/Services/Reminders/*`, `app/Console/Commands/DispatchDueRemindersCommand.php`
- Report/PDF: snapshot report + job PDF + pubblicazione token.  
  Path: `app/Services/Therapies/GenerateTherapyReportService.php`, `app/Jobs/GenerateReportPdfJob.php`, `app/Http/Controllers/PublicReportController.php`

### Filament
- Resource principale terapia con wizard multi-step e relation manager checklist/checks/followups/reminders.  
  Path: `app/Filament/Resources/TherapyResource.php`, `app/Filament/Resources/TherapyResource/RelationManagers/*`
- Pagine dedicate followups/reminders/reports: attualmente placeholder.  
  Path: `app/Filament/Resources/TherapyResource/Pages/ManageTherapy*.php`
- Resource message logs WhatsApp (oggi non tenant-safe).  
  Path: `app/Filament/Resources/MessageLogResource.php`

### Policies / tenancy / queue
- Tenancy context: `CurrentPharmacy` + middleware `ResolveCurrentPharmacy` + trait `BelongsToPharmacy`.  
  Path: `app/Tenancy/CurrentPharmacy.php`, `app/Http/Middleware/ResolveCurrentPharmacy.php`, `app/Models/Concerns/BelongsToPharmacy.php`
- Policies aggregate-based (Therapy/Patient/Reminder/Followup/Report).  
  Path: `app/Policies/*`
- Queue jobs: report PDF e invio WhatsApp.  
  Path: `app/Jobs/GenerateReportPdfJob.php`, `app/Jobs/SendWhatsAppMessageJob.php`

---

## B) ANALISI DATA MODEL (Postgres)

### Tabelle e relazioni
- `jta_therapies` collega farmacia e paziente; root per reminder/followups/checklist/reports.  
- `jta_therapy_followups` e `jta_therapy_reminders` hanno `pharmacy_id` (hardened), buona base tenant query-driven.  
- `jta_therapy_checklist_answers` nasceva senza `pharmacy_id` e `therapy_id`, poi backfill/NOT NULL aggiunti (buon hardening).  
- `jta_therapy_assistant` inizialmente senza `pharmacy_id`, poi hardening con unique tripla (`pharmacy_id`,`therapy_id`,`assistant_id`).

### Criticità FK / indici / ambiguità
- **Ambiguità naming**: `Assistant` e WhatsApp usano `pharma_id`, il resto usa `pharmacy_id`; aumenta errori in Rule::exists e query generiche.
- **Reminder dual-table legacy**: esiste `reminder_dispatches` legacy e nuova `jta_reminder_dispatches`; rischio confusione operativa se qualche processo legge la tabella sbagliata.
- **Patient FK nullOnDelete in origine** vs terapia con `patient_id` obbligatorio: harden ha corretto verso `RESTRICT`, ma resta tecnico e poco esplicito nel dominio (serve decisione business su cancellazione paziente).

### JSON/meta che stanno sostituendo un modello dati tipizzato
1. `jta_therapy_chronic_care`: `care_context`, `doctor_info`, `general_anamnesis`, `biometric_info`, `detailed_intake`, `adherence_base`, `flags`, `consent`.  
   - Scrittura/lettura: wizard e `Create/UpdateTherapyService`; visualizzazione infolist/report.  
   - Impatto: query/reporting cross-farmacia su KPI clinici quasi impossibili senza json-path dedicati.
2. `jta_therapy_condition_surveys.answers`  
   - Scrittura in `SaveTherapySurveyService`, lettura in infolist/report snapshot.
3. Pivot `jta_therapy_assistant.preferences_json` / `consents_json`.
4. `jta_therapy_reports.content` snapshot completo (inclusi campi tecnici e strutture grezze).

### Proposta JSON vs normalizzazione
- **Tenere JSON (quick win con contratto)** per blocchi variabili ma con schema: chronic care blocchi + preferences assistente.
  - Aggiungere JSON Schema + validator (es. `opis/json-schema` o validazione custom service) e versione schema (`schema_version`) per migrazioni future.
- **Normalizzare (refactor incrementale)**:
  - `survey.answers` -> tabella `jta_therapy_survey_answers` (`survey_id`,`question_key`,`answer_value_typed`,`answer_type`).
  - `report.content` -> mantenere snapshot per audit, ma affiancare colonne tipizzate per filtri commerciali (condition, risk trend, adherence status).
  - `followup/checklist answers` -> usare colonna typed o almeno `answer_value_json` + validatore tipo.

---

## C) TENANT-SAFETY & SECURITY REVIEW

### Dove è buono
- Trait `BelongsToPharmacy` applica global scope + autoiniezione `pharmacy_id` in `creating`.
- `CreateTherapyService`/`UpdateTherapyService` verificano tenant e patient ownership.
- `SyncTherapyAssistantsService` valida gli assistenti per tenant prima del sync pivot.
- `TherapyResource::getEloquentQuery()` filtra esplicitamente per `pharmacy_id`.

### Rischi alti di data leakage
1. **MessageLogResource non scoped** (BLOCKER)  
   Query tabella senza filtro tenant e model `MessageLog` non usa trait tenant.
2. **Public report token globale**  
   Accesso via token senza scoping farmacia è intenzionale per condivisione, ma serve hardening (entropia + revoca + audit access).
3. **Uso frequente di `withoutGlobalScopes()`** in job/command/service: corretto in alcuni casi idempotenza, ma richiede sempre filtro `pharmacy_id` (non sempre presente in pattern futuri).

### Fix concreti consigliati
```php
// app/Filament/Resources/MessageLogResource.php
public static function getEloquentQuery(): Builder
{
    $tenantId = app(CurrentPharmacy::class)->getId();

    $query = parent::getEloquentQuery();

    return $tenantId
        ? $query->where('pharma_id', $tenantId)
        : $query->whereRaw('1 = 0');
}
```

```php
// app/Models/MessageLog.php (quick win)
protected static function booted(): void
{
    static::addGlobalScope('tenant', fn (Builder $q) =>
        app(CurrentPharmacy::class)->getId()
            ? $q->where('pharma_id', app(CurrentPharmacy::class)->getId())
            : $q->whereRaw('1=0')
    );
}
```

---

## D) FLOW FUNZIONALE END-TO-END

### 1) Creazione terapia (wizard)
**Buono**
- Wizard unico con creazione/edizione paziente inline.
- Bootstrap checklist automatico via registry condizione.

**Manca / confonde farmacista**
- Step misti IT/EN (“Chronic care”, “Survey”, “Active/Planned”).
- Troppi campi key/value senza guida clinica.
- Nessuna checklist “obbligatoria minima” per completare onboarding commerciale.

**Impatto vendita**
- In demo sembra “strumento tecnico” più che “percorso farmacia guidato”.

### 2) Gestione checklist
**Buono**
- Attiva/disattiva, reorder, custom question con `question_key` generato.

**Gap**
- Nessuna validazione forte su `input_type`/opzioni e valore risposta.
- Nessun template riusabile per farmacia/condizione fuori registry statico in codice.

### 3) Reminder + follow-up
**Buono**
- `markDone` lock + idempotenza one_shot.
- dispatch command con lock per reminder+dueAt.

**Gap**
- Frequenze migrate in modo opinabile (`daily` -> `weekly`).
- Nessuno scheduler dichiarato per command (deve essere esplicito in deploy docs).
- Retry/outcome dispatch non collegato a invio WhatsApp in pipeline completa.

### 4) PDF/report
**Buono**
- Snapshot completo dati per audit e condivisione link temporaneo.

**Gap grave prodotto**
- PDF è un dump JSON grezzo (`<pre>`), non presentabile a farmacista/paziente/medico.
- Nessuna intestazione farmacia/farmacista/firma clinica.
- Nessuna sezione leggibile (aderenza trend, alert, prossimi step).

---

## E) UX/UI “DA PRODOTTO” (Filament)

### Punti grezzi
- Label miste lingua e troppo tecniche (`Care context`, `flags`, `preferences_json`).
- Mancano help text/tooltips nei campi opachi.
- Status in inglese e non orientati a stato clinico comprensibile.
- Step “Manage...” vuoti (placeholder) interrompono il flusso.

### Ristrutturazione proposta (incrementale)
1. **Wizard 6 step chiari**: Paziente → Terapia → Quadro Clinico → Aderenza/Checklist → Follow-up/Reminder → Consensi/Allegati. *(quick win)*
   - File: `app/Filament/Resources/TherapyResource.php`
2. **Fieldset per dominio**: Clinico / Terapia / Aderenza / Follow-up / Note / Allegati. *(quick win)*
   - File: `TherapyResource.php` + relation managers.
3. **Preset per patologia + custom** con select condizione + “Aggiungi domanda custom”. *(refactor leggero)*
   - File: `ChecklistRegistry.php`, `ChecklistRelationManager.php`, `EnsureTherapyChecklistService.php`.
4. **Microcopy e tooltip** su campi JSON per spiegare uso/reporting. *(quick win)*
   - File: `TherapyResource.php`.
5. **Pagina report leggibile** con card “stato aderenza / prossima azione / rischio”. *(refactor UI)*
   - File: `resources/views/reports/pdf.blade.php`, `public-show.blade.php`.

---

## F) BUG LOGICI / INCONGRUENZE / DEBITO TECNICO

### BLOCKER
- **Leak tenant su Message Logs**  
  - Riproduzione: login farmacia A, apri Message Logs, vedi record farmacia B.  
  - Root cause: assenza `getEloquentQuery` scoped e modello non tenant-scoped.  
  - Fix: scope query/resource + test Feature dedicato.

- **Risposte checklist non validate per tipo**  
  - Riproduzione: domanda `select`, salva valore arbitrario da payload.  
  - Root cause: `SaveFollowupAnswersService` converte in string senza verifica opzioni.  
  - Fix: validator centralizzato per `input_type` + enum opzioni.

### HIGH
- **Timezone clinica errata (UTC)**  
  - Riproduzione: reminder/followup creati vicino a mezzanotte locale, mismatch giorno.  
  - Root cause: `config/app.php` timezone UTC + uso `now()` non contestualizzato.  
  - Fix: `Europe/Rome`, persistenza UTC + conversione in UI.

- **Pagine funzionali placeholder**  
  - Riproduzione: entra in route followups/reminders/reports dedicate.  
  - Root cause: classi `ManageTherapy*` puntano a view placeholder.  
  - Fix: nascondere navigation fino a implementazione o implementare MVP read-only.

- **Migrazione semantica reminder discutibile (`daily` -> `weekly`)**  
  - Root cause: upgrade migration hard-coded.  
  - Fix: mapping esplicito con changelog e fallback “manual_review_required”.

### MED
- **Incoerenza `pharma_id` vs `pharmacy_id`** (assistant/whatsapp/request validation)  
  - Root cause: legacy naming misto.  
  - Fix: alias model + migrazione rinomina graduale + compat layer.

- **PDF commerciale non utilizzabile** (dump JSON).  
  - Fix: template sezione-based con lessico farmacia.

- **Policy coverage incompleta su modelli non mappati in Filament**.  
  - Fix: autorizzazioni esplicite su resource sensibili e test gate.

### LOW
- Copy/UI bilingue disomogenea.
- Registry checklist hardcoded nel codice (scalabilità bassa).

---

## G) PIANO DI MIGLIORAMENTO (PR plan)

### PR1 — Stabilità tenant + sicurezza (priorità massima)
- **Obiettivo**: chiudere leakage e rinforzare autorizzazioni base.
- **File**: `MessageLogResource.php`, `MessageLog.php`, test `Feature` tenancy logs.
- **Rischio**: basso.
- **Manual test**:
  1. crea log su farmacia A/B;
  2. login A, verifica lista solo A;
  3. prova URL record B → 404/forbidden.
- **Auto test**: `tests/Feature/MessageLogTenantIsolationTest.php`.

### PR2 — Validazione checklist answers + contratto dati
- **Obiettivo**: evitare dati incoerenti non interrogabili.
- **File**: `SaveFollowupAnswersService.php`, eventualmente nuovo `ChecklistAnswerValueValidator.php`, test feature/unit.
- **Rischio**: medio (può bloccare dati sporchi esistenti).
- **Manual test**: inserisci valori validi/invalidi per boolean/select/date/number.
- **Auto test**: casi positivi/negativi per ogni `input_type`.

### PR3 — Timezone clinica e scheduling esplicito
- **Obiettivo**: coerenza date/ora in Europe/Rome lato prodotto.
- **File**: `config/app.php`, `DispatchDueRemindersCommand.php`, doc operativa.
- **Rischio**: medio.
- **Manual test**: reminder al cambio giorno e visualizzazione in UI.
- **Auto test**: unit su compute con timezone fissata.

### PR4 — UX wizard “da farmacista” (quick wins)
- **Obiettivo**: semplificare comprensione e ridurre campi tecnici.
- **File**: `TherapyResource.php`, `ChecklistRelationManager.php`, `RemindersRelationManager.php`.
- **Rischio**: medio-basso.
- **Manual test**: onboarding terapia con farmacista non tecnica in <5 minuti.
- **Auto test**: smoke test Livewire/Filament su create/edit.

### PR5 — Report/PDF commerciale
- **Obiettivo**: PDF leggibile con sezioni cliniche e dati farmacia/farmacista.
- **File**: `GenerateTherapyReportService.php`, `GenerateReportPdfJob.php`, `resources/views/reports/pdf.blade.php`.
- **Rischio**: medio.
- **Manual test**: genera report da terapia completa, verifica layout e contenuti.
- **Auto test**: feature “genera report” + assert su path/pdf_generated_at.

---

## H) Righe di spiegazione tecnica (aree opache)

### 1) Registry checklist
Il registry è codificato in PHP e funge da “template engine” minimale: al create therapy, `EnsureTherapyChecklistService` prende la condition corrente e crea domande default. È rapido ma poco governabile lato business perché ogni variazione richiede deploy. Conviene estrarre preset in tabella tenant-aware con versionamento e fallback a registry statico.

### 2) Scheduling reminder
Il flusso attuale separa “creazione dispatch” (`reminders:dispatch-due`) e “invio messaggio” (job WhatsApp), ma manca una pipeline esplicita che leghi dispatch → invio → outcome/attempt con retry policy uniforme. Serve orchestrazione chiara per idempotenza end-to-end e audit operativo.

### 3) Key/value chronic care
I blocchi key/value consentono flessibilità rapida, ma senza schema diventano non confrontabili nei report e fragili nelle evoluzioni. La via pragmatica è mantenere JSON con schema versionato + validazione centralizzata, normalizzando solo le dimensioni di reporting (es. rischio, aderenza, outcome).

### README modulo consigliato
**Sì, necessario** (quick win).  
Proposta file: `docs/therapy-module/README.md` con:
- bounded context, entità e relazioni;
- convenzioni tenant (`pharmacy_id`), naming e anti-pattern vietati;
- contratto payload wizard (DTO/schema);
- flusso reminder/dispatch/jobs;
- regole timezone (persist UTC, visualizza Europe/Rome);
- checklist preset lifecycle (draft/published/version).
