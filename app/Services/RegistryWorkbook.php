<?php

namespace App\Services;

use App\Enums\DeliveryMode;
use App\Enums\EyeSide;
use App\Enums\ManagementPlan;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\PlusDisease;
use App\Enums\RespiratorySupport;
use App\Enums\RopStatus;
use App\Enums\RopType;
use App\Enums\Sex;
use App\Enums\Stage;
use App\Enums\TreatmentType;
use App\Enums\VisitKind;
use App\Enums\Zone;
use App\Models\Patient;
use App\Models\ReviewItem;
use App\Models\Treatment;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the registry as an Excel workbook.
 *
 * The first sheet keeps the layout of the original paper-transcription workbook: one row
 * per baby, with every visit across the columns and whole-row highlighting for completed
 * treatment. Further sheets hold the same data in long form (one row per visit and per
 * treatment), which is what statistics software expects, plus statistics and the review log.
 */
class RegistryWorkbook
{
    protected const DATE_FORMAT = 'yyyy-mm-dd';

    protected const INJECTION_FILL = 'E4DFEC';

    protected const LASER_FILL = 'FCE4D6';

    protected const UNVERIFIED_FILL = 'EDEDED';

    protected Writer $writer;

    protected Style $title;

    protected Style $group;

    protected Style $heading;

    protected Style $plain;

    protected Style $date;

    protected Style $muted;

    public function __construct(protected RegistryStatistics $statistics)
    {
        $this->title = new Style(fontBold: true, fontSize: 14, fontName: 'Calibri');
        $this->group = new Style(fontBold: true, fontSize: 11, fontColor: 'FFFFFF', fontName: 'Calibri', backgroundColor: '1F6F8B');
        $this->heading = new Style(fontBold: true, fontSize: 11, fontName: 'Calibri', shouldWrapText: true, backgroundColor: 'D9E8EE');
        $this->plain = new Style(fontSize: 11, fontName: 'Calibri');
        $this->date = new Style(fontSize: 11, fontName: 'Calibri', format: self::DATE_FORMAT);
        $this->muted = new Style(fontItalic: true, fontSize: 10, fontColor: '6B7280', fontName: 'Calibri');
    }

    /**
     * Write the workbook for the given patients to the path.
     *
     * @param  Collection<int, Patient>  $patients
     */
    public function write(Collection $patients, string $path): void
    {
        $patients->load(['visits', 'treatments', 'reviewItems']);

        $this->writer = new Writer(new Options(FALLBACK_STYLE: $this->plain));
        $this->writer->openToFile($path);

        $this->registrySheet($patients);
        $this->visitsSheet($patients);
        $this->treatmentsSheet($patients);
        $this->statisticsSheet();
        $this->reviewSheet($patients);
        $this->listsSheet();
        $this->aboutSheet($patients);

        $this->writer->close();
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    protected function registrySheet(Collection $patients): void
    {
        $this->sheet($this->text('ROP Registry'), freezeRow: 3, freezeColumn: 'C', first: true);

        $patientColumns = [
            $this->text('Clinic file no.'), $this->text('Baby full name'), $this->text('Mother name'), $this->text('Date of birth'), $this->text('Sex'),
            $this->text('Birth weight (g)'), $this->text('GA weeks'), $this->text('GA days'), $this->text('Multiplicity'),
            $this->text('Delivery mode'), $this->text('Referral date'), $this->text('Referring doctor'), $this->text('NICU stay (days)'),
            $this->text('Respiratory support'), $this->text('Support duration (days)'), $this->text('O₂ days'), $this->text('CPAP days'),
            $this->text('Systemic illness'), $this->text('Parent phone'), $this->text('Address'), $this->text('Notes'),
            $this->text('Status'), $this->text('Identity confirmed'),
        ];

        $summaryColumns = [
            $this->text('Examinations'), $this->text('First visit'), $this->text('Last visit'), $this->text('Next appointment'),
            $this->text('Documented ROP'), $this->text('Highest stage'), $this->text('Type 1 ROP'), $this->text('Injection performed'),
            $this->text('Laser performed'), $this->text('Last injection'), $this->text('Treatment pending'), $this->text('Open review items'),
        ];

        $visitColumns = [
            $this->text('Date'), $this->text('Record type'), $this->text('PMA'),
            $this->text('Right eye').' · '.$this->text('Zone'), $this->text('Right eye').' · '.$this->text('Stage'),
            $this->text('Right eye').' · '.$this->text('ROP status'), $this->text('Right eye').' · '.$this->text('Plus disease'),
            $this->text('Left eye').' · '.$this->text('Zone'), $this->text('Left eye').' · '.$this->text('Stage'),
            $this->text('Left eye').' · '.$this->text('ROP status'), $this->text('Left eye').' · '.$this->text('Plus disease'),
            $this->text('Overall assessment'), $this->text('Management plan'), $this->text('Next visit date'), $this->text('Notes'),
        ];

        $slots = max(1, (int) $patients->max(fn (Patient $patient): int => $patient->visits->count()));

        $groups = [
            ...$this->span($this->text('Patient and referral'), count($patientColumns)),
            ...$this->span($this->text('Summary'), count($summaryColumns)),
        ];
        $headings = [...$patientColumns, ...$summaryColumns];

        for ($slot = 1; $slot <= $slots; $slot++) {
            $label = $slot === 1 ? $this->text('Initial examination') : $this->text('Follow-up :number', ['number' => $slot - 1]);

            array_push($groups, ...$this->span($label, count($visitColumns)));
            array_push($headings, ...$visitColumns);
        }

        $this->writer->addRow(Row::fromValuesWithStyle([$this->text('ROP screening registry — one row per baby')], $this->title));
        $this->writer->addRow(Row::fromValuesWithStyle($groups, $this->group));
        $this->writer->addRow(Row::fromValuesWithStyle($headings, $this->heading, 32));

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setColumnWidthForRange(14, 1, count($headings));
        $sheet->setColumnWidth(26, 2, 3);
        $sheet->setColumnWidth(30, 18, 21);

        foreach ($patients as $patient) {
            $fill = match (true) {
                $patient->had_injection => self::INJECTION_FILL,
                $patient->had_laser => self::LASER_FILL,
                $patient->unverified => self::UNVERIFIED_FILL,
                default => null,
            };

            $values = [
                $patient->file_number, $patient->name, $patient->mother_name, $patient->dob, $patient->sex->label(),
                $patient->birth_weight_g, $patient->ga_weeks, $patient->ga_days, $patient->multiplicity?->label(),
                $patient->delivery_mode?->label(), $patient->referral_date, $patient->referring_doctor, $patient->nicu_days,
                $patient->respiratory_support?->label(), $patient->support_days, $patient->o2_days, $patient->cpap_days,
                $patient->illnessSummary(), $patient->phone, $patient->address, $patient->notes,
                $patient->status->label(), $this->yesNo(! $patient->unverified),
                $patient->exams_count, $patient->first_visit_date, $patient->last_visit_date, $patient->next_appointment_date,
                $this->tristate($patient->any_rop), $patient->highest_stage?->label(), $this->yesNo($patient->type_one),
                $this->yesNo($patient->had_injection), $this->yesNo($patient->had_laser), $patient->last_injection_date,
                $this->yesNo($patient->treatment_pending), $patient->reviewItems->whereNull('resolved_at')->count(),
            ];

            foreach ($patient->visits as $visit) {
                array_push(
                    $values,
                    $visit->visit_date, $visit->kind->label(), $this->weeks($patient->postmenstrualAgeInDays($visit->visit_date)),
                    $visit->right_zone?->label(), $visit->right_stage?->label(), $visit->right_rop_status?->label(), $visit->right_plus?->label(),
                    $visit->left_zone?->label(), $visit->left_stage?->label(), $visit->left_rop_status?->label(), $visit->left_plus?->label(),
                    $visit->assessment, $visit->management_plan?->label(), $visit->next_visit_date, $visit->notes,
                );
            }

            $this->writer->addRow($this->row($values, $fill));
        }
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    protected function visitsSheet(Collection $patients): void
    {
        $this->sheet($this->text('Visits'), freezeRow: 2, freezeColumn: 'C');

        $eye = fn (string $side): array => array_map(fn (string $column): string => $side.' · '.$column, [
            $this->text('Dilatation'), $this->text('Lens'), $this->text('Plus disease'), $this->text('Zone'), $this->text('Stage'),
            $this->text('A-ROP (aggressive)'), $this->text('Type of ROP'), $this->text('ROP status'), $this->text('Notes'),
        ]);

        $headings = [
            $this->text('Clinic file no.'), $this->text('Baby full name'), $this->text('Visit'), $this->text('Date'), $this->text('Record type'),
            $this->text('Age at examination').' ('.$this->text('days').')', $this->text('PMA'), $this->text('PMA').' ('.$this->text('days').')', $this->text('Examiner'),
            ...$eye($this->text('Right eye')), ...$eye($this->text('Left eye')),
            $this->text('Overall assessment'), $this->text('Management plan'), $this->text('Plan details'),
            $this->text('Next visit date'), $this->text('Fee (IQD)'), $this->text('Notes'),
        ];

        $this->writer->addRow(Row::fromValuesWithStyle($headings, $this->heading, 32));

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setColumnWidthForRange(14, 1, count($headings));
        $sheet->setColumnWidth(26, 2);

        foreach ($patients as $patient) {
            foreach ($patient->visits as $index => $visit) {
                $pma = $patient->postmenstrualAgeInDays($visit->visit_date);

                $this->writer->addRow($this->row([
                    $patient->file_number, $patient->name, $index + 1, $visit->visit_date, $visit->kind->label(),
                    $patient->chronologicalAgeInDays($visit->visit_date), $this->weeks($pma), $pma, $visit->examiner,
                    ...$this->eyeValues($visit, 'right'), ...$this->eyeValues($visit, 'left'),
                    $visit->assessment, $visit->management_plan?->label(), $visit->management_notes,
                    $visit->next_visit_date, $visit->fee, $visit->notes,
                ]));
            }
        }
    }

    /**
     * @return list<string|null>
     */
    protected function eyeValues(Visit $visit, string $eye): array
    {
        $isRight = $eye === 'right';
        $aggressive = $isRight ? $visit->right_a_rop : $visit->left_a_rop;

        return [
            $isRight ? $visit->right_dilatation : $visit->left_dilatation,
            $isRight ? $visit->right_lens : $visit->left_lens,
            ($isRight ? $visit->right_plus : $visit->left_plus)?->label(),
            ($isRight ? $visit->right_zone : $visit->left_zone)?->label(),
            ($isRight ? $visit->right_stage : $visit->left_stage)?->label(),
            $aggressive === null ? null : $this->yesNo($aggressive),
            $visit->eyeRopType($eye)?->label(),
            ($isRight ? $visit->right_rop_status : $visit->left_rop_status)?->label(),
            $isRight ? $visit->right_notes : $visit->left_notes,
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    protected function treatmentsSheet(Collection $patients): void
    {
        $this->sheet($this->text('Treatments'), freezeRow: 2, freezeColumn: 'C');

        $headings = [
            $this->text('Clinic file no.'), $this->text('Baby full name'), $this->text('Date performed'), $this->text('PMA'),
            $this->text('Treatment'), $this->text('Eye'), $this->text('Eyes treated'), $this->text('Agent / dose'),
            $this->text('Performed by'), $this->text('Place'), $this->text('Notes'),
        ];

        $this->writer->addRow(Row::fromValuesWithStyle($headings, $this->heading, 32));

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setColumnWidthForRange(16, 1, count($headings));
        $sheet->setColumnWidth(26, 2);

        foreach ($patients as $patient) {
            foreach ($patient->treatments as $treatment) {
                $this->writer->addRow($this->row([
                    $patient->file_number, $patient->name, $treatment->performed_date,
                    $this->weeks($patient->postmenstrualAgeInDays($treatment->performed_date)),
                    $treatment->type->label(), $treatment->eye->label(), $treatment->eyesCount(), $treatment->agent,
                    $treatment->performed_by, $treatment->location, $treatment->notes,
                ], $treatment->type === TreatmentType::Laser ? self::LASER_FILL : self::INJECTION_FILL));
            }
        }
    }

    protected function statisticsSheet(): void
    {
        $this->sheet($this->text('Statistics'));

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setColumnWidth(46, 1);
        $sheet->setColumnWidthForRange(14, 2, 7);

        $statistics = $this->statistics->calculate(null, null);

        $this->writer->addRow(Row::fromValuesWithStyle([$this->text('Registry statistics')], $this->title));
        $this->writer->addRow(Row::fromValuesWithStyle([$this->text('Patient counts, eye counts and treatment-session counts are reported separately. A recommended treatment is counted as performed only when a treatment record exists.')], $this->muted));
        $this->writer->addRow(Row::fromValues([]));

        $this->writer->addRow(Row::fromValuesWithStyle([$this->text('Key figures'), $this->text('Value'), $this->text('Note')], $this->heading));

        foreach ($statistics['kpis'] as $kpi) {
            $this->writer->addRow($this->row([$kpi['label'], $kpi['value'], $kpi['hint']]));
        }

        $this->writer->addRow(Row::fromValues([]));
        $this->writer->addRow(Row::fromValuesWithStyle([
            $this->text('Numeric summaries'), $this->text('Recorded'), $this->text('Missing'), $this->text('Mean'),
            $this->text('Median'), $this->text('Minimum'), $this->text('Maximum'),
        ], $this->heading));

        foreach ($statistics['numeric'] as $row) {
            $this->writer->addRow($this->row([$row['label'], $row['n'], $row['missing'], $row['mean'], $row['median'], $row['min'], $row['max']]));
        }

        foreach ($statistics['crosstabs'] as $table) {
            $this->writer->addRow(Row::fromValues([]));
            $this->writer->addRow(Row::fromValuesWithStyle([
                $table['title'], $this->text('Patients'), $this->text('Known ROP status'), $this->text('ROP'),
                $this->text('ROP rate').' %', $this->text('Type 1'), $this->text('Treated'),
            ], $this->heading));

            foreach ($table['rows'] as $row) {
                $this->writer->addRow($this->row([$row['label'], $row['patients'], $row['known'], $row['rop'], $row['rop_percent'], $row['type_one'], $row['treated']]));
            }
        }

        foreach ($statistics['charts'] as $chart) {
            $known = array_sum(array_column($chart['items'], 'value'));

            $this->writer->addRow(Row::fromValues([]));
            $this->writer->addRow(Row::fromValuesWithStyle([$chart['title'], $this->text('Number of :unit', ['unit' => $chart['unit']]), '%'], $this->heading));

            foreach ($chart['items'] as $item) {
                $this->writer->addRow($this->row([$item['label'], $item['value'], $known > 0 ? round($item['value'] / $known * 100, 1) : null]));
            }

            if ($chart['total'] > 0) {
                $this->writer->addRow(Row::fromValuesWithStyle([
                    $this->text('Out of :total :unit', ['total' => $chart['total'], 'unit' => $chart['unit']])
                        .($chart['unknown'] > 0 ? ' · '.$this->text(':count not recorded', ['count' => $chart['unknown']]) : ''),
                ], $this->muted));
            }
        }

        $this->writer->addRow(Row::fromValues([]));
        $this->writer->addRow(Row::fromValuesWithStyle([
            $this->text('Month'), $this->text('New patients'), $this->text('Examinations'), $this->text('Treatments'),
        ], $this->heading));

        foreach ($statistics['monthly'] as $month) {
            $this->writer->addRow($this->row([$month['month'], $month['new_patients'], $month['examinations'], $month['treatments']]));
        }
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    protected function reviewSheet(Collection $patients): void
    {
        $this->sheet($this->text('Review log'), freezeRow: 2);

        $headings = [
            $this->text('Clinic file no.'), $this->text('Baby full name'), $this->text('Field'), $this->text('To check'),
            $this->text('Source records'), $this->text('Status'), $this->text('Resolution'),
        ];

        $this->writer->addRow(Row::fromValuesWithStyle($headings, $this->heading));

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setColumnWidth(14, 1, 6);
        $sheet->setColumnWidth(26, 2, 3, 5);
        $sheet->setColumnWidth(80, 4);
        $sheet->setColumnWidth(40, 7);

        foreach ($patients as $patient) {
            /** @var ReviewItem $item */
            foreach ($patient->reviewItems as $item) {
                $this->writer->addRow($this->row([
                    $patient->file_number, $patient->name, $item->field, $item->issue, $item->source_reference,
                    $item->resolved_at === null ? $this->text('Open') : $this->text('Resolved'), $item->resolution,
                ]));
            }
        }
    }

    protected function listsSheet(): void
    {
        $this->sheet($this->text('Lists'));

        $lists = [
            $this->text('Sex') => Sex::cases(),
            $this->text('Multiplicity') => Multiplicity::cases(),
            $this->text('Delivery mode') => DeliveryMode::cases(),
            $this->text('Respiratory support') => RespiratorySupport::cases(),
            $this->text('Status') => PatientStatus::cases(),
            $this->text('Zone') => Zone::cases(),
            $this->text('Stage') => Stage::cases(),
            $this->text('ROP status') => RopStatus::cases(),
            $this->text('Plus disease') => PlusDisease::cases(),
            $this->text('Type of ROP') => RopType::cases(),
            $this->text('Management plan') => ManagementPlan::cases(),
            $this->text('Record type') => VisitKind::cases(),
            $this->text('Treatment') => TreatmentType::cases(),
            $this->text('Eye') => EyeSide::cases(),
        ];

        $this->writer->getCurrentSheet()->setColumnWidthForRange(28, 1, count($lists));
        $this->writer->addRow(Row::fromValuesWithStyle(array_keys($lists), $this->heading));

        $height = max(array_map('count', $lists));

        for ($index = 0; $index < $height; $index++) {
            $this->writer->addRow($this->row(array_map(
                fn (array $cases): ?string => isset($cases[$index]) ? $cases[$index]->label() : null,
                array_values($lists),
            )));
        }
    }

    /**
     * @param  Collection<int, Patient>  $patients
     */
    protected function aboutSheet(Collection $patients): void
    {
        $this->sheet($this->text('About'));
        $this->writer->getCurrentSheet()->setColumnWidth(110, 1);

        $lines = [
            $this->text('Exported from :app on :date.', ['app' => (string) config('app.name'), 'date' => now()->format('Y-m-d H:i')]),
            $this->text(':patients patients, :visits visits and :treatments treatments.', [
                'patients' => $patients->count(),
                'visits' => $patients->sum(fn (Patient $patient): int => $patient->visits->count()),
                'treatments' => $patients->sum(fn (Patient $patient): int => $patient->treatments->count()),
            ]),
            '',
            $this->text('Dates are true Excel dates shown as YYYY-MM-DD. Empty cells mean "not recorded", never a negative finding.'),
            $this->text('Lavender rows had an intravitreal injection performed; peach rows had laser only; grey rows are not identity-confirmed and are excluded from the statistics.'),
            $this->text('PMA (postmenstrual age) is calculated from the date of birth, the gestational age and the visit date.'),
            $this->text('The Statistics sheet covers the whole registry and holds values, not formulas: export again after entering new data.'),
        ];

        $this->writer->addRow(Row::fromValuesWithStyle([$this->text('About this workbook')], $this->title));

        foreach ($lines as $line) {
            $this->writer->addRow(Row::fromValues([$line]));
        }
    }

    /**
     * Start a new sheet (or name the first one) with frozen headers and the reading direction of the language.
     *
     * @param  int<0, max>  $freezeRow
     * @param  non-empty-string  $freezeColumn
     */
    protected function sheet(string $name, int $freezeRow = 0, string $freezeColumn = 'A', bool $first = false): void
    {
        $sheet = $first ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();

        $sheet->setName(mb_substr($name, 0, 31));
        $sheet->setSheetView(new SheetView(
            rightToLeft: config('registry.locales.'.app()->getLocale().'.dir') === 'rtl',
            freezeRow: $freezeRow,
            freezeColumn: $freezeColumn,
        ));
    }

    /**
     * Build a data row; dates become true Excel dates and the optional fill highlights the whole row.
     *
     * @param  array<int, mixed>  $values
     */
    protected function row(array $values, ?string $fill = null): Row
    {
        $plain = $fill === null ? $this->plain : $this->plain->withBackgroundColor($fill);
        $date = $fill === null ? $this->date : $this->date->withBackgroundColor($fill);

        return new Row(array_map(fn (mixed $value): Cell => match (true) {
            $value instanceof CarbonInterface => Cell::fromValue($value->toDateTimeImmutable()->setTime(0, 0), $date),
            is_int($value), is_float($value), is_string($value), $value === null => Cell::fromValue($value, $plain),
            default => Cell::fromValue((string) json_encode($value), $plain),
        }, array_values($values)));
    }

    /**
     * Repeat a group title over the columns it covers (only the first cell carries the text).
     *
     * @return list<string>
     */
    protected function span(string $label, int $columns): array
    {
        return [$label, ...array_fill(0, max(0, $columns - 1), '')];
    }

    protected function weeks(?int $days): ?string
    {
        return $days === null ? null : intdiv($days, 7).'+'.($days % 7);
    }

    protected function yesNo(bool $value): string
    {
        return $value ? $this->text('Yes') : $this->text('No');
    }

    protected function tristate(?bool $value): string
    {
        return $value === null ? $this->text('Unknown') : $this->yesNo($value);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    protected function text(string $key, array $replace = []): string
    {
        $translation = __($key, $replace);

        return is_string($translation) ? $translation : $key;
    }
}
