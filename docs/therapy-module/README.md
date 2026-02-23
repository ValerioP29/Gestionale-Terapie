# Therapy Module - Timezone Rules

## Obiettivo
Per il dominio clinico (reminder, follow-up, report PDF) la timezone di riferimento funzionale è **Europe/Rome**.

## Regole
1. **Persistenza DB in UTC**
   - I timestamp salvati su DB devono restare UTC.
   - Esempi: `next_due_at`, `occurred_at`, `answered_at`, `pdf_generated_at`.

2. **Calcoli clinici in Europe/Rome**
   - I calcoli di scheduling usano il calendario Europe/Rome.
   - In `ComputeNextDueAt`: conversione input UTC -> Europe/Rome, calcolo, conversione output -> UTC.
   - In `InitPeriodicCheckService`: il "giorno" del check periodico è il giorno Europe/Rome.

3. **Dispatch reminder**
   - Il confronto dei reminder due usa `now` calcolato in Europe/Rome e convertito in UTC prima del confronto SQL.

4. **Output utente/PDF in Europe/Rome**
   - Il PDF mostra data/ora in Europe/Rome (`generatedAtRome`, `validUntilRome`).

## Note operative
- Evitare assunzioni implicite su `config('app.timezone')` per logica clinica.
- Quando si aggiungono nuove funzionalità di scheduling, esplicitare sempre la conversione timezone.
