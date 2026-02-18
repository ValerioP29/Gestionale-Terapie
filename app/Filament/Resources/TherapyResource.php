<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TherapyResource\Pages;
use App\Models\Assistant;
use App\Models\Patient;
use App\Models\Therapy;
use App\Models\TherapyChecklistQuestion;
use App\Models\TherapyChronicCare;
use App\Services\Patients\CreatePatientService;
use App\Services\Patients\UpdatePatientService;
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
use RuntimeException;

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
                            $patient = app(CreatePatientService::class)->handle($data);

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
                                throw new RuntimeException('Current pharmacy not resolved');
                            }

                            $patient = Patient::query()
                                ->where('pharmacy_id', $tenantId)
                                ->findOrFail((int) $state);

                            app(UpdatePatientService::class)->handle($patient, $data);

                            Notification::make()->success()->title('Paziente aggiornato')->send();

                            return (string) $patient->id;
                        }),
                ]),
                Forms\Components\Wizard\Step::make('Terapia')->schema([
                    Forms\Components\TextInput::make('therapy_title')->required()->maxLength(255),
                    Forms\Components\Select::make('status')->required()->options([
                        'active' => 'Active',
                        'planned' => 'Planned',
                        'completed' => 'Completed',
                        'suspended' => 'Suspended',
                    ])->default('active'),
                    Forms\Components\DatePicker::make('start_date'),
                    Forms\Components\DatePicker::make('end_date')->afterOrEqual('start_date'),
                    Forms\Components\Textarea::make('therapy_description')->label('Note')->rows(4),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Chronic care')->schema([
                    Forms\Components\TextInput::make('primary_condition')->required()->maxLength(50),
                    Forms\Components\TextInput::make('risk_score')->numeric()->minValue(0)->maxValue(100),
                    Forms\Components\DatePicker::make('follow_up_date'),
                    Forms\Components\Textarea::make('notes_initial')->columnSpanFull(),
                    self::jsonRepeater('chronic_care.care_context', 'Care context'),
                    self::jsonRepeater('chronic_care.doctor_info', 'Doctor info'),
                    self::jsonRepeater('chronic_care.general_anamnesis', 'General anamnesis'),
                    self::jsonRepeater('chronic_care.biometric_info', 'Biometric info'),
                    self::jsonRepeater('chronic_care.detailed_intake', 'Detailed intake'),
                    self::jsonRepeater('chronic_care.adherence_base', 'Adherence base'),
                    self::jsonRepeater('chronic_care.flags', 'Flags'),
                    Forms\Components\KeyValue::make('chronic_consent')->label('Consenso chronic care'),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Survey')->schema([
                    Forms\Components\TextInput::make('survey.condition_type')->required()->maxLength(50)->live(),
                    Forms\Components\Select::make('survey.level')->required()->options([
                        'base' => 'Base',
                        'approfondito' => 'Approfondito',
                    ]),
                    Forms\Components\Repeater::make('survey.answers')
                        ->schema([
                            Forms\Components\Select::make('question_key')
                                ->required()
                                ->searchable()
                                ->options(function (Forms\Get $get): array {
                                    $condition = $get('../../survey.condition_type');

                                    return TherapyChecklistQuestion::query()
                                        ->when($condition, fn (Builder $query) => $query->where('condition_key', $condition))
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->limit(100)
                                        ->get()
                                        ->mapWithKeys(fn (TherapyChecklistQuestion $question): array => [
                                            $question->question_key ?? (string) $question->id => $question->question_text,
                                        ])
                                        ->all();
                                }),
                            Forms\Components\TextInput::make('answer')->required(),
                        ])
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Consensi')->schema([
                    Forms\Components\TextInput::make('consent.signer_name')->required()->maxLength(150),
                    Forms\Components\Select::make('consent.signer_relation')->required()->options([
                        'patient' => 'Patient',
                        'caregiver' => 'Caregiver',
                        'familiare' => 'Familiare',
                    ]),
                    Forms\Components\TextInput::make('consent.signer_role')->maxLength(20),
                    Forms\Components\DateTimePicker::make('consent.signed_at'),
                    Forms\Components\Textarea::make('consent.consent_text')->required()->columnSpanFull(),
                    Forms\Components\CheckboxList::make('consent.scopes_json')
                        ->options([
                            'privacy' => 'Privacy',
                            'marketing' => 'Marketing',
                            'profiling' => 'Profiling',
                            'clinical_data' => 'Clinical data',
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('consent.signature_path')
                        ->label('Firma (opzionale)')
                        ->disk(config('filesystems.default'))
                        ->directory('therapy-signatures')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->columnSpanFull(),
                ])->columns(2),
                Forms\Components\Wizard\Step::make('Assistenti/Caregiver')->schema([
                    Forms\Components\Repeater::make('assistants')
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
                            Forms\Components\KeyValue::make('preferences_json')->label('Preferences'),
                            Forms\Components\KeyValue::make('consents_json')->label('Consensi assistente'),
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
                TextEntry::make('status')->badge(),
                TextEntry::make('start_date')->date(),
                TextEntry::make('end_date')->date(),
                TextEntry::make('therapy_description')->label('Note')->columnSpanFull(),
            ])->columns(2),
            InfolistSection::make('Chronic care')->schema([
                TextEntry::make('currentChronicCare.primary_condition'),
                TextEntry::make('currentChronicCare.risk_score'),
                TextEntry::make('currentChronicCare.follow_up_date')->date(),
                TextEntry::make('currentChronicCare.notes_initial')->columnSpanFull(),
            ])->columns(2),
            InfolistSection::make('Survey')->schema([
                TextEntry::make('latestSurvey.condition_type'),
                TextEntry::make('latestSurvey.level')->badge(),
                TextEntry::make('latestSurvey.answers')->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '-')
                    ->columnSpanFull(),
            ])->columns(2),
            InfolistSection::make('Consensi')->schema([
                TextEntry::make('latestConsent.signer_name'),
                TextEntry::make('latestConsent.signer_relation'),
                TextEntry::make('latestConsent.signed_at')->dateTime(),
                TextEntry::make('latestConsent.scopes_json')->formatStateUsing(fn (mixed $state): string => implode(', ', (array) $state)),
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
                Tables\Columns\TextColumn::make('primary_condition')->state(fn (Therapy $record): string => $record->currentChronicCare?->primary_condition ?? '-'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('next_due')->label('Next due')->state(fn (Therapy $record): string => optional($record->reminders->sortBy('next_due_at')->first()?->next_due_at)?->format('Y-m-d H:i') ?? '-'),
                Tables\Columns\TextColumn::make('last_done')->label('Last done')->state(fn (Therapy $record): string => optional($record->followups->sortByDesc('follow_up_date')->first()?->follow_up_date)?->format('Y-m-d') ?? '-'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'planned' => 'Planned',
                    'completed' => 'Completed',
                    'suspended' => 'Suspended',
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
                    ->label('Generate report PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => Notification::make()->warning()->title('Report service non ancora disponibile')->send()),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapies::route('/'),
            'create' => Pages\CreateTherapy::route('/create'),
            'view' => Pages\ViewTherapy::route('/{record}'),
            'edit' => Pages\EditTherapy::route('/{record}/edit'),
            'followups' => Pages\ManageTherapyFollowups::route('/{record}/followups'),
            'reminders' => Pages\ManageTherapyReminders::route('/{record}/reminders'),
            'reports' => Pages\ManageTherapyReports::route('/{record}/reports'),
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
            ->schema([
                Forms\Components\TextInput::make('key')->required(),
                Forms\Components\TextInput::make('value')->required(),
            ])
            ->defaultItems(0)
            ->columnSpanFull();
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

        return TherapyChronicCare::query()
            ->select("{$chronicCareTable}.primary_condition")
            ->join($therapyTable, "{$therapyTable}.id", '=', "{$chronicCareTable}.therapy_id")
            ->where("{$therapyTable}.pharmacy_id", $tenantId)
            ->whereNotNull("{$chronicCareTable}.primary_condition")
            ->distinct()
            ->orderBy("{$chronicCareTable}.primary_condition")
            ->limit(100)
            ->pluck("{$chronicCareTable}.primary_condition", "{$chronicCareTable}.primary_condition")
            ->all();
    }
}
