<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TherapyResource\Pages;
use App\Filament\Resources\TherapyResource\RelationManagers\ChecklistRelationManager;
use App\Filament\Resources\TherapyResource\RelationManagers\ChecksRelationManager;
use App\Filament\Resources\TherapyResource\RelationManagers\FollowupsRelationManager;
use App\Filament\Resources\TherapyResource\RelationManagers\RemindersRelationManager;
use App\Filament\Resources\TherapyResource\RelationManagers\TimelineRelationManager;
use App\Models\Assistant;
use App\Models\Patient;
use App\Models\Therapy;
use App\Models\TherapyChronicCare;
use App\Models\TherapyReport;
use App\Presenters\TherapyPresenter;
use App\Services\Patients\CreatePatientService;
use App\Services\Patients\UpdatePatientService;
use App\Services\Therapies\GenerateTherapyReportService;
use App\Support\ConditionKeyNormalizer;
use App\Tenancy\CurrentPharmacy;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use App\Exceptions\CurrentPharmacyNotResolvedException;

class TherapyResource extends Resource
{
    protected static ?string $model = Therapy::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationGroup = 'Terapie';

    public static function getModelLabel(): string
    {
        return 'terapia';
    }

    public static function getPluralModelLabel(): string
    {
        return 'terapie';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Paziente')->schema([
                    Forms\Components\Select::make('patient_id')
                        ->label('Paziente')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => self::searchPatients($search))
                        ->getOptionLabelUsing(fn ($value): ?string => self::getPatientLabel($value))
                        ->createOptionForm(self::patientFormSchema())
                        ->createOptionUsing(function (array $data): int {
                            try {
                                $patient = app(CreatePatientService::class)->handle($data);
                            } catch (CurrentPharmacyNotResolvedException) {
                                throw ValidationException::withMessages([
                                    'patient_id' => 'Farmacia corrente non risolta. Seleziona una farmacia e riprova.',
                                ]);
                            }

                            Notification::make()->success()->title('Paziente creato')->send();

                            return $patient->id;
                        })
                        ->editOptionForm(self::patientFormSchema())
                        ->fillEditOptionActionFormUsing(function (Patient $record, Forms\Set $set): void {
                            foreach ($record->only(['first_name', 'last_name', 'birth_date', 'codice_fiscale', 'gender', 'phone', 'email', 'notes']) as $key => $value) {
                                $set($key, $value);
                            }
                        })
                        ->updateOptionUsing(function (array $data, string $state): string {
                            $tenantId = app(CurrentPharmacy::class)->getId();

                            if ($tenantId === null) {
                                throw new CurrentPharmacyNotResolvedException();
                            }

                            $patient = Patient::query()
                                ->where('pharmacy_id', $tenantId)
                                ->findOrFail((int) $state);

                            app(UpdatePatientService::class)->handle($patient, $data);

                            Notification::make()->success()->title('Paziente aggiornato')->send();

                            return (string) $patient->id;
                        }),
                ]),
                Forms\Components\Wizard\Step::make('Percorso terapeutico')->description('Scegli il tipo di programma per adattare i campi del wizard.')->schema([
                    Forms\Components\Radio::make('ui_care_mode')
                        ->label('Tipo percorso')
                        ->options([
                            'chronic' => 'Presa in carico cronica',
                            'fidelity' => 'Fidelizzazione / monitoraggio leggero',
                        ])
                        ->default('chronic')
                        ->live()
                        ->dehydrated(false)
                        ->helperText('La scelta modifica i campi mostrati nei passaggi successivi.'),
                    Forms\Components\Placeholder::make('ui_minimum_banner')
                        ->label('Minimi obbligatori')
                        ->content(fn (Forms\Get $get): string => ($get('ui_care_mode') === 'fidelity')
                            ? 'Per la fidelizzazione compila almeno: Titolo terapia, Data inizio, Note terapia e Consenso.'
                            : 'Per la presa in carico cronica compila almeno: Paziente, Patologia principale, 3 consensi obbligatori e firma finale.'),
                    Forms\Components\Placeholder::make('ui_completeness_badge')
                        ->label('Stato presa in carico')
                        ->content(fn (Forms\Get $get): string => self::completenessBadgeFromForm($get))
                        ->columnSpanFull(),
                ])->columns(1),
                Forms\Components\Wizard\Step::make('Terapia')->schema([
                    Forms\Components\TextInput::make('therapy_title')->label('Titolo terapia')->required()->maxLength(255)->helperText('Usa un titolo chiaro, es. Terapia antipertensiva.')->validationMessages(['required' => 'Inserisci il titolo della terapia.']),
                    Forms\Components\Select::make('status')->required()->options([
                        'active' => 'Attiva',
                        'planned' => 'Pianificata',
                        'completed' => 'Completata',
                        'suspended' => 'Sospesa',
                    ])->default('active')->validationMessages(['required' => 'Seleziona lo stato della terapia.']),
                    Forms\Components\DatePicker::make('start_date')->label('Data inizio')->placeholder('Seleziona la data di avvio della terapia.'),
                    Forms\Components\DatePicker::make('end_date')->label('Data fine')->placeholder('Seleziona la data di chiusura, se prevista.')->afterOrEqual('start_date')->validationMessages(['after_or_equal' => 'La data di fine deve essere uguale o successiva alla data di inizio.']),
                    Forms\Components\Textarea::make('therapy_description')->label('Note terapia')->rows(4)->helperText('Note operative visibili al team farmacia.')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') === 'fidelity')->validationMessages(['required' => 'Per la fidelizzazione compila le note terapia.']),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Valutazione iniziale')->description('Inserisci solo le note cliniche iniziali e le domande strutturate del primo colloquio.')->schema([
                    Forms\Components\TextInput::make('risk_score')->label('Indice di rischio (0-100)')->numeric()->minValue(0)->maxValue(100)->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->validationMessages([
                        'numeric' => 'L\'indice di rischio deve essere numerico.',
                        'min' => 'L\'indice di rischio non può essere inferiore a 0.',
                        'max' => 'L\'indice di rischio non può superare 100.',
                    ]),
                    Forms\Components\DatePicker::make('follow_up_date')->label('Prossimo follow-up suggerito')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    Forms\Components\Textarea::make('notes_initial')->label('Note cliniche iniziali')->helperText('Riassunto clinico iniziale utile al monitoraggio.')->columnSpanFull()->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    self::clinicalQuestionsRepeater('chronic_care.care_context', 'Contesto assistenziale', config('therapy_clinical_questions.care_context', [])),
                    Forms\Components\Section::make('Medico curante / Specialista')
                        ->description('Compila i riferimenti del medico curante e, se disponibile, dello specialista.')
                        ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->schema([
                            Forms\Components\Fieldset::make('Medico curante')->schema([
                                Forms\Components\TextInput::make('chronic_care.doctor_info.medico_curante.nome')->label('Nome')->maxLength(100),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.medico_curante.cognome')->label('Cognome')->maxLength(100),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.medico_curante.email')->label('E-mail')->email()->maxLength(150),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.medico_curante.telefono')->label('Telefono')->tel()->maxLength(30),
                            ])->columns(2),
                            Forms\Components\Fieldset::make('Specialista (facoltativo)')->schema([
                                Forms\Components\TextInput::make('chronic_care.doctor_info.specialista.nome')->label('Nome')->maxLength(100),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.specialista.cognome')->label('Cognome')->maxLength(100),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.specialista.email')->label('E-mail')->email()->maxLength(150),
                                Forms\Components\TextInput::make('chronic_care.doctor_info.specialista.telefono')->label('Telefono')->tel()->maxLength(30),
                            ])->columns(2),
                        ])
                        ->columns(1)
                        ->columnSpanFull(),
                    self::clinicalQuestionsRepeater('chronic_care.general_anamnesis', 'Anamnesi generale', config('therapy_clinical_questions.general_anamnesis', [])),
                    self::clinicalQuestionsRepeater('chronic_care.biometric_info', 'Dati biometrici', config('therapy_clinical_questions.biometric_info', [])),
                    self::clinicalQuestionsRepeater('chronic_care.detailed_intake', 'Dettaglio assunzione terapia', config('therapy_clinical_questions.detailed_intake', [])),
                    self::clinicalQuestionsRepeater('chronic_care.adherence_base', 'Valutazione base aderenza', config('therapy_clinical_questions.adherence_base', [])),
                    self::clinicalQuestionsRepeater('chronic_care.flags', 'Segnalazioni cliniche', config('therapy_clinical_questions.flags', [])),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Questionario di aderenza')->description("Definisci la patologia principale e compila il questionario iniziale per monitorare l'aderenza.")->schema([
                    Forms\Components\Select::make('primary_condition')->label('Patologia/condizione principale')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options(ConditionKeyNormalizer::options())->helperText('Seleziona una condizione standardizzata per allineare checklist e report.')->validationMessages(['required' => 'La condizione clinica principale è obbligatoria.'])->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->live()->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            self::syncSurveyAnswersWithTemplates($set, $get);
                        })->dehydrateStateUsing(fn (mixed $state, Forms\Get $get): string => self::effectiveConditionKey($state, $get('custom_condition_name'))),
                    Forms\Components\TextInput::make('custom_condition_name')
                        ->label('Nome patologia custom')
                        ->placeholder('Es. carcinoma mammario')
                        ->maxLength(120)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            self::syncSurveyAnswersWithTemplates($set, $get);
                        })
                        ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && $get('primary_condition') === 'altro')
                        ->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && $get('primary_condition') === 'altro')
                        ->validationMessages(['required' => 'Inserisci il nome della patologia custom.']),
                    Forms\Components\Select::make('survey.level')->label('Livello questionario')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options([
                        'base' => 'Base',
                        'approfondito' => 'Approfondito',
                    ])->live()->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                        self::syncSurveyAnswersWithTemplates($set, $get);
                    })->validationMessages(['required' => 'Seleziona il livello del questionario.']),
                    Forms\Components\Hidden::make('survey._mode_state')->dehydrated(false),
                    Forms\Components\Hidden::make('survey._condition_state')->dehydrated(false),
                    Forms\Components\Repeater::make('survey.answers')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->schema([
                            Forms\Components\Select::make('question_key')
                                ->label('Domanda checklist')
                                ->helperText('Le domande base standard vengono precaricate automaticamente.')
                                ->required(fn (Forms\Get $get): bool => self::usesTemplateSurveyQuestions($get))
                                ->searchable()
                                ->visible(fn (Forms\Get $get): bool => self::usesTemplateSurveyQuestions($get))
                                ->options(fn (Forms\Get $get): array => self::surveyQuestionOptions($get)),
                            Forms\Components\TextInput::make('question_label')
                                ->label('Domanda custom')
                                ->placeholder('Inserisci una domanda personalizzata')
                                ->required(fn (Forms\Get $get): bool => ! self::usesTemplateSurveyQuestions($get))
                                ->visible(fn (Forms\Get $get): bool => ! self::usesTemplateSurveyQuestions($get)),
                            Forms\Components\TextInput::make('answer')->label('Risposta')->required()->helperText('Inserisci una risposta comprensibile per il report.'),
                        ])
                        ->defaultItems(0)
                        ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get): void {
                            self::syncSurveyAnswersWithTemplates($set, $get);
                        })
                        ->addActionLabel('Aggiungi domanda')
                        ->columnSpanFull(),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Consenso informato')->schema([
                    Forms\Components\TextInput::make('consent.signer_name')->label('Nome e cognome firmatario')->required()->maxLength(150)->validationMessages(['required' => 'Inserisci il nominativo del firmatario.']),
                    Forms\Components\Select::make('consent.signer_relation')->required()->options([
                        'patient' => 'Paziente',
                        'caregiver' => 'Caregiver',
                        'familiare' => 'Familiare',
                    ]),
                    Forms\Components\TextInput::make('consent.signer_role')->label('Ruolo firmatario (facoltativo)')->maxLength(20),
                    Forms\Components\DateTimePicker::make('consent.signed_at')->label('Data e ora firma')->required()->validationMessages(['required' => 'Indica data e ora della firma del consenso.']),
                    Forms\Components\Textarea::make('consent.consent_text')->label('Testo consenso')->required()->columnSpanFull()->validationMessages(['required' => 'Inserisci il testo del consenso informato.']),
                    Forms\Components\CheckboxList::make('consent.scopes_json')
                        ->helperText('Per la presa in carico cronica seleziona tutti e tre i consensi minimi.')
                        ->options([
                            'privacy' => 'Privacy',
                            'marketing' => 'Marketing',
                            'profiling' => 'Uso dati anonimizzati',
                            'clinical_data' => 'Follow-up clinico',
                        ])
                        ->columns(2)
                        ->required()
                        ->validationMessages(['required' => 'Seleziona i consensi obbligatori per completare la presa in carico.'])
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('consent.consent_care_followup')
                        ->label('Acconsento ai follow-up di presa in carico')
                        ->default(false)
                        ->accepted(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->validationMessages(['accepted' => 'Conferma il consenso ai follow-up.']),
                    Forms\Components\Toggle::make('consent.consent_contact')
                        ->label('Acconsento a essere contattato per promemoria e comunicazioni')
                        ->default(false)
                        ->accepted(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->validationMessages(['accepted' => 'Conferma il consenso ai contatti.']),
                    Forms\Components\Toggle::make('consent.consent_anonymous')
                        ->label('Acconsento all\'uso anonimizzato dei dati')
                        ->default(false)
                        ->accepted(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->validationMessages(['accepted' => 'Conferma il consenso all\'uso anonimizzato dei dati.']),
                    Forms\Components\FileUpload::make('consent.signature_path')
                        ->label('Firma (facoltativa: allega immagine se disponibile)')
                        ->disk(config('filesystems.default'))
                        ->directory('therapy-signatures')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->columnSpanFull(),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Caregiver e assistenti')->schema([
                    Forms\Components\Repeater::make('assistants')->helperText('Associa caregiver/familiari da contattare per promemoria e follow-up.')
                        ->schema([
                            Forms\Components\Select::make('assistant_id')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => self::searchAssistants($search))
                                ->getOptionLabelUsing(fn ($value): ?string => self::getAssistantLabel($value)),
                            Forms\Components\Select::make('role')->required()->options([
                                'caregiver' => 'Caregiver',
                                'familiare' => 'Familiare',
                            ]),
                            Forms\Components\Select::make('contact_channel')->options([
                                'phone' => 'Telefono',
                                'email' => 'E-mail',
                                'whatsapp' => 'WhatsApp',
                            ]),
                            Forms\Components\KeyValue::make('preferences_json')->label('Preferenze contatto')->helperText('Esempio: fascia_oraria => mattina.'),
                            Forms\Components\KeyValue::make('consents_json')->label('Consensi assistente')->helperText('Solo informazioni essenziali, evita dati sensibili non necessari.'),
                        ])
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
            ])
                ->nextAction(fn (Action $action): Action => $action->label('Avanti'))
                ->previousAction(fn (Action $action): Action => $action->label('Indietro'))
                ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-color-custom fi-ac-action fi-ac-btn-action"><span class="fi-btn-label">Salva terapia</span></button>'))
                ->columnSpanFull(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Paziente')->schema([
                TextEntry::make('patient.first_name')->label('Nome'),
                TextEntry::make('patient.last_name')->label('Cognome'),
                TextEntry::make('patient.codice_fiscale'),
                TextEntry::make('patient.phone'),
                TextEntry::make('patient.email'),
            ])->columns(2),
            InfolistSection::make('Terapia')->schema([
                TextEntry::make('therapy_title'),
                TextEntry::make('status')
                    ->badge()
                    ->label('Stato')
                    ->state(fn (Therapy $record): string => (new TherapyPresenter($record))->statusLabel()),
                TextEntry::make('start_date')->date(),
                TextEntry::make('end_date')->date(),
                TextEntry::make('therapy_description')->label('Note')->columnSpanFull(),
                TextEntry::make('presa_in_carico')
                    ->label('Presa in carico')
                    ->badge()
                    ->state(fn (Therapy $record): string => self::isTherapyComplete($record) ? 'Completa' : 'Incompleta')
                    ->color(fn (Therapy $record): string => self::isTherapyComplete($record) ? 'success' : 'danger'),
            ])->columns(2),
            InfolistSection::make('Presa in carico clinica')->schema([
                TextEntry::make('currentChronicCare.primary_condition')->label('Condizione principale')->state(fn (Therapy $record): string => self::conditionLabel($record->currentChronicCare?->primary_condition, $record->currentChronicCare?->custom_condition_name)),
                TextEntry::make('currentChronicCare.risk_score'),
                TextEntry::make('currentChronicCare.follow_up_date')->date(),
                TextEntry::make('currentChronicCare.notes_initial')->columnSpanFull(),
            ])->columns(2),
            InfolistSection::make('Questionario di aderenza')->schema([
                TextEntry::make('latestSurvey.condition_type')->label('Condizione questionario')->state(fn (Therapy $record): string => self::conditionLabel($record->latestSurvey?->condition_type, $record->currentChronicCare?->custom_condition_name)),
                TextEntry::make('latestSurvey.level')->badge(),
                TextEntry::make('survey_readable')
                    ->label('Risposte questionario')
                    ->state(fn (Therapy $record): string => (new TherapyPresenter($record))->surveyAnswersReadable())
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'whitespace-pre-line']),
            ])->columns(2),
            InfolistSection::make('Consensi')->schema([
                TextEntry::make('latestConsent.signer_name'),
                TextEntry::make('latestConsent.signer_relation'),
                TextEntry::make('latestConsent.signed_at')->dateTime(),
                TextEntry::make('consent_scopes_readable')
                    ->label('Ambiti consenso')
                    ->state(fn (Therapy $record): string => (new TherapyPresenter($record))->consentScopesReadable()),
            ])->columns(2),

            InfolistSection::make('Report generati')->schema([
                ViewEntry::make('reports_table')
                    ->label('Storico report')
                    ->view('filament.infolists.entries.therapy-reports-list')
                    ->state(function (Therapy $record): array {
                        return $record->reports()
                            ->latest('created_at')
                            ->get(['id', 'created_at', 'status', 'pdf_path', 'share_token', 'error_message'])
                            ->map(fn (TherapyReport $report): array => [
                                'id' => $report->id,
                                'generated_at' => $report->created_at?->format('d/m/Y H:i') ?? '-',
                                'status' => match ($report->status) {
                                    TherapyReport::STATUS_COMPLETED => 'Pronto',
                                    TherapyReport::STATUS_PROCESSING => 'In generazione',
                                    TherapyReport::STATUS_FAILED => 'Fallito',
                                    default => 'In coda',
                                },
                                'status_code' => $report->status,
                                'download_url' => ($report->status === TherapyReport::STATUS_COMPLETED && $report->pdf_path !== null)
                                    ? route('reports.pdf', ['token' => $report->share_token])
                                    : null,
                                'error_message' => $report->status === TherapyReport::STATUS_FAILED ? $report->error_message : null,
                            ])
                            ->all();
                    })
                    ->columnSpanFull(),
            ]),

            InfolistSection::make('Assistenti/Caregiver')->schema([
                TextEntry::make('assistants_list')
                    ->state(fn (Therapy $record): string => $record->assistants
                        ->map(fn (Assistant $assistant): string => trim(sprintf('%s %s (%s)', $assistant->last_name, $assistant->first_name, $assistant->pivot->role ?? '-')))
                        ->implode("\n"))
                    ->label('Assistenti associati'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['patient', 'currentChronicCare', 'followups', 'reminders']))
            ->columns([
                Tables\Columns\TextColumn::make('therapy_title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('patient_full_name')->label('Paziente')
                    ->state(fn (Therapy $record): string => trim(sprintf('%s %s', $record->patient?->last_name, $record->patient?->first_name)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('patient', fn (Builder $patientQuery) => $patientQuery
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%"))),
                Tables\Columns\TextColumn::make('primary_condition')->label('Condizione')->state(fn (Therapy $record): string => self::conditionLabel($record->currentChronicCare?->primary_condition, $record->currentChronicCare?->custom_condition_name)),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('next_due')->label('Prossima scadenza')->state(fn (Therapy $record): string => optional($record->reminders->sortBy('next_due_at')->first()?->next_due_at)?->format('Y-m-d H:i') ?? '-'),
                Tables\Columns\TextColumn::make('last_done')->label('Ultimo completamento')->state(fn (Therapy $record): string => optional($record->followups->sortByDesc('follow_up_date')->first()?->follow_up_date)?->format('Y-m-d') ?? '-'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Attiva',
                    'planned' => 'Pianificata',
                    'completed' => 'Completata',
                    'suspended' => 'Sospesa',
                ]),
                Tables\Filters\SelectFilter::make('patient_id')
                    ->label('Paziente')
                    ->relationship(
                        name: 'patient',
                        titleAttribute: 'last_name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $tenantId = app(CurrentPharmacy::class)->getId();

                            if ($tenantId === null) {
                                return $query->whereRaw('1 = 0');
                            }

                            return $query->where('pharmacy_id', $tenantId);
                        }
                    ),
                Tables\Filters\SelectFilter::make('primary_condition')
                    ->options(fn (): array => self::primaryConditionOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $builder, string $condition): Builder => $builder->whereHas(
                            'chronicCare',
                            fn (Builder $chronicQuery): Builder => $chronicQuery->where('primary_condition', $condition)
                        )
                    )),
                Filter::make('start_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_from'),
                        Forms\Components\DatePicker::make('start_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['start_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['start_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('start_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_pdf')
                    ->label('Genera report PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function (Therapy $record): void {
                        app(GenerateTherapyReportService::class)->handle($record);

                        Notification::make()->success()->title('Report in generazione')->body('Il report è in generazione. Lo troverai nella sezione Report della terapia appena pronto.')->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        $query = parent::getEloquentQuery()
            ->with(['patient', 'currentChronicCare', 'latestConsent', 'latestSurvey']);

        if ($tenantId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('pharmacy_id', $tenantId);
    }


    public static function getRelations(): array
    {
        return [
            TimelineRelationManager::class,
            ChecklistRelationManager::class,
            FollowupsRelationManager::class,
            ChecksRelationManager::class,
            RemindersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapies::route('/'),
            'create' => Pages\CreateTherapy::route('/create'),
            'view' => Pages\ViewTherapy::route('/{record}'),
            'edit' => Pages\EditTherapy::route('/{record}/edit'),
        ];
    }

    /** @return array<int, Forms\Components\Field> */
    private static function patientFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('first_name')->required()->maxLength(100),
            Forms\Components\TextInput::make('last_name')->required()->maxLength(100),
            Forms\Components\DatePicker::make('birth_date'),
            Forms\Components\TextInput::make('codice_fiscale')->maxLength(20),
            Forms\Components\Select::make('gender')->options(['M' => 'M', 'F' => 'F', 'X' => 'X']),
            Forms\Components\TextInput::make('phone')->tel()->maxLength(30),
            Forms\Components\TextInput::make('email')->email()->maxLength(150),
            Forms\Components\Textarea::make('notes')->rows(3),
        ];
    }

    private static function clinicalQuestionsRepeater(string $name, string $label, array $defaultQuestions): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label($label)
            ->helperText('Registra domande cliniche con risposte strutturate. Puoi aggiungere domande personalizzate.')
            ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
            ->schema([
                Forms\Components\TextInput::make('question_text')
                    ->label('Domanda')
                    ->required()
                    ->validationMessages(['required' => 'Inserisci il testo della domanda clinica.'])
                    ->maxLength(255),
                Forms\Components\Hidden::make('question_key'),
                Forms\Components\Toggle::make('is_readonly')->default(false)->hidden(),
                Forms\Components\Select::make('answer_type')
                    ->label('Tipo risposta')
                    ->required()
                    ->live()
                    ->options([
                        'text' => 'Testo',
                        'boolean' => 'Sì / No',
                        'single_choice' => 'Opzioni',
                        'number' => 'Numero',
                    ])
                    ->validationMessages(['required' => 'Seleziona il tipo di risposta.']),
                Forms\Components\TagsInput::make('options')
                    ->label('Opzioni disponibili')
                    ->placeholder('Inserisci le opzioni e premi invio')
                    ->helperText('Compila solo se il tipo risposta è "Opzioni".')
                    ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'single_choice')
                    ->dehydrated(fn (Forms\Get $get): bool => $get('answer_type') === 'single_choice'),
                Forms\Components\Textarea::make('answer_text')
                    ->label('Risposta')
                    ->rows(2)
                    ->placeholder('Inserisci la risposta testuale')
                    ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'text'),
                Forms\Components\TextInput::make('answer_number')
                    ->label('Risposta numerica')
                    ->numeric()
                    ->readOnly(fn (Forms\Get $get): bool => (bool) $get('is_readonly'))
                    ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'number'),
                Forms\Components\Radio::make('answer_boolean')
                    ->label('Risposta')
                    ->options([
                        true => 'Sì',
                        false => 'No',
                    ])
                    ->inline()
                    ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'boolean'),
                Forms\Components\Select::make('answer_choice')
                    ->label('Risposta')
                    ->options(fn (Forms\Get $get): array => collect($get('options') ?? [])->filter()->mapWithKeys(fn (mixed $option): array => [(string) $option => (string) $option])->all())
                    ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'single_choice'),
            ])
            ->default($defaultQuestions)
            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) use ($name): void {
                self::applyBiometricDerivedValues($set, $get($name), $name);
            })
            ->afterStateUpdated(function (Forms\Set $set, ?array $state) use ($name): void {
                self::applyBiometricDerivedValues($set, $state, $name);
            })
            ->addActionLabel('Aggiungi domanda')
            ->reorderable(false)
            ->collapsed()
            ->columnSpanFull();
    }


    private static function applyBiometricDerivedValues(Forms\Set $set, mixed $state, string $path): void
    {
        if (! is_array($state)) {
            return;
        }

        $weight = self::extractBiometricNumericValue($state, 'weight_kg');
        $height = self::extractBiometricNumericValue($state, 'height_cm');

        if ($weight === null || $height === null || $height <= 0.0) {
            self::clearBiometricDerivedValue($set, $state, $path);

            return;
        }

        $heightMeters = $height > 3 ? ($height / 100) : $height;

        if ($heightMeters <= 0.0) {
            self::clearBiometricDerivedValue($set, $state, $path);

            return;
        }

        $bmi = round($weight / ($heightMeters * $heightMeters), 2);

        foreach ($state as $index => $row) {
            if (! is_array($row) || ($row['question_key'] ?? null) !== 'bmi') {
                continue;
            }

            $set(sprintf('%s.%s.answer_number', $path, $index), $bmi);
            break;
        }
    }


    private static function clearBiometricDerivedValue(Forms\Set $set, array $state, string $path): void
    {
        foreach ($state as $index => $row) {
            if (! is_array($row) || ($row['question_key'] ?? null) !== 'bmi') {
                continue;
            }

            $set(sprintf('%s.%s.answer_number', $path, $index), null);
            break;
        }
    }

    private static function extractBiometricNumericValue(array $rows, string $questionKey): ?float
    {
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['question_key'] ?? null) !== $questionKey) {
                continue;
            }

            $value = $row['answer_number'] ?? null;

            if (! is_numeric($value)) {
                return null;
            }

            return (float) $value;
        }

        return null;
    }

    private static function usesTemplateSurveyQuestions(Forms\Get $get): bool
    {
        $primaryCondition = self::surveyContextValue($get, 'primary_condition');
        $surveyLevel = self::surveyContextValue($get, 'survey.level');

        return $primaryCondition !== 'altro'
            && $surveyLevel === 'base';
    }

    private static function syncSurveyAnswersWithTemplates(Forms\Set $set, Forms\Get $get): void
    {
        $primaryCondition = self::surveyContextValue($get, 'primary_condition');
        $customConditionName = self::surveyContextValue($get, 'custom_condition_name');
        $conditionKey = self::effectiveConditionKey($primaryCondition, $customConditionName);
        $mode = self::usesTemplateSurveyQuestions($get) ? 'template' : 'custom';

        $previousMode = self::surveyContextValue($get, 'survey._mode_state');
        $previousCondition = self::surveyContextValue($get, 'survey._condition_state');
        $answers = (array) $get('survey.answers');

        if ($mode === 'template') {
            $templates = self::surveyTemplateRowsByCondition($conditionKey);

            if ($templates === []) {
                $answers = [];
            } else {
                $existingByKey = collect($answers)
                    ->filter(fn (mixed $row): bool => is_array($row) && trim((string) ($row['question_key'] ?? '')) !== '')
                    ->keyBy(fn (array $row): string => (string) $row['question_key']);

                $answers = array_map(function (array $template) use ($existingByKey): array {
                    $existing = $existingByKey->get((string) $template['question_key']);

                    return [
                        'question_key' => (string) $template['question_key'],
                        'answer' => is_array($existing) ? (string) ($existing['answer'] ?? '') : '',
                    ];
                }, $templates);
            }
        } else {
            if ($previousMode === 'template' && $previousCondition === $conditionKey) {
                $answers = self::convertTemplateRowsToCustomRows($answers, self::templateLabelsByKey($conditionKey));
            } elseif ($previousMode === 'template' && $previousCondition !== $conditionKey) {
                $answers = [];
            } elseif ($previousMode === 'custom' && $previousCondition !== '' && $previousCondition !== $conditionKey) {
                $answers = [];
            } else {
                $answers = self::normalizeCustomRows($answers);
            }

            $isCustomPrimary = $primaryCondition === 'altro';

            if ($isCustomPrimary && $conditionKey !== 'altro' && $answers === []) {
                $answers = self::customRowsFromTemplates(self::surveyTemplateRowsByCondition($conditionKey));
            }
        }

        $set('survey.answers', $answers);
        $set('survey._mode_state', $mode);
        $set('survey._condition_state', $conditionKey);
    }

    private static function surveyQuestionOptions(Forms\Get $get): array
    {
        return collect(self::surveyTemplateRows(self::surveyContextValue($get, 'primary_condition'), self::surveyContextValue($get, 'custom_condition_name')))
            ->mapWithKeys(fn (array $question): array => [(string) $question['question_key'] => (string) $question['label']])
            ->all();
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, string> $labelsByKey
     * @return array<int, array<string, string>>
     */
    private static function convertTemplateRowsToCustomRows(array $rows, array $labelsByKey): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && trim((string) ($row['question_key'] ?? '')) !== '')
            ->map(function (array $row) use ($labelsByKey): array {
                $questionKey = trim((string) ($row['question_key'] ?? ''));

                return [
                    'question_key' => $questionKey,
                    'question_label' => $labelsByKey[$questionKey] ?? $questionKey,
                    'answer' => trim((string) ($row['answer'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private static function normalizeCustomRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): ?array {
                $questionLabel = trim((string) ($row['question_label'] ?? ''));
                $questionKey = trim((string) ($row['question_key'] ?? ''));

                if ($questionLabel === '' && $questionKey === '') {
                    return null;
                }

                return [
                    'question_key' => $questionKey,
                    'question_label' => $questionLabel !== '' ? $questionLabel : $questionKey,
                    'answer' => trim((string) ($row['answer'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $templates
     * @return array<int, array<string, string>>
     */
    private static function customRowsFromTemplates(array $templates): array
    {
        return array_map(fn (array $template): array => [
            'question_key' => (string) ($template['question_key'] ?? ''),
            'question_label' => (string) ($template['label'] ?? ''),
            'answer' => '',
        ], $templates);
    }

    /**
     * @return array<string, string>
     */
    private static function templateLabelsByKey(string $conditionKey): array
    {
        return collect(self::surveyTemplateRowsByCondition($conditionKey))
            ->mapWithKeys(fn (array $question): array => [(string) $question['question_key'] => (string) $question['label']])
            ->all();
    }

    private static function surveyContextValue(Forms\Get $get, string $path): string
    {
        foreach ([$path, '../'.$path, '../../'.$path] as $candidate) {
            $value = $get($candidate);

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function surveyTemplateRows(mixed $primaryCondition, mixed $customConditionName): array
    {
        $conditionKey = self::effectiveConditionKey($primaryCondition, $customConditionName);

        return self::surveyTemplateRowsByCondition($conditionKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function surveyTemplateRowsByCondition(string $conditionKey): array
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null || trim($conditionKey) === '') {
            return [];
        }

        return app(\App\Services\Checklist\EnsureTherapyChecklistService::class)
            ->resolveTemplates($tenantId, $conditionKey);
    }

    private static function effectiveConditionKey(mixed $primaryCondition, mixed $customConditionName): string
    {
        if ((string) $primaryCondition === 'altro') {
            return ConditionKeyNormalizer::customKeyFromName((string) $customConditionName);
        }

        return ConditionKeyNormalizer::normalize((string) $primaryCondition);
    }


    private static function conditionLabel(?string $conditionKey, ?string $customLabel = null): string
    {
        if (ConditionKeyNormalizer::isCustom($conditionKey)) {
            return trim((string) $customLabel) !== '' ? (string) $customLabel : 'Patologia custom';
        }

        return ConditionKeyNormalizer::options()[$conditionKey ?? ''] ?? ($conditionKey ?? 'N/D');
    }

    private static function completenessBadgeFromForm(Forms\Get $get): string
    {
        if ($get('ui_care_mode') === 'fidelity') {
            return 'Percorso fidelizzazione: requisiti ridotti.';
        }

        $isComplete = self::isFormComplete($get);

        return $isComplete
            ? '✅ Presa in carico completa'
            : '⚠️ Presa in carico incompleta: verifica condizione clinica e consensi finali.';
    }

    private static function isFormComplete(Forms\Get $get): bool
    {
        $patientId = $get('patient_id');
        $primaryCondition = trim((string) ($get('primary_condition') ?? ''));
        $customConditionName = trim((string) ($get('custom_condition_name') ?? ''));
        $signerName = trim((string) ($get('consent.signer_name') ?? ''));
        $signedAt = $get('consent.signed_at');
        $consentFlags = [
            (bool) $get('consent.consent_care_followup'),
            (bool) $get('consent.consent_contact'),
            (bool) $get('consent.consent_anonymous'),
        ];

        return is_numeric($patientId)
            && $primaryCondition !== ''
            && ($primaryCondition !== 'altro' || $customConditionName !== '')
            && $signerName !== ''
            && $signedAt !== null
            && ! in_array(false, $consentFlags, true);
    }

    private static function isTherapyComplete(Therapy $record): bool
    {
        $record->loadMissing(['patient', 'currentChronicCare', 'latestConsent']);

        $primaryCondition = trim((string) ($record->currentChronicCare?->primary_condition ?? ''));
        $customConditionName = trim((string) ($record->currentChronicCare?->custom_condition_name ?? ''));
        $consent = $record->latestConsent;
        $scopes = collect((array) ($consent?->scopes_json ?? []));

        return $record->patient_id !== null
            && $primaryCondition !== ''
            && ($primaryCondition !== 'altro' || $customConditionName !== '')
            && $consent !== null
            && trim((string) $consent->signer_name) !== ''
            && $consent->signed_at !== null
            && $scopes->contains('clinical_data')
            && $scopes->contains('marketing')
            && $scopes->contains('profiling');
    }

    private static function searchPatients(string $search): array
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            return [];
        }

        return Patient::query()
            ->where('pharmacy_id', $tenantId)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('codice_fiscale', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Patient $patient): array => [
                $patient->id => trim(sprintf('%s %s', $patient->last_name, $patient->first_name)),
            ])
            ->all();
    }

    private static function getPatientLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            return null;
        }

        $patient = Patient::query()
            ->where('pharmacy_id', $tenantId)
            ->find($value);

        return $patient ? trim(sprintf('%s %s', $patient->last_name, $patient->first_name)) : null;
    }

    private static function searchAssistants(string $search): array
    {
        return Assistant::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Assistant $assistant): array => [
                $assistant->id => trim(sprintf('%s %s', $assistant->last_name, $assistant->first_name)),
            ])
            ->all();
    }

    private static function getAssistantLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $assistant = Assistant::query()->find($value);

        return $assistant ? trim(sprintf('%s %s', $assistant->last_name, $assistant->first_name)) : null;
    }

    private static function primaryConditionOptions(): array
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            return [];
        }

        $chronicCareTable = (new TherapyChronicCare())->getTable();
        $therapyTable = (new Therapy())->getTable();

        $keys = TherapyChronicCare::query()
            ->select("{$chronicCareTable}.primary_condition")
            ->join($therapyTable, "{$therapyTable}.id", '=', "{$chronicCareTable}.therapy_id")
            ->where("{$therapyTable}.pharmacy_id", $tenantId)
            ->whereNotNull("{$chronicCareTable}.primary_condition")
            ->distinct()
            ->orderBy("{$chronicCareTable}.primary_condition")
            ->limit(100)
            ->pluck("{$chronicCareTable}.primary_condition")
            ->map(fn (string $condition): string => ConditionKeyNormalizer::normalize($condition))
            ->unique()
            ->values();

        $labels = ConditionKeyNormalizer::options();

        return $keys
            ->mapWithKeys(fn (string $key): array => [$key => ($labels[$key] ?? ucfirst($key))])
            ->all();
    }
}
