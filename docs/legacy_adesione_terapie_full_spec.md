# SPEC completa modulo legacy “Adesione Terapie”

## 1) PANORAMICA MODULO

### Scopo del modulo (business)
Il modulo gestisce la presa in carico del paziente cronico in farmacia: raccolta anagrafica/clinica iniziale, definizione terapia, questionario per patologia, pianificazione follow-up periodici, promemoria operativi e produzione report/PDF.

### Attori coinvolti
- **Farmacista**: utente principale. Crea/modifica terapie, compila check/follow-up, configura reminder, genera report/PDF.
- **Admin**: stessi privilegi API del farmacista (autorizzazione `requireApiAuth(['admin','pharmacist'])`).
- **Paziente**: soggetto dei dati sanitari/anagrafici.
- **Caregiver/Familiare**: collegato alla terapia come assistente (`jta_assistants` + pivot `jta_therapy_assistant`), può avere preferenze/consensi e comparire in report.

### Flusso generale prodotto
1. Lista terapie con filtri/stato (`cronico_terapie.php` + `assets/js/cronico_terapie.js`).
2. Wizard terapia in 7 step (`adesione-terapie/assets/js/cronico_wizard.js`) con salvataggio bozza locale.
3. Salvataggio su backend (`api/therapies.php`) in tabelle terapia + chronic care + survey + consensi + assistenti.
4. Checklist check periodico gestita in tabelle dedicate (`api/followups.php`, `includes/therapy_checklist.php`).
5. Reminder con agenda today/upcoming/overdue (`api/reminders.php`).
6. Report preview/generate e PDF download (`api/reports.php`, template in `includes/pdf_templates/*`).

---

## 2) FLOW END-TO-END (DETTAGLIATO)

## 2.1 Creazione terapia

### Ingresso
- UI: bottone “Nuova terapia” (`#btnNewTherapy`) nella pagina `cronico_terapie.php`.
- Wizard 7 step con stato in `therapyWizardState` (`cronico_wizard.js`).

### Step/campi (obbligatori/opzionali)
- **Step 1 (obbligatori)**: paziente (`id` o `first_name` + `last_name`) e `primary_condition`.
- **Step 2-6**: facoltativi lato validazione front-end, ma persistiti se compilati.
- **Step 7 (obbligatori)**:
  - 3 check GDPR (`consentCareFollowup`, `consentContact`, `consentAnonymous`) tutti true.
  - `signerName` e `signedAt` valorizzati.

### Validazioni
- Front-end: `validateStep(1)` e `validateStep(7)`.
- Backend POST (`api/therapies.php`): blocca se mancano `patient.first_name`, `patient.last_name`, `primary_condition`.
- Backend PUT: consente update parziale, ma valida i campi presenti nel payload.

### Salvataggi DB
- `jta_patients` (+ `jta_pharma_patient`) se nuovo paziente.
- `jta_therapies` (titolo, descrizione, stato, date).
- `jta_therapy_chronic_care` (JSON clinici + rischio + note + consenso JSON).
- `jta_assistants` + `jta_therapy_assistant`.
- `jta_therapy_condition_surveys` (risposte questionario patologia).
- `jta_therapy_consents` (storico consenso/firme).
- `jta_therapy_followups` + `jta_therapy_checklist_answers` per check iniziale via `upsertInitialFollowupChecklist`.

### Output/azioni successive
- Toast successo, chiusura modal, reload lista terapie.
- In edit, aggiornamento record esistente e merge JSON per alcune sezioni (`mergeJsonPayload`).

### Ambiguità/incoerenze
- `consent` è salvato sia in `jta_therapy_chronic_care.consent` sia in `jta_therapy_consents` (duplicazione).
- In PUT la checklist iniziale è riallineata **solo** se arriva `condition_survey`.
- Campi legacy/canonici duplicati in `detailed_intake`/`adherence_base` (es. `forgets_medications` vs `forgets_doses`).

---

## 2.2 Gestione paziente e caregiver

### Paziente
**Ingresso**: ricerca (`api/patients.php?q=`) o inserimento manuale nel wizard step 1.

**Validazioni**:
- API patients POST: obbligatori `first_name`, `last_name`.
- API therapies POST/PUT: coerenza farmacia-paziente tramite `jta_pharma_patient`.

**Salvataggio**:
- `jta_patients`, relazione `jta_pharma_patient`.

**Output**:
- Dati paziente mostrati in lista terapie, report e PDF.

### Caregiver/assistenti
**Ingresso**: step 2 wizard (bozza assistente + elenco assistenti esistenti farmacia).

**Validazioni**:
- `api/assistants.php` POST richiede `first_name`.
- `api/therapies.php` verifica ownership farmacia per `assistant_id` esistenti.

**Salvataggio**:
- Anagrafica in `jta_assistants`.
- Link terapia-assistente in `jta_therapy_assistant` con `role`, `contact_channel`, `preferences_json`, `consents_json`.

**Output**:
- Caregiver in report, PDF terapia, PDF follow-up.

**Ambiguità**:
- Alcune preferenze caregiver finiscono in `flags.caregiver_feedback` invece che in `preferences_json` (modello misto).

---

## 2.3 Selezione patologia/condizione

### Ingresso
- Step 1: select `primaryCondition` + testo `primaryConditionOther`.
- Valori guidati: Diabete, BPCO, Ipertensione, Dislipidemia, Altro.

### Validazioni
- Front-end: obbligatoria al passaggio step 1.
- Backend: obbligatoria in POST; in PUT solo se presente nel payload.

### Salvataggi
- `jta_therapy_chronic_care.primary_condition`.
- `condition_survey.condition_type` e checklist default (`includes/therapy_checklist.php`) dipendono dalla patologia normalizzata.

### Output/azioni successive
- Aggiorna titolo terapia auto-seeded.
- Se patologia cambia e ci sono risposte survey già compilate, prompt di reset risposte.

### Ambiguità
- Normalizzazione condizione in PHP usa chiavi lowercase (`diabete`, `bpco`, ecc.), mentre UI usa label capitalizzate (mapping implicito).

---

## 2.4 Checklist/domande (default, custom, riordino, attivazione/disattivazione)

### Ingresso
- Generazione template default da `includes/therapy_checklist.php` (per condizione).
- Gestione via API followups azioni:
  - `checklist` (lista)
  - `checklist-add`
  - `checklist-remove` (soft disable `is_active=0`)
  - `checklist-reorder`

### Validazioni
- `checklist-add`: richiede `therapy_id`, `text`, `type in [text,boolean,select]`.
- `checklist-reorder`: `order` array non vuoto + `therapy_id`.

### Salvataggi DB
- Domande in `jta_therapy_checklist_questions`:
  - default con `question_key` stabile;
  - custom con `question_key = custom_<random_bytes>`.
- Risposte in `jta_therapy_checklist_answers` (unique `followup_id + question_id`).

### Output
- Domande visibili nel check periodico.
- Riutilizzate in report/PDF follow-up.

### Ambiguità/incoerenze
- Esistono due modelli parallelI:
  1. modello nuovo (question/answer tables)
  2. modello legacy snapshot (`snapshot.questions`, `snapshot.custom_questions`).
- API mantiene ancora endpoint snapshot (`init/add-question/remove-question/answer/cancel`) insieme a endpoint checklist normalizzati.

---

## 2.5 Follow-up / check periodici

### Ingresso
- Modal check periodico (`openCheckModal` in JS).
- Creazione check periodico: `POST api/followups.php?action=init`.
- Creazione follow-up “manuale”: `POST api/followups.php` con `entry_type='followup'`.

### Validazioni
- `therapy_id` obbligatorio.
- Follow-up manuale: obbligatori `risk_score` intero e `follow_up_date` formato `YYYY-MM-DD`.
- `check-meta`: valida formati `risk_score`, `follow_up_date`.

### Salvataggi DB
- `jta_therapy_followups`:
  - `entry_type='check'` + `check_type='periodic'` per check.
  - `entry_type='followup'` per follow-up manuale.
- `jta_therapy_checklist_answers` per risposte domande check.
- Stato “canceled” check via flag in JSON snapshot (`$.canceled=true`) e non colonna dedicata.

### Output/stati
- Stato derivato runtime (`withFollowupStatus`): `canceled` se snapshot.canceled, altrimenti `scheduled`.
- Conteggio risposte (`answer_count`) in listing.

### Ambiguità
- Stato follow-up non normalizzato in colonna: dipende da presenza campo JSON snapshot.
- Coesistenza `entry_type` NULL legacy + regole di inferenza da `JSON_LENGTH(snapshot)`.

---

## 2.6 Reminder/promemoria (scheduling, invio, retry, esiti/log)

### Ingresso
- Modal promemoria (`openRemindersModal`).
- CRUD reminder via `api/reminders.php`.

### Validazioni
- Obbligatori in create/update: `therapy_id`, `title`, `frequency`, `first_due_at`.
- `frequency` ammessi: `one_shot`, `weekly`, `biweekly`, `monthly`.
- `first_due_at` parsato in formati `Y-m-d\TH:i`, `Y-m-d H:i:s`, `Y-m-d H:i`.
- `weekday` richiesto/normalizzato per `weekly`.

### Salvataggi DB
- `jta_therapy_reminders`:
  - schedule: `first_due_at`, `next_due_at`, `interval_value`, `weekday`.
  - stato: `active|done|cancelled`.

### Output/azioni successive
- Agenda suddivisa in `overdue`, `today`, `upcoming` (GET view=agenda).
- “Segna fatto”:
  - one_shot -> `status=done`
  - ricorrente -> calcolo nuova `next_due_at`, `status` resta `active`.
- Delete/cancel: `status=cancelled` (soft delete logico).

### Retry/esiti/log invio
- **Invio canali (SMS/email/WhatsApp), retry automatici, log esiti dettagliati**: **non trovato**.
- Dove cercato:
  - `api/reminders.php`
  - `assets/js/cronico_terapie.js`
  - `migrations/2026-03-15_therapy_reminders_agenda.sql`
  - ricerca file repo per `notification|send|retry|channel`.

---

## 2.7 Report/PDF

### Ingresso
- Modal report (`openReportsModal`) con modalità:
  - all followups
  - single followup
- API `api/reports.php` azioni:
  - `preview`
  - `generate`
  - `pdf`
  - `therapy_pdf`
  - `followup_pdf`

### Validazioni
- `therapy_id` obbligatorio quasi ovunque.
- mode consentito: `all|single`, con `followup_id` richiesto se single.

### Salvataggi DB
- `jta_therapy_reports` per report generati/condivisi (`content`, `share_token`, ecc.).
- PDF fisico in `storage/therapy_reports/report_<id>.pdf` (se Dompdf disponibile).

### Output
- PDF download immediato per `therapy_pdf` e `followup_pdf`.
- Preview HTML + payload JSON per debug/verifica.
- Token pubblico per accesso report condiviso (`?token=`).

### Formato contenuti
- Template:
  - `includes/pdf_templates/therapy_summary.php`
  - `includes/pdf_templates/followup_report.php`
  - `includes/pdf_templates/therapy_report.php`
- Include: anagrafica farmacia/farmacista/paziente, terapia, chronic care JSON renderizzato, survey, checklist risposte, caregiver, consensi/firme.

### Ambiguità
- Doppio percorso report: summary dedicati (`therapy_pdf/followup_pdf`) e report completo (`pdf/generate`).

---

## 3) DIZIONARIO CAMPI (IMPORTANTISSIMO)

> Nota: sono inclusi i campi realmente usati in UI/logica/API. Per JSON/meta si indica il path (`json_key.sottochiave`).

| Chiave tecnica | Label utente proposta (IT) | Tipo input | Obbl. | Default | Dove usato (legacy) | Salvataggio DB | Dove riappare | Note |
|---|---|---|---|---|---|---|---|---|
| patient.id | Paziente (ID tecnico) | hidden/reference | no | null | wizard step1, API therapies | jta_therapies.patient_id | lista terapie, report | tecnico |
| patient.first_name | Nome paziente | text | sì (create) | '' | wizard step1 | jta_patients.first_name | lista/report/pdf |  |
| patient.last_name | Cognome paziente | text | sì (create) | '' | wizard step1 | jta_patients.last_name | lista/report/pdf |  |
| patient.birth_date | Data nascita | date | no | null | wizard step1 | jta_patients.birth_date | report/pdf |  |
| patient.codice_fiscale | Codice fiscale | text | no | null | wizard step1, lista | jta_patients.codice_fiscale | lista/report/pdf |  |
| patient.gender | Sesso | select | no | null | wizard step1 | jta_patients.gender | report/pdf | valori M/F/X |
| patient.phone | Telefono paziente | text | no | null | wizard step1 | jta_patients.phone | report/pdf |  |
| patient.email | Email paziente | email | no | null | wizard step1 | jta_patients.email | report/pdf |  |
| patient.notes | Note paziente | textarea | no | null | wizard step1 | jta_patients.notes | API dettaglio |  |
| therapy_title | Titolo terapia | text | no* | primary_condition | wizard step1/lista | jta_therapies.therapy_title | lista/report/pdf | *fallback automatico |
| primary_condition | Patologia principale | select+text | sì | null | wizard step1 | jta_therapy_chronic_care.primary_condition | lista/report/pdf/checklist | guida template checklist |
| initial_notes | Note terapia iniziali | textarea | no | null | wizard step1 | jta_therapies.therapy_description (+ chronic notes_initial in step6) | report/pdf | possibile duplicazione con notes_initial |
| status | Stato terapia | select | no | active | lista filtri, API therapies | jta_therapies.status | lista/report/pdf | active/planned/completed/suspended |
| start_date | Data inizio terapia | date | no | oggi su create | API therapies | jta_therapies.start_date | lista/report/pdf |  |
| end_date | Data fine terapia | date | no | null | API therapies/suspend | jta_therapies.end_date | lista/report/pdf | valorizzata a sospensione |
| general_anamnesis.family_members_count | Numero familiari conviventi | number | no | null | wizard step2 | tcc.general_anamnesis(JSON) | report/pdf |  |
| general_anamnesis.has_external_support | Supporto esterno | boolean(select) | no | null | wizard step2 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.education_level | Livello istruzione | text | no | null | wizard step2 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.has_caregiver | Presenza caregiver | boolean(select) | no | null | wizard step2 | tcc.general_anamnesis | report/pdf | abilita blocco caregiver UI |
| doctor_info.gp_reference | Medico curante | textarea | no | null | wizard step2 | tcc.doctor_info(JSON) | report/pdf |  |
| doctor_info.specialist_reference | Specialista riferimento | textarea | no | null | wizard step2 | tcc.doctor_info | report/pdf |  |
| therapy_assistants[].assistant_id | Assistente esistente | hidden/reference | no | null | wizard step2 | jta_therapy_assistant.assistant_id | report/pdf | tecnico |
| therapy_assistants[].first_name | Nome assistente | text | sì per nuovo assistente | '' | wizard step2 / api assistants | jta_assistants.first_name | report/pdf |  |
| therapy_assistants[].last_name | Cognome assistente | text | no | null | wizard step2 | jta_assistants.last_name | report/pdf |  |
| therapy_assistants[].phone | Telefono assistente | text | no | null | wizard step2 | jta_assistants.phone | report/pdf |  |
| therapy_assistants[].email | Email assistente | email | no | null | wizard step2 | jta_assistants.email | report/pdf |  |
| therapy_assistants[].type | Tipo assistente | select | no | familiare | wizard step2 | jta_assistants.type | report/pdf | caregiver/familiare |
| therapy_assistants[].relation_to_patient | Relazione col paziente | text | no | null | wizard step2 | jta_assistants.relation_to_patient | report/pdf |  |
| therapy_assistants[].preferred_contact | Canale preferito | select | no | null | wizard step2 | jta_assistants.preferred_contact | report/pdf | phone/email/whatsapp |
| therapy_assistants[].notes | Note assistente | textarea | no | null | wizard step2 | jta_assistants.notes | report/pdf |  |
| therapy_assistants[].role | Ruolo in terapia | select | no | familiare | wizard step2 forms dinamici | jta_therapy_assistant.role | report/pdf | duplicato semantico con type |
| therapy_assistants[].contact_channel | Canale contatto terapia | select | no | null | wizard step2 | jta_therapy_assistant.contact_channel | report/pdf |  |
| therapy_assistants[].consents_json.* | Consensi/permessi assistente | dynamic kv | no | {} | wizard step2 | jta_therapy_assistant.consents_json | report content | campo tecnico JSON |
| flags.caregiver_feedback.wants_monthly_report | Caregiver vuole report mensile | boolean | no | null | wizard step2 | tcc.flags(JSON) | report | modellato in flags, non tabella assistenti |
| flags.caregiver_feedback.report_channel | Canale report caregiver | select | no | null | wizard step2 | tcc.flags | report |  |
| flags.caregiver_feedback.allow_doctor_interaction | Consenso interazione medico | boolean | no | null | wizard step2 | tcc.flags | report |  |
| flags.caregiver_feedback.allow_prescription_pickup | Ritiro ricette delegato | boolean | no | null | wizard step2 | tcc.flags | report |  |
| flags.caregiver_feedback.notes | Note caregiver | textarea | no | null | wizard step2 | tcc.flags | report |  |
| general_anamnesis.female_status | Stato femminile/menopausa | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf | condizionale per sesso F |
| general_anamnesis.self_rated_health | Autopercezione salute | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.smoking_status | Fumatore | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.cigarettes_per_day | Sigarette/giorno | number | no | null | wizard step3 | tcc.general_anamnesis | report/pdf | condizionale se fumatore |
| general_anamnesis.physical_activity_regular | Attività fisica regolare | boolean(select) | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.diet_control | Controllo alimentazione | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.chronic_therapy_regimen | Schema terapia cronica | text | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.therapy_management_difficulty | Difficoltà gestione terapia | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.diagnosed_conditions.* | Condizioni diagnosticate | checkbox + text | no | false/'' | wizard step3 | tcc.general_anamnesis | report/pdf | chiavi: diabete/ipertensione/bpco/dislipidemia/altro |
| general_anamnesis.family_history_cvd | Familiarità cardiovascolare | select | no | null | wizard step3 | tcc.general_anamnesis | report/pdf |  |
| biometric_info.weight_kg | Peso (kg) | number | no | null | wizard step3 | tcc.biometric_info | report/pdf |  |
| biometric_info.height_cm | Altezza (cm) | number | no | null | wizard step3 | tcc.biometric_info | report/pdf |  |
| biometric_info.bmi | BMI | number(readonly) | no | calcolato | wizard step3 | tcc.biometric_info | report/pdf | calcolo front-end |
| general_anamnesis.exams | Esami eseguiti | text | no | null | wizard step4 | tcc.general_anamnesis | report/pdf |  |
| general_anamnesis.vaccines | Vaccini/reazioni | text | no | null | wizard step4 | tcc.general_anamnesis | report/pdf |  |
| detailed_intake.has_helper_for_medication | Aiuto nella terapia | boolean(select) | no | null | wizard step4 | tcc.detailed_intake | report/pdf |  |
| detailed_intake.drug_types | Tipologia farmaci | text | no | null | wizard step4 | tcc.detailed_intake | report/pdf |  |
| detailed_intake.uses_supplements | Uso integratori | boolean(select) | no | null | wizard step4 | tcc.detailed_intake | report/pdf |  |
| detailed_intake.supplements_details | Dettaglio integratori | text | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale |
| detailed_intake.supplements_frequency | Frequenza integratori | text | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale |
| detailed_intake.uses_bpcop_device | Uso device BPCO | boolean | no | null | wizard step4 | tcc.detailed_intake | report/pdf | typo legacy key `bpcop` |
| detailed_intake.device_problems | Problemi device | text | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale BPCO |
| detailed_intake.uses_self_measure_bp | Automisurazione pressione | boolean | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale ipertensione |
| detailed_intake.pharmacy_bp_frequency | Frequenza misurazione in farmacia | text | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale ipertensione |
| detailed_intake.ever_measured_glycemia | Ha misurato glicemia | boolean | no | null | wizard step4 | tcc.detailed_intake | report/pdf | condizionale diabete |
| adherence_base.current_therapies | Terapie correnti | text | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.devices_used | Dispositivi usati | text | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.forgets_doses | Dimentica dosi | select | no | null | wizard step4 | tcc.adherence_base | report/pdf | valori Mai/Talvolta/Spesso |
| adherence_base.stops_when_better | Interrompe se sta meglio | boolean | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.reduces_doses_without_consult | Riduce dosi senza consulto | boolean | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.knows_how_to_use_devices | Sa usare dispositivi | boolean | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.does_self_monitoring | Automonitoraggio periodico | boolean | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.last_check_date | Ultimo controllo | date | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.er_visits_last_year | Accessi PS ultimo anno | select | no | null | wizard step4 | tcc.adherence_base | report/pdf | sincronizza anche `general_anamnesis.er_access` |
| adherence_base.known_adverse_reactions | Reazioni avverse note | boolean | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| adherence_base.extra_notes | Note aderenza | textarea | no | null | wizard step4 | tcc.adherence_base | report/pdf |  |
| detailed_intake.forgets_medications | [LEGACY] dimenticanza farmaci | text | no | null | wizard step4 compat | tcc.detailed_intake | report | **non esporre in nuova UI** |
| detailed_intake.intentional_skips | [LEGACY] salti intenzionali | boolean | no | null | wizard step4 compat | tcc.detailed_intake | report | tecnico legacy |
| detailed_intake.dose_changes_self_initiated | [LEGACY] modifica dose autonoma | boolean | no | null | wizard step4 compat | tcc.detailed_intake | report | tecnico legacy |
| detailed_intake.knows_how_to_use_device | [LEGACY] usa device | boolean | no | null | wizard step4 compat | tcc.detailed_intake | report | tecnico legacy |
| detailed_intake.drug_or_supplement_allergic_reactions | [LEGACY] dettaglio reazioni | text | no | null | wizard step4 compat | tcc.detailed_intake | report | tecnico legacy |
| condition_survey.condition_type | Condizione questionario | select auto | no | primary_condition | step5 | jta_therapy_condition_surveys.condition_type | report/pdf |  |
| condition_survey.level | Livello questionario | hidden | no | base | step5/API | jta_therapy_condition_surveys.level | report/pdf | tecnico |
| condition_survey.answers.<question_key> | Risposte questionario patologia | dinamico | no | {} | step5, checklist seed | jta_therapy_condition_surveys.answers(JSON) | report/pdf/checklist iniziale | key-value |
| condition_survey.compiled_at | Data compilazione survey | date | no | oggi | step5 | jta_therapy_condition_surveys.compiled_at | report/pdf |  |
| risk_score | Rischio clinico | number | no* | null | step6/check-meta/followup | tcc.risk_score + followups.risk_score | report/pdf | *obbligatorio nel follow-up manuale |
| flags.critical_issues | Criticità rilevate | textarea | no | null | step6 | tcc.flags | report/pdf |  |
| flags.education_notes | Note educazionali | textarea | no | null | step6 | tcc.flags | report/pdf |  |
| notes_initial | Note farmacista iniziali | textarea | no | null | step6 | tcc.notes_initial | report/pdf | distinto da initial_notes |
| follow_up_date | Data prossimo follow-up | date | no | null | payload/API | tcc.follow_up_date | report/pdf | nel wizard non c’è campo visibile dedicato |
| consent.scopes.care_followup | Consenso presa in carico/follow-up | checkbox | sì step7 | false | step7 | tcc.consent + t_therapy_consents.scopes_json | pdf terapia |  |
| consent.scopes.contact_for_reminders | Consenso contatti promemoria | checkbox | sì step7 | false | step7 | tcc.consent + consents table | pdf terapia |  |
| consent.scopes.anonymous_stats | Consenso statistiche anonime | checkbox | sì step7 | false | step7 | tcc.consent + consents table | pdf terapia |  |
| consent.scopes.gdpr_accepted | GDPR completo | boolean calcolato | sì (calcolato) | false | step7 | tcc.consent + consents table | pdf terapia | tecnico derivato |
| consent.signer_name | Nome firmatario | text | sì step7 | '' | step7 | jta_therapy_consents.signer_name (+ tcc.consent) | pdf terapia |  |
| consent.signed_at | Data firma | date | sì step7 | now fallback server | step7 | jta_therapy_consents.signed_at | pdf terapia |  |
| consent.pharmacist_name | Farmacista firmatario | text | no | '' | step7 | tcc.consent JSON | pdf terapia | non in tabella consensi dedicata |
| consent.place | Luogo firma | text | no | '' | step7 | tcc.consent JSON | pdf terapia |  |
| consent.signatures.patient | Firma paziente (base64) | signature canvas | no* | null | step7 | jta_therapy_consents.signature_image(JSON) | pdf terapia | *validazione richiede solo nome+data |
| consent.signatures.pharmacist | Firma farmacista (base64) | signature canvas | no | null | step7 | jta_therapy_consents.signature_image(JSON) | pdf terapia |  |
| followups.entry_type | Tipo record followup/check | enum tecnico | sì | followup/check | api/followups | jta_therapy_followups.entry_type | report | tecnico |
| followups.check_type | Sottotipo check | enum tecnico | no | periodic/initial | api/followups/therapies | jta_therapy_followups.check_type | report/pdf | tecnico |
| followups.pharmacist_notes | Note farmacista follow-up | textarea | no | null | followup modal/check-meta | jta_therapy_followups.pharmacist_notes | report/pdf followup |  |
| followups.snapshot | Snapshot legacy check | JSON | no | {} | api/followups legacy actions | jta_therapy_followups.snapshot | report | **non replicare as-is** |
| checklist.question_text | Testo domanda checklist | text | sì | '' | check modal + API | jta_therapy_checklist_questions.question_text | check/report/pdf |  |
| checklist.question_key | Chiave domanda | text tecnico | no | auto | API template/custom | jta_therapy_checklist_questions.question_key | report mapping | tecnico |
| checklist.input_type | Tipo risposta | enum | sì | text | API checklist | jta_therapy_checklist_questions.input_type | check/report | text/boolean/select |
| checklist.options_json | Opzioni domanda | JSON | no | null | API checklist | jta_therapy_checklist_questions.options_json | check/report |  |
| checklist.sort_order | Ordine domanda | number tecnico | sì | incremental | checklist reorder | jta_therapy_checklist_questions.sort_order | check/report | tecnico |
| checklist.is_active | Domanda attiva | boolean tecnico | sì | 1 | checklist remove | jta_therapy_checklist_questions.is_active | check/report | soft delete |
| checklist_answer.answer_value | Risposta domanda | text | no | null | check answers API | jta_therapy_checklist_answers.answer_value | report/pdf | bool salvato come 'true'/'false' |
| reminder.therapy_id | Terapia riferimento | select | sì | null | modal reminder | jta_therapy_reminders.therapy_id | agenda |  |
| reminder.title | Titolo promemoria | text | sì | '' | modal reminder | jta_therapy_reminders.title | agenda |  |
| reminder.description | Descrizione promemoria | textarea | no | null | modal reminder | jta_therapy_reminders.description | agenda |  |
| reminder.frequency | Frequenza | select | sì | one_shot | modal reminder | jta_therapy_reminders.frequency | agenda | one_shot/weekly/biweekly/monthly |
| reminder.interval_value | Intervallo | number | no | 1 | modal reminder | jta_therapy_reminders.interval_value | agenda | min 1 |
| reminder.weekday | Giorno settimana | select | req per weekly | da first_due_at | modal reminder | jta_therapy_reminders.weekday | agenda | 1-7 |
| reminder.first_due_at | Prima scadenza | datetime-local | sì | null | modal reminder | jta_therapy_reminders.first_due_at | agenda | timezone Europe/Rome lato API |
| reminder.next_due_at | Prossima scadenza | datetime tecnico | auto | =first_due_at | API reminders | jta_therapy_reminders.next_due_at | agenda | tecnico calcolato |
| reminder.status | Stato promemoria | enum tecnico | sì | active | API reminders | jta_therapy_reminders.status | agenda | active/done/cancelled |
| report.mode | Modalità report | radio | sì | all | report modal | runtime API | preview/generate/pdf | all/single |
| report.followup_id | Follow-up specifico | select | req if single | null | report modal | runtime/API | pdf |  |
| report.share_token | Token condivisione | hidden | auto | random bytes | reports generate/public | jta_therapy_reports.share_token | link pubblico | tecnico |
| report.content | Contenuto report | JSON | auto | {} | reports API | jta_therapy_reports.content | list report | tecnico |

---

## 4) REGOLE BUSINESS

### Stati terapia
- Stati supportati: `active`, `planned`, `completed`, `suspended`.
- Sospensione via DELETE `api/therapies.php` => set `status='suspended'` e `end_date=today`.

### Regole completamento
- Wizard completabile solo con:
  - paziente + patologia (step1)
  - consenso GDPR completo + firmatario/data (step7)
- Follow-up manuale creabile solo con `risk_score` e `follow_up_date` validi.

### Regole reminder/frequenze
- `one_shot`: dopo “done” stato definitivo `done`.
- `weekly|biweekly|monthly`: recalcolo `next_due_at` su mark_done.
- `interval_value <1` viene normalizzato a 1.
- `weekly` usa `weekday` 1..7 (ISO weekday).

### Regole follow-up
- Check periodico inizializzato con tutte le domande attive della checklist terapia.
- Le risposte check sono per `(followup_id, question_id)` univoche.
- Stato canceled check deriva da flag JSON snapshot.

### Dipendenze tra campi
- `primary_condition='Altro'` abilita testo “Specificare”.
- Cambio patologia con survey già compilata propone reset risposte.
- Step4 mostra/nasconde blocchi per condizione:
  - BPCO -> device
  - Ipertensione -> misurazione pressione
  - Diabete -> misurazione glicemia
- `usesSupplements=true` abilita dettagli/frequenza integratori.
- `smoking_status` abilita `cigarettes_per_day`.
- `hasCaregiver=true` abilita blocco feedback caregiver.

### Eccezioni / edge case
- Update parziali terapia fanno merge JSON (non replace totale) per alcune sezioni.
- Alcuni campi legacy restano in payload per backward compatibility.
- Domande checklist custom e template convivono; disattivazione è soft (`is_active=0`).

### Timezone / date
- `api/reminders.php` e `api/reports.php` impostano `Europe/Rome`.
- Formati date stringa validati rigidamente in followups (`YYYY-MM-DD`).

---

## 5) MAPPA DB LEGACY (SOLO QUELLO CHE SERVE)

## Tabelle core modulo
- `jta_therapies`
- `jta_therapy_chronic_care`
- `jta_therapy_condition_surveys`
- `jta_patients`
- `jta_pharma_patient` (join farmacia-paziente)
- `jta_assistants`
- `jta_therapy_assistant`
- `jta_therapy_followups`
- `jta_therapy_checklist_questions`
- `jta_therapy_checklist_answers`
- `jta_therapy_reminders`
- `jta_therapy_reports`
- `jta_therapy_consents`

## Relazioni principali
- Terapia -> Paziente (`jta_therapies.patient_id`).
- Terapia -> Chronic care (1:1 logica).
- Terapia -> Assistenti (N:N via `jta_therapy_assistant`).
- Terapia -> Followups (1:N).
- Followup -> Checklist answers (1:N).
- Terapia -> Reminders (1:N).
- Terapia -> Reports (1:N).
- Terapia -> Consents (1:N storico).

## Colonne chiave
- Identificative: `id`, `therapy_id`, `patient_id`, `assistant_id`, `followup_id`.
- Operative: `status`, `entry_type`, `check_type`, `next_due_at`, `risk_score`, `follow_up_date`.
- JSON strutturanti: `general_anamnesis`, `detailed_intake`, `adherence_base`, `flags`, `consent`, `answers`, `content`.

## Colonne legacy/tecniche da NON portare 1:1
- `jta_therapy_followups.snapshot` (modello storico opaco).
- Campi duplicati legacy in `detailed_intake` (`forgets_medications`, `intentional_skips`, ecc.).
- Doppio consenso (`tcc.consent` + `jta_therapy_consents`) senza clear source of truth.

---

## 6) CRITICITÀ LEGACY (DA NON REPLICARE)

- **UX confusa**: coesistenza “check periodico” e “follow-up manuale” con modelli dati diversi.
- **Campi tecnici esposti implicitamente**: molte chiavi JSON legacy vengono renderizzate raw nei report.
- **Logiche duplicate**:
  - consenso in due tabelle/strutture;
  - aderenza in chiavi canoniche + legacy.
- **Key/value opachi**: molte informazioni cliniche in JSON libero senza schema rigido.
- **Incoerenza stati follow-up**: stato derivato da `snapshot.canceled` e non da colonna dedicata.
- **Hardcoded**:
  - patologie e molte label hardcoded JS/PHP;
  - template checklist codificati in `includes/therapy_checklist.php`.
- **Retry/log reminder invio**: non implementati a livello canale (non trovato motore delivery).

---

## 7) COSA PORTARE NEL NUOVO LARAVEL

## Da tenere
- Wizard a step con struttura clinica chiara.
- Checklist per patologia + possibilità domande custom e riordino.
- Agenda reminder (overdue/today/upcoming) con frequenze ricorrenti.
- Report/PDF con viste terapia e follow-up.
- Ownership stretta per farmacia su tutte le entità.

## Da semplificare
- Unificare modello follow-up/check in un’unica entità con stato esplicito.
- Unificare consenso in **un solo aggregate** (event log + ultimo stato).
- Rendere typed i JSON clinici in tabelle/campi strutturati dove serve analytics.
- Ridurre duplicati tra `adherence_base` e `detailed_intake`.

## Da rifare meglio
- Engine reminder con canali, retry policy, audit log invii/esiti.
- Dizionario campi centralizzato (metadata-driven) invece di key sparse hardcoded.
- Gestione checklist versionata (snapshot schema + question version) per storicità robusta.
- Stato terapia/follow-up con enum coerenti e transizioni controllate.

---

## “NON TROVATO” (esplicito)

1. **Motore invio promemoria multicanale + retry automatici + log delivery dettagliati**: non trovato.
   - Cercato in: `api/reminders.php`, `assets/js/cronico_terapie.js`, `migrations/*`, ricerca repo su keyword notification/send/retry/channel.
2. **Timezone centralizzata globale applicativa**: non trovato bootstrap unico; trovate solo impostazioni locali in alcuni endpoint (`reminders.php`, `reports.php`).
3. **Spec formale degli stati follow-up in colonna dedicata**: non trovato; stato inferito da snapshot.

