<?php

return [
    'sex' => ['male' => 'ذكر', 'female' => 'أنثى', 'unknown' => 'غير معروف'],
    'multiplicity' => ['single' => 'مفرد', 'twin' => 'توأم', 'triplet' => 'ثلاثي', 'higher' => 'أكثر من ثلاثة', 'unknown' => 'غير معروف'],
    'delivery_mode' => ['vaginal' => 'ولادة طبيعية', 'cesarean' => 'عملية قيصرية', 'other' => 'أخرى'],
    'respiratory_support' => ['none' => 'لا يوجد', 'o2' => 'أوكسجين', 'cpap' => 'CPAP', 'o2_cpap' => 'أوكسجين + CPAP', 'ventilator' => 'جهاز تنفس اصطناعي'],
    'patient_status' => ['active' => 'متابعة مستمرة', 'discharged' => 'انتهت المتابعة', 'referred' => 'محال', 'lost' => 'انقطع عن المتابعة', 'deceased' => 'متوفى'],
    // Zone, stage, plus, type and ROP status are written in English in clinical practice.
    'zone' => ['zone_1' => 'Zone I', 'posterior_zone_2' => 'Posterior zone II', 'zone_2' => 'Zone II', 'zone_3' => 'Zone III', 'not_applicable' => 'Not applicable', 'not_assessable' => 'Not assessable'],
    'stage' => ['stage_0' => 'Stage 0', 'stage_1' => 'Stage 1', 'stage_2' => 'Stage 2', 'stage_3' => 'Stage 3', 'stage_4a' => 'Stage 4A', 'stage_4b' => 'Stage 4B', 'stage_5' => 'Stage 5', 'not_applicable' => 'Not applicable', 'not_assessable' => 'Not assessable'],
    'rop_status' => ['no_rop' => 'No ROP', 'present' => 'ROP present', 'regressing' => 'Regressing ROP', 'regressed' => 'Regressed ROP', 'fully_vascularized' => 'Fully vascularized', 'incomplete_vascularization' => 'Incomplete vascularization', 'not_assessable' => 'Not assessable'],
    'plus_disease' => ['none' => 'No plus', 'pre_plus' => 'Pre-plus', 'plus' => 'Plus disease', 'not_assessable' => 'Not assessable'],
    'rop_type' => ['type_1' => 'Type 1', 'type_2' => 'Type 2'],
    'management_plan' => ['observe' => 'مراقبة ومتابعة', 'eylea' => 'حقن Eylea', 'laser' => 'ليزر', 'eylea_laser' => 'حقن Eylea + ليزر', 'referred' => 'إحالة إلى بغداد', 'discharge' => 'إنهاء متابعة اعتلال الشبكية', 'other' => 'أخرى'],
    'visit_kind' => ['examination' => 'فحص', 'undated_examination' => 'فحص غير مؤرخ', 'treatment_only' => 'علاج فقط', 'note_only' => 'خطة / ملاحظة فقط', 'uncertain' => 'نسبة غير مؤكدة', 'index_pending' => 'قيد من السجل (بانتظار الربط)'],
    'treatment_type' => ['eylea' => 'حقن Eylea', 'other_anti_vegf' => 'حقن Anti-VEGF آخر', 'laser' => 'ليزر', 'surgery' => 'جراحة', 'other' => 'أخرى'],
    'eye_side' => ['right' => 'العين اليمنى', 'left' => 'العين اليسرى', 'both' => 'كلتا العينين'],
    'suggestion_list' => ['illness' => 'الأمراض المرافقة', 'referring_doctor' => 'الأطباء المحيلون'],
];
