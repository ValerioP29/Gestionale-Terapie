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
                Forms\Components\Wizard\Step::make('Valutazione iniziale')->description('Step 3: medico curante/specialista e dati clinici generali.')->schema([
                    Forms\Components\Select::make('primary_condition')->label('Patologia/condizione principale')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options(ConditionKeyNormalizer::options())->helperText('La patologia selezionata definisce il preset del questionario approfondito.')->validationMessages(['required' => 'La condizione clinica principale è obbligatoria.'])->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->live()->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            self::syncSurveyAnswersWithTemplates($set, $get);
                            self::syncInitialAssessmentGroupsWithCondition($set, $get);
                        })->dehydrateStateUsing(fn (mixed $state, Forms\Get $get): string => self::effectiveConditionKey($state, $get('custom_condition_name'))),
                    Forms\Components\TextInput::make('custom_condition_name')
                        ->label('Nome patologia custom')
                        ->placeholder('Es. carcinoma mammario')
                        ->maxLength(120)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get): void {
                            self::syncSurveyAnswersWithTemplates($set, $get);
                            self::syncInitialAssessmentGroupsWithCondition($set, $get);
                        })
                        ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && $get('primary_condition') === 'altro')
                        ->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && $get('primary_condition') === 'altro')
                        ->validationMessages(['required' => 'Inserisci il nome della patologia custom.']),
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
                    Forms\Components\TextInput::make('risk_score')->label('Indice di rischio (0-100)')->numeric()->minValue(0)->maxValue(100)->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->validationMessages([
                        'numeric' => 'L\'indice di rischio deve essere numerico.',
                        'min' => 'L\'indice di rischio non può essere inferiore a 0.',
                        'max' => 'L\'indice di rischio non può superare 100.',
                    ]),
                    Forms\Components\DatePicker::make('follow_up_date')->label('Prossimo follow-up suggerito')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    Forms\Components\Textarea::make('notes_initial')->label('Note cliniche iniziali')->helperText('Riassunto clinico iniziale utile al monitoraggio.')->columnSpanFull()->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Questionario base iniziale')->description('Step 4: sezioni base predefinite + sezioni custom.')->schema([
                    Forms\Components\Placeholder::make('survey_base_hint')
                        ->label('Questionario base')
                        ->content('Compila e personalizza il questionario base: puoi aggiungere sezioni e domande, modificarle, eliminarle e riordinarle.')
                        ->columnSpanFull(),
                    self::questionnaireSectionsBuilder('survey.base_sections', 'Sezioni questionario base', self::defaultBaseQuestionnaireSections()),
                ])->columns(1),
                Forms\Components\Wizard\Step::make('Approfondito')->description('Step 5: questionario approfondito custom usato nei check periodici.')->schema([
                    Forms\Components\Placeholder::make('survey_deep_hint')
                        ->label('Questionario approfondito')
                        ->content('Costruisci liberamente sezioni e domande custom: saranno usate per i check periodici.')
                        ->columnSpanFull(),
                    self::questionnaireSectionsBuilder('survey.approfondito_sections', 'Sezioni approfondito', []),
                ])->columns(1),
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
                    Forms\Components\Repeater::make('assistants')->helperText('Associa un assistente esistente oppure creane uno nuovo.')
                        ->schema([
                            Forms\Components\Select::make('assistant_id')
                                ->label('Assistente')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => self::searchAssistants($search))
                                ->getOptionLabelUsing(fn ($value): ?string => self::getAssistantLabel($value))
                                ->createOptionForm(self::assistantFormSchema())
                                ->createOptionUsing(function (array $data): int {
                                    $tenantId = app(CurrentPharmacy::class)->getId();

                                    if ($tenantId === null) {
                                        throw ValidationException::withMessages([
                                            'assistants' => 'Farmacia corrente non risolta. Riprova dopo aver selezionato la farmacia.',
                                        ]);
                                    }

                                    $assistant = Assistant::query()->create(array_merge($data, ['pharma_id' => $tenantId]));

                                    Notification::make()->success()->title('Assistente creato')->send();

                                    return $assistant->id;
                                }),
                            Forms\Components\Select::make('role')->label('Ruolo nel percorso terapeutico')->required()->options([
                                'caregiver' => 'Caregiver',
                                'familiare' => 'Familiare',
                            ]),
                            Forms\Components\Radio::make('pref_contact_phone')->label('Preferisce essere contattato telefonicamente?')->options(['si' => 'Sì', 'no' => 'No'])->inline()->default('si')->required(),
                            Forms\Components\Radio::make('pref_contact_email')->label('Preferisce essere contattato via email?')->options(['si' => 'Sì', 'no' => 'No'])->inline()->default('no')->required(),
                            Forms\Components\Radio::make('pref_contact_sms_whatsapp')->label('Preferisce essere contattato via SMS/WhatsApp?')->options(['si' => 'Sì', 'no' => 'No'])->inline()->default('no')->required(),
                            Forms\Components\Radio::make('consent_therapy_contact')->label('Acconsente a essere contattato per comunicazioni relative alla terapia?')->options(['si' => 'Sì', 'no' => 'No'])->inline()->default('si')->required(),
                            Forms\Components\Radio::make('consent_data_processing')->label('Acconsente al trattamento dei dati necessari alla gestione della terapia?')->options(['si' => 'Sì', 'no' => 'No'])->inline()->default('si')->required(),
                        ])
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
            ])
                ->nextAction(fn (Action $action): Action => $action->label('Avanti'))
                ->previousAction(fn (Action $action): Action => $action->label('Indietro'))
                ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="fi-btn fi-btn-size-lg fi-btn-color-primary fi-color-custom fi-ac-action fi-ac-btn-action font-semibold shadow-sm hover:shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"><span class="fi-btn-label">Salva terapia</span></button>'))
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


    /** @return array<int, Forms\Components\Component> */
    private static function questionBuilderSchema(): array
    {
        return [
            Forms\Components\Hidden::make('question_key'),
            Forms\Components\Hidden::make('answer'),
            Forms\Components\TextInput::make('question_label')->label('Testo domanda')->required()->maxLength(255),
            Forms\Components\Select::make('input_type')->label('Tipo risposta')->required()->options([
                'text' => 'Testo breve',
                'text_long' => 'Testo lungo',
                'number' => 'Numero',
                'date' => 'Data',
                'boolean' => 'Sì/No',
                'select' => 'Scelta singola',
                'multiple_choice' => 'Scelta multipla',
            ])->live(),
            Forms\Components\TagsInput::make('options_json')
                ->label('Opzioni')
                ->visible(fn (Forms\Get $get): bool => in_array((string) $get('input_type'), ['select', 'multiple_choice'], true)),
            Forms\Components\TextInput::make('ui_answer_text')
                ->label('Risposta')
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'text' && (string) $get('question_key') !== 'bmi'),
            Forms\Components\TextInput::make('ui_answer_number')
                ->label('Risposta')
                ->numeric()
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'number' && (string) $get('question_key') !== 'bmi'),
            Forms\Components\DatePicker::make('ui_answer_date')
                ->label('Risposta')
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'date' && (string) $get('question_key') !== 'bmi'),
            Forms\Components\Textarea::make('ui_answer_long')
                ->label('Risposta')
                ->rows(3)
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'text_long'),
            Forms\Components\Radio::make('ui_answer_boolean')
                ->label('Risposta')
                ->options(['1' => 'Sì', '0' => 'No'])
                ->inline()
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'boolean'),
            Forms\Components\Select::make('ui_answer_select')
                ->label('Risposta')
                ->options(fn (Forms\Get $get): array => collect((array) ($get('options_json') ?? []))
                    ->filter()
                    ->mapWithKeys(fn (mixed $option): array => [(string) $option => (string) $option])
                    ->all())
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'select'),
            Forms\Components\CheckboxList::make('ui_answer_multiple')
                ->label('Risposta')
                ->options(fn (Forms\Get $get): array => collect((array) ($get('options_json') ?? []))
                    ->filter()
                    ->mapWithKeys(fn (mixed $option): array => [(string) $option => (string) $option])
                    ->all())
                ->visible(fn (Forms\Get $get): bool => (string) $get('input_type') === 'multiple_choice'),
            Forms\Components\Placeholder::make('bmi_display')
                ->label('BMI (calcolato)')
                ->content(fn (Forms\Get $get): string => ($value = $get('answer')) === null || $value === '' ? '—' : (string) $value)
                ->visible(fn (Forms\Get $get): bool => (string) $get('question_key') === 'bmi'),
            Forms\Components\Textarea::make('answer_detail')
                ->label('Dettaglio (facoltativo)')
                ->rows(2),
            Forms\Components\Hidden::make('sort_order')->default(10),
        ];
    }

    private static function questionnaireSectionsBuilder(string $name, string $label, array $default): Forms\Components\Section
    {
        return Forms\Components\Section::make($label)
            ->schema([
                Forms\Components\Repeater::make($name)
                    ->default($default)
                    ->schema([
                        Forms\Components\TextInput::make('section')
                            ->label('Sezione')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Repeater::make('questions')
                            ->schema(self::questionBuilderSchema())
                            ->reorderableWithButtons()
                            ->reorderable()
                            ->addActionLabel('Aggiungi domanda')
                            ->default([])
                            ->columnSpanFull(),
                    ])
                    ->reorderableWithButtons()
                    ->reorderable()
                    ->addActionLabel('Aggiungi sezione')
                    ->afterStateHydrated(function (Forms\Set $set, ?array $state) use ($name): void {
                        $processed = self::prepareSectionState($state, $name === 'survey.base_sections');
                        $set($name, $processed);
                    })
                    ->afterStateUpdated(function (Forms\Set $set, ?array $state) use ($name): void {
                        $processed = self::prepareSectionState($state, $name === 'survey.base_sections');

                        if ($processed === (array) $state) {
                            return;
                        }

                        $set($name, $processed);
                    })
                    ->dehydrateStateUsing(function (?array $state) use ($name): array {
                        return self::prepareSectionState($state, $name === 'survey.base_sections');
                    })
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<int, array<string, mixed>> */
    private static function prepareSectionState(?array $state, bool $computeBiometricDerived): array
    {
        $sections = self::normalizeQuestionnaireSections($state);

        if ($computeBiometricDerived) {
            $sections = self::applyBaseSectionDerivedValues($sections);
        }

        return self::withComputedSortOrder($sections);
    }

    /** @return array<int, array<string, mixed>> */
    private static function normalizeQuestionnaireSections(?array $state): array
    {
        $rows = collect((array) $state)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values();

        $looksLegacy = $rows->isNotEmpty() && $rows->contains(fn (array $row): bool => array_key_exists('question_label', $row));

        if ($looksLegacy) {
            return $rows
                ->groupBy(fn (array $row): string => trim((string) ($row['section'] ?? '')) ?: 'Sezione personalizzata')
                ->map(fn ($items, string $section): array => [
                    'section' => $section,
                    'questions' => collect($items)->map(function (array $item): array {
                        return [
                            'question_key' => $item['question_key'] ?? null,
                            'question_label' => $item['question_label'] ?? '',
                            'input_type' => $item['input_type'] ?? 'text',
                            'options_json' => $item['options_json'] ?? null,
                            'answer' => $item['answer'] ?? null,
                            'answer_detail' => $item['answer_detail'] ?? null,
                            'sort_order' => $item['sort_order'] ?? null,
                            'ui_answer_text' => null,
                            'ui_answer_long' => null,
                            'ui_answer_number' => null,
                            'ui_answer_date' => null,
                            'ui_answer_boolean' => null,
                            'ui_answer_select' => null,
                            'ui_answer_multiple' => null,
                        ];
                    })->values()->all(),
                ])
                ->values()
                ->all();
        }

        return $rows
            ->map(function (array $row): array {
                return [
                    'section' => trim((string) ($row['section'] ?? '')),
                    'questions' => collect((array) ($row['questions'] ?? []))
                        ->filter(fn (mixed $question): bool => is_array($question) && trim((string) ($question['question_label'] ?? '')) !== '')
                        ->map(fn (array $question): array => self::normalizeQuestionRow($question))
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $section): bool => $section['section'] !== '' || $section['questions'] !== [])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $question @return array<string, mixed> */
    private static function normalizeQuestionRow(array $question): array
    {
        $inputType = (string) ($question['input_type'] ?? 'text');
        $answer = self::resolveAnswerFromQuestionState($question, $inputType);

        return [
            'question_key' => $question['question_key'] ?? null,
            'question_label' => trim((string) ($question['question_label'] ?? '')),
            'input_type' => $inputType,
            'options_json' => $question['options_json'] ?? null,
            'answer' => $answer,
            'answer_detail' => $question['answer_detail'] ?? null,
            'sort_order' => $question['sort_order'] ?? null,
            'ui_answer_text' => $inputType === 'text' ? (string) ($answer ?? '') : (string) ($question['ui_answer_text'] ?? ''),
            'ui_answer_long' => $inputType === 'text_long' ? (string) ($answer ?? '') : (string) ($question['ui_answer_long'] ?? ''),
            'ui_answer_number' => $inputType === 'number' ? $answer : ($question['ui_answer_number'] ?? null),
            'ui_answer_date' => $inputType === 'date' ? $answer : ($question['ui_answer_date'] ?? null),
            'ui_answer_boolean' => $inputType === 'boolean' ? $answer : ($question['ui_answer_boolean'] ?? null),
            'ui_answer_select' => $inputType === 'select' ? $answer : ($question['ui_answer_select'] ?? null),
            'ui_answer_multiple' => $inputType === 'multiple_choice' ? (array) ($answer ?? []) : ($question['ui_answer_multiple'] ?? null),
        ];
    }

    /** @param array<string, mixed> $question */
    private static function resolveAnswerFromQuestionState(array $question, string $inputType): mixed
    {
        $storedAnswer = $question['answer'] ?? null;

        $answerByType = match ($inputType) {
            'text' => $question['ui_answer_text'] ?? $storedAnswer,
            'text_long' => $question['ui_answer_long'] ?? $storedAnswer,
            'number' => $question['ui_answer_number'] ?? $storedAnswer,
            'date' => $question['ui_answer_date'] ?? $storedAnswer,
            'boolean' => $question['ui_answer_boolean'] ?? $storedAnswer,
            'select' => $question['ui_answer_select'] ?? $storedAnswer,
            'multiple_choice' => $question['ui_answer_multiple'] ?? $storedAnswer,
            default => $storedAnswer,
        };

        return self::normalizeAnswerScalar($answerByType, $inputType);
    }

    private static function normalizeAnswerScalar(mixed $value, string $inputType): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($inputType === 'multiple_choice') {
            $items = collect((array) $value)
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();

            return $items === [] ? null : $items;
        }

        if ($inputType === 'number') {
            if ($value === '') {
                return null;
            }

            return is_numeric($value) ? (float) $value : $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }

    /** @return array<int, array<string, mixed>> */
    private static function defaultBaseQuestionnaireSections(): array
    {
        return [
            [
                'section' => 'Dati biometrici',
                'questions' => [
                    ['question_key' => 'weight_kg', 'question_label' => 'Peso (kg)', 'input_type' => 'number', 'answer' => null],
                    ['question_key' => 'height_cm', 'question_label' => 'Altezza (cm)', 'input_type' => 'number', 'answer' => null],
                    ['question_key' => 'bmi', 'question_label' => 'BMI (calcolato da peso/altezza)', 'input_type' => 'number', 'answer' => null],
                ],
            ],
            [
                'section' => 'Anamnesi generale',
                'questions' => [
                    ['question_key' => 'allergie_note', 'question_label' => 'Il paziente presenta allergie note?', 'input_type' => 'select', 'options_json' => ['Sì', 'No'], 'answer' => null, 'answer_detail' => null],
                    ['question_key' => 'fumo', 'question_label' => 'Il paziente fuma?', 'input_type' => 'select', 'options_json' => ['Sì', 'No', 'Ex fumatore'], 'answer' => null],
                    ['question_key' => 'alcol', 'question_label' => 'Il paziente consuma alcolici abitualmente?', 'input_type' => 'select', 'options_json' => ['Mai', 'Occasionalmente', 'Regolarmente'], 'answer' => null],
                    ['question_key' => 'attivita_fisica', 'question_label' => 'Il paziente pratica attività fisica?', 'input_type' => 'select', 'options_json' => ['Sì, regolare', 'Sì, saltuaria', 'No'], 'answer' => null],
                    ['question_key' => 'altre_patologie', 'question_label' => 'Sono presenti altre patologie rilevanti?', 'input_type' => 'text_long', 'answer' => null],
                ],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $sections @return array<int, array<string, mixed>> */
    private static function withComputedSortOrder(array $sections): array
    {
        $globalIndex = 1;

        return array_map(function (array $section, int $sectionIndex) use (&$globalIndex): array {
            $section['sort_order'] = ($sectionIndex + 1) * 1000;
            $section['questions'] = array_map(function (mixed $question, int $questionIndex) use (&$globalIndex): mixed {
                if (! is_array($question)) {
                    return $question;
                }

                $question['sort_order'] = $globalIndex * 10;
                $question['section_sort_order'] = ($questionIndex + 1) * 10;
                $globalIndex++;

                return $question;
            }, (array) ($section['questions'] ?? []), array_keys((array) ($section['questions'] ?? [])));

            return $section;
        }, $sections, array_keys($sections));
    }

    /** @param array<int, array<string, mixed>> $sections @return array<int, array<string, mixed>> */
    private static function applyBaseSectionDerivedValues(array $sections): array
    {
        $weight = null;
        $height = null;

        foreach ($sections as $section) {
            foreach ((array) ($section['questions'] ?? []) as $question) {
                if (! is_array($question)) {
                    continue;
                }

                $key = (string) ($question['question_key'] ?? '');

                if ($key === 'weight_kg' && is_numeric($question['answer'] ?? null)) {
                    $weight = (float) $question['answer'];
                }

                if ($key === 'height_cm' && is_numeric($question['answer'] ?? null)) {
                    $height = (float) $question['answer'];
                }
            }
        }

        $bmi = null;
        if ($weight !== null && $height !== null && $height > 0.0) {
            $heightMeters = $height > 3 ? ($height / 100) : $height;
            if ($heightMeters > 0.0) {
                $bmi = round($weight / ($heightMeters * $heightMeters), 2);
            }
        }

        return array_map(function (array $section) use ($bmi): array {
            $questions = array_map(function (mixed $question) use ($bmi): mixed {
                if (! is_array($question)) {
                    return $question;
                }

                if ((string) ($question['question_key'] ?? '') === 'bmi') {
                    $question['answer'] = $bmi;
                }

                $answer = $question['answer'] ?? null;
                $question['ui_answer_text'] = (string) (($question['input_type'] ?? '') === 'text' ? ($answer ?? '') : ($question['ui_answer_text'] ?? ''));
                $question['ui_answer_long'] = (string) (($question['input_type'] ?? '') === 'text_long' ? ($answer ?? '') : ($question['ui_answer_long'] ?? ''));
                $question['ui_answer_number'] = ($question['input_type'] ?? '') === 'number' ? $answer : ($question['ui_answer_number'] ?? null);
                $question['ui_answer_date'] = ($question['input_type'] ?? '') === 'date' ? $answer : ($question['ui_answer_date'] ?? null);
                $question['ui_answer_boolean'] = ($question['input_type'] ?? '') === 'boolean' ? $answer : ($question['ui_answer_boolean'] ?? null);
                $question['ui_answer_select'] = ($question['input_type'] ?? '') === 'select' ? $answer : ($question['ui_answer_select'] ?? null);
                $question['ui_answer_multiple'] = ($question['input_type'] ?? '') === 'multiple_choice' ? (array) ($answer ?? []) : ($question['ui_answer_multiple'] ?? null);

                return $question;
            }, (array) ($section['questions'] ?? []));

            $section['questions'] = $questions;

            return $section;
        }, $sections);
    }


    /** @return array<int, Forms\Components\Field> */
    private static function assistantFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('first_name')->label('Nome')->required()->maxLength(100),
            Forms\Components\TextInput::make('last_name')->label('Cognome')->required()->maxLength(100),
            Forms\Components\TextInput::make('phone')->label('Telefono')->tel()->maxLength(30),
            Forms\Components\TextInput::make('email')->label('Email')->email()->maxLength(150),
        ];
    }

    private static function clinicalQuestionsRepeater(string $name, string $label, array $defaultQuestions, ?string $groupKey = null): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label($label)
            ->helperText('Consulta domanda + risposta. Per le domande precompilate usa “Modifica” per aggiornare il template.')
            ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && self::isInitialAssessmentGroupVisible($get, $groupKey))
            ->dehydrated(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity' && self::isInitialAssessmentGroupVisible($get, $groupKey))
            ->itemLabel(fn (array $state): ?string => trim((string) ($state['question_text'] ?? '')) !== '' ? (string) $state['question_text'] : 'Nuova domanda (clicca Modifica)')
            ->schema([
                Forms\Components\TextInput::make('question_text')
                    ->label('Domanda')
                    ->required()
                    ->disabled()
                    ->dehydrated()
                    ->validationMessages(['required' => 'Inserisci il testo della domanda clinica.'])
                    ->maxLength(255),
                Forms\Components\Hidden::make('question_key'),
                Forms\Components\Toggle::make('is_readonly')->default(false)->hidden(),
                Forms\Components\Select::make('answer_type')
                    ->label('Tipo risposta')
                    ->required()
                    ->disabled()
                    ->dehydrated()
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
                    ->disabled()
                    ->dehydrated()
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
            ->extraItemActions([
                Action::make('edit_template_question')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->fillForm(fn (array $arguments, Forms\Components\Repeater $component): array => $component->getState()[$arguments['item']] ?? [])
                    ->form([
                        Forms\Components\TextInput::make('question_text')->label('Testo domanda')->required()->maxLength(255),
                        Forms\Components\Select::make('answer_type')
                            ->label('Tipo risposta')
                            ->required()
                            ->live()
                            ->options([
                                'text' => 'Testo',
                                'boolean' => 'Sì / No',
                                'single_choice' => 'Opzioni',
                                'number' => 'Numero',
                            ]),
                        Forms\Components\TagsInput::make('options')
                            ->label('Opzioni disponibili')
                            ->placeholder('Inserisci le opzioni e premi invio')
                            ->visible(fn (Forms\Get $get): bool => $get('answer_type') === 'single_choice')
                            ->dehydrated(fn (Forms\Get $get): bool => $get('answer_type') === 'single_choice'),
                    ])
                    ->action(function (array $data, array $arguments, Forms\Components\Repeater $component): void {
                        $state = $component->getState();
                        $item = $arguments['item'] ?? null;

                        if ($item === null || ! isset($state[$item])) {
                            return;
                        }

                        $state[$item]['question_text'] = $data['question_text'];
                        $state[$item]['answer_type'] = $data['answer_type'];
                        $state[$item]['options'] = $data['answer_type'] === 'single_choice' ? array_values(array_filter((array) ($data['options'] ?? []))) : null;
                        $component->state($state);
                    }),
            ])
            ->default($defaultQuestions)
            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) use ($name, $groupKey): void {
                if (! self::isInitialAssessmentGroupVisible($get, $groupKey)) {
                    $set($name, []);

                    return;
                }

                self::applyBiometricDerivedValues($set, $get($name), $name);
            })
            ->afterStateUpdated(function (Forms\Set $set, ?array $state) use ($name): void {
                self::applyBiometricDerivedValues($set, $state, $name);
            })
            ->addActionLabel('Aggiungi domanda (poi clicca Modifica)')
            ->reorderable(false)
            ->collapsed()
            ->collapseAllAction(fn (Action $action): Action => $action->label('Comprimi tutto'))
            ->expandAllAction(fn (Action $action): Action => $action->label('Espandi tutto'))
            ->columnSpanFull();
    }




    private static function syncInitialAssessmentGroupsWithCondition(Forms\Set $set, Forms\Get $get): void
    {
        $condition = self::surveyContextValue($get, 'primary_condition');

        foreach (self::initialAssessmentGroupPaths() as $groupKey => $path) {
            if (self::isGroupVisibleForCondition($condition, $groupKey)) {
                continue;
            }

            $set($path, []);
        }
    }

    /** @return array<string, string> */
    private static function initialAssessmentGroupPaths(): array
    {
        return [
            'care_context' => 'chronic_care.care_context',
            'general_anamnesis' => 'chronic_care.general_anamnesis',
            'biometric_info' => 'chronic_care.biometric_info',
            'detailed_intake' => 'chronic_care.detailed_intake',
            'adherence_base' => 'chronic_care.adherence_base',
            'flags' => 'chronic_care.flags',
            'custom_deep_dive' => 'chronic_care.custom_deep_dive',
        ];
    }

    private static function isGroupVisibleForCondition(string $condition, string $groupKey): bool
    {
        if ($condition === '') {
            return false;
        }

        if ($condition === 'altro') {
            return $groupKey === 'custom_deep_dive';
        }

        if ($groupKey === 'custom_deep_dive') {
            return false;
        }

        $presetGroupsByCondition = [
            'diabete' => ['general_anamnesis', 'biometric_info', 'detailed_intake', 'adherence_base', 'flags'],
            'bpco' => ['care_context', 'general_anamnesis', 'detailed_intake', 'adherence_base', 'flags'],
            'ipertensione' => ['care_context', 'biometric_info', 'detailed_intake', 'adherence_base', 'flags'],
            'dislipidemia' => ['general_anamnesis', 'biometric_info', 'detailed_intake', 'adherence_base'],
        ];

        $groups = $presetGroupsByCondition[$condition]
            ?? ['care_context', 'general_anamnesis', 'biometric_info', 'detailed_intake', 'adherence_base', 'flags'];

        return in_array($groupKey, $groups, true);
    }

    private static function isInitialAssessmentGroupVisible(Forms\Get $get, ?string $groupKey): bool
    {
        if ($groupKey === null) {
            return true;
        }

        return self::isGroupVisibleForCondition(self::surveyContextValue($get, 'primary_condition'), $groupKey);
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
        return self::surveyContextValue($get, 'survey.level') === 'base';
    }

    private static function syncSurveyAnswersWithTemplates(Forms\Set $set, Forms\Get $get): void
    {
        $primaryCondition = self::surveyContextValue($get, 'primary_condition');
        $customConditionName = self::surveyContextValue($get, 'custom_condition_name');
        $conditionKey = self::effectiveConditionKey($primaryCondition, $customConditionName);
        $answers = (array) $get('survey.answers');
        $templates = self::baseSurveyTemplateRows();

        if ($templates === []) {
            $set('survey.answers', []);
            $set('survey._mode_state', 'template');
            $set('survey._condition_state', $conditionKey);

            return;
        }

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

        $set('survey.answers', $answers);
        $set('survey._mode_state', 'template');
        $set('survey._condition_state', $conditionKey);
    }

    private static function surveyQuestionOptions(Forms\Get $get): array
    {
        return collect(self::baseSurveyTemplateRows())
            ->mapWithKeys(fn (array $question): array => [(string) $question['question_key'] => (string) $question['label']])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function baseSurveyTemplateRows(): array
    {
        return collect(config('therapy_clinical_questions.adherence_base', []))
            ->map(fn (array $question): array => [
                'question_key' => (string) ($question['question_key'] ?? ''),
                'label' => (string) ($question['question_text'] ?? ''),
            ])
            ->filter(fn (array $question): bool => $question['question_key'] !== '' && $question['label'] !== '')
            ->values()
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
