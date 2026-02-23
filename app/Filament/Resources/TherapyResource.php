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
use App\Models\TherapyChecklistQuestion;
use App\Models\TherapyChronicCare;
use App\Presenters\TherapyPresenter;
use App\Services\Patients\CreatePatientService;
use App\Services\Patients\UpdatePatientService;
use App\Services\Therapies\GenerateTherapyReportService;
use App\Support\ConditionKeyNormalizer;
use App\Tenancy\CurrentPharmacy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
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
                    Forms\Components\TextInput::make('therapy_title')->label('Titolo terapia')->required()->maxLength(255)->helperText('Usa un titolo chiaro, es. Terapia antipertensiva.'),
                    Forms\Components\Select::make('status')->required()->options([
                        'active' => 'Attiva',
                        'planned' => 'Pianificata',
                        'completed' => 'Completata',
                        'suspended' => 'Sospesa',
                    ])->default('active'),
                    Forms\Components\DatePicker::make('start_date')->label('Data inizio'),
                    Forms\Components\DatePicker::make('end_date')->label('Data fine')->afterOrEqual('start_date'),
                    Forms\Components\Textarea::make('therapy_description')->label('Note terapia')->rows(4)->helperText('Note operative visibili al team farmacia.')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') === 'fidelity'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Valutazione iniziale')->description('Inserisci il quadro clinico iniziale del paziente.')->schema([
                    Forms\Components\Select::make('primary_condition')->label('Patologia/condizione principale')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options(ConditionKeyNormalizer::options())->helperText('Seleziona una condizione standardizzata per allineare checklist e report.')->validationMessages(['required' => 'La condizione clinica principale è obbligatoria.'])->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->dehydrateStateUsing(fn (mixed $state, Forms\Get $get): string => $get('ui_care_mode') === 'fidelity' ? 'altro' : ConditionKeyNormalizer::normalize((string) $state)),
                    Forms\Components\TextInput::make('risk_score')->label('Indice di rischio (0-100)')->numeric()->minValue(0)->maxValue(100)->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    Forms\Components\DatePicker::make('follow_up_date')->label('Prossimo follow-up suggerito')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    Forms\Components\Textarea::make('notes_initial')->label('Note cliniche iniziali')->helperText('Riassunto clinico iniziale utile al monitoraggio.')->columnSpanFull()->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                    self::jsonRepeater('chronic_care.care_context', 'Contesto assistenziale'),
                    self::jsonRepeater('chronic_care.doctor_info', 'Informazioni medico curante'),
                    self::jsonRepeater('chronic_care.general_anamnesis', 'Anamnesi generale'),
                    self::jsonRepeater('chronic_care.biometric_info', 'Dati biometrici'),
                    self::jsonRepeater('chronic_care.detailed_intake', 'Dettaglio assunzione terapia'),
                    self::jsonRepeater('chronic_care.adherence_base', 'Valutazione base aderenza'),
                    self::jsonRepeater('chronic_care.flags', 'Segnalazioni cliniche'),
                    Forms\Components\KeyValue::make('chronic_consent')->label('Consenso clinico')->helperText('Usa coppie chiave/valore solo per note interne non strutturate.')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Questionario di aderenza')->description("Compila il questionario iniziale per monitorare l'aderenza.")->schema([
                    Forms\Components\Select::make('survey.condition_type')->label('Condizione di riferimento')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options(ConditionKeyNormalizer::options())->default(fn (Forms\Get $get): ?string => $get('primary_condition'))->live()->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->helperText('Usa la stessa condizione clinica della presa in carico.'),
                    Forms\Components\Select::make('survey.level')->required(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')->options([
                        'base' => 'Base',
                        'approfondito' => 'Approfondito',
                    ]),
                    Forms\Components\Repeater::make('survey.answers')->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
                        ->schema([
                            Forms\Components\Select::make('question_key')
                                ->label('Domanda checklist')
                                ->helperText('Seleziona la domanda da monitorare nel questionario di aderenza.')
                                ->required()
                                ->searchable()
                                ->options(function (Forms\Get $get): array {
                                    $tenantId = app(CurrentPharmacy::class)->getId();

                                    if ($tenantId === null) {
                                        return [];
                                    }

                                    $condition = trim((string) ($get('../../survey.condition_type') ?? ''));

                                    if ($condition === '') {
                                        return [];
                                    }

                                    return TherapyChecklistQuestion::query()
                                        ->where('pharmacy_id', $tenantId)
                                        ->where('condition_key', $condition)
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->limit(100)
                                        ->get()
                                        ->mapWithKeys(fn (TherapyChecklistQuestion $question): array => [
                                            $question->question_key ?? (string) $question->id => $question->label,
                                        ])
                                        ->all();
                                }),
                            Forms\Components\TextInput::make('answer')->label('Risposta')->required()->helperText('Inserisci una risposta comprensibile per il report.'),
                        ])
                        ->defaultItems(0)
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
                    Forms\Components\Textarea::make('consent.consent_text')->label('Testo consenso')->required()->columnSpanFull(),
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
                                'phone' => 'Phone',
                                'email' => 'Email',
                                'whatsapp' => 'WhatsApp',
                            ]),
                            Forms\Components\KeyValue::make('preferences_json')->label('Preferenze contatto')->helperText('Esempio: fascia_oraria => mattina.'),
                            Forms\Components\KeyValue::make('consents_json')->label('Consensi assistente')->helperText('Solo informazioni essenziali, evita dati sensibili non necessari.'),
                        ])
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
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
                TextEntry::make('currentChronicCare.primary_condition')->label('Condizione principale')->formatStateUsing(fn (?string $state): string => ConditionKeyNormalizer::options()[$state ?? ''] ?? ($state ?? 'N/D')),
                TextEntry::make('currentChronicCare.risk_score'),
                TextEntry::make('currentChronicCare.follow_up_date')->date(),
                TextEntry::make('currentChronicCare.notes_initial')->columnSpanFull(),
            ])->columns(2),
            InfolistSection::make('Questionario di aderenza')->schema([
                TextEntry::make('latestSurvey.condition_type'),
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
                Tables\Columns\TextColumn::make('primary_condition')->label('Condizione')->state(fn (Therapy $record): string => ConditionKeyNormalizer::options()[$record->currentChronicCare?->primary_condition ?? ''] ?? ($record->currentChronicCare?->primary_condition ?? '-')),
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

                        Notification::make()->success()->title('Generazione report avviata')->send();
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

    private static function jsonRepeater(string $name, string $label): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label($label)
            ->helperText('Compila solo le informazioni realmente utili al monitoraggio clinico.')
            ->visible(fn (Forms\Get $get): bool => $get('ui_care_mode') !== 'fidelity')
            ->schema([
                Forms\Components\TextInput::make('key')->required(),
                Forms\Components\TextInput::make('value')->required(),
            ])
            ->defaultItems(0)
            ->columnSpanFull();
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
        $signerName = trim((string) ($get('consent.signer_name') ?? ''));
        $signedAt = $get('consent.signed_at');
        $consentFlags = [
            (bool) $get('consent.consent_care_followup'),
            (bool) $get('consent.consent_contact'),
            (bool) $get('consent.consent_anonymous'),
        ];

        return is_numeric($patientId)
            && $primaryCondition !== ''
            && $primaryCondition !== 'altro'
            && $signerName !== ''
            && $signedAt !== null
            && ! in_array(false, $consentFlags, true);
    }

    private static function isTherapyComplete(Therapy $record): bool
    {
        $record->loadMissing(['patient', 'currentChronicCare', 'latestConsent']);

        $primaryCondition = trim((string) ($record->currentChronicCare?->primary_condition ?? ''));
        $consent = $record->latestConsent;
        $scopes = collect((array) ($consent?->scopes_json ?? []));

        return $record->patient_id !== null
            && $primaryCondition !== ''
            && $primaryCondition !== 'altro'
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
