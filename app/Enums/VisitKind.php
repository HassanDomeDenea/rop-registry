<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum VisitKind: string
{
    use HasOptions;

    case Examination = 'examination';
    case UndatedExamination = 'undated_examination';
    case TreatmentOnly = 'treatment_only';
    case NoteOnly = 'note_only';
    case Uncertain = 'uncertain';
    case IndexPending = 'index_pending';

    /**
     * Determine whether the record counts as a performed examination.
     */
    public function isExamination(): bool
    {
        return in_array($this, [self::Examination, self::UndatedExamination], true);
    }
}
