<?php

return [
    'sex' => ['male' => 'ذكر', 'female' => 'أنثى', 'unknown' => 'غير معروف'],
    'multiplicity' => ['single' => 'مفرد', 'twin' => 'توأم', 'triplet' => 'ثلاثي', 'higher' => 'أكثر من ثلاثة', 'unknown' => 'غير معروف'],
    'delivery_mode' => ['vaginal' => 'ولادة طبيعية', 'cesarean' => 'عملية قيصرية', 'other' => 'أخرى'],
    'respiratory_support' => ['none' => 'لا يوجد', 'o2' => 'أوكسجين', 'cpap' => 'CPAP', 'o2_cpap' => 'أوكسجين + CPAP', 'ventilator' => 'جهاز تنفس اصطناعي'],
    'patient_status' => ['active' => 'متابعة مستمرة', 'discharged' => 'انتهت المتابعة', 'referred' => 'محال', 'lost' => 'انقطع عن المتابعة', 'deceased' => 'متوفى'],
    'zone' => ['zone_1' => 'المنطقة I', 'posterior_zone_2' => 'المنطقة II الخلفية', 'zone_2' => 'المنطقة II', 'zone_3' => 'المنطقة III', 'not_applicable' => 'لا ينطبق', 'not_assessable' => 'غير قابل للتقييم'],
    'stage' => ['stage_0' => 'المرحلة 0', 'stage_1' => 'المرحلة 1', 'stage_2' => 'المرحلة 2', 'stage_3' => 'المرحلة 3', 'stage_4a' => 'المرحلة 4A', 'stage_4b' => 'المرحلة 4B', 'stage_5' => 'المرحلة 5', 'not_applicable' => 'لا ينطبق', 'not_assessable' => 'غير قابل للتقييم'],
    'rop_status' => ['no_rop' => 'لا يوجد اعتلال', 'present' => 'اعتلال موجود', 'regressing' => 'اعتلال في طور التراجع', 'regressed' => 'اعتلال متراجع', 'fully_vascularized' => 'توعي كامل', 'incomplete_vascularization' => 'توعي غير مكتمل', 'not_assessable' => 'غير قابل للتقييم'],
    'plus_disease' => ['none' => 'لا يوجد Plus', 'pre_plus' => 'Pre-plus', 'plus' => 'Plus disease', 'not_assessable' => 'غير قابل للتقييم'],
    'rop_type' => ['type_1' => 'النوع 1', 'type_2' => 'النوع 2'],
    'management_plan' => ['observe' => 'مراقبة ومتابعة', 'eylea' => 'حقن Eylea', 'laser' => 'ليزر', 'eylea_laser' => 'حقن Eylea + ليزر', 'referred' => 'إحالة إلى بغداد', 'discharge' => 'إنهاء متابعة اعتلال الشبكية', 'other' => 'أخرى'],
    'visit_kind' => ['examination' => 'فحص', 'undated_examination' => 'فحص غير مؤرخ', 'treatment_only' => 'علاج فقط', 'note_only' => 'خطة / ملاحظة فقط', 'uncertain' => 'نسبة غير مؤكدة', 'index_pending' => 'قيد من السجل (بانتظار الربط)'],
    'treatment_type' => ['eylea' => 'حقن Eylea', 'other_anti_vegf' => 'حقن Anti-VEGF آخر', 'laser' => 'ليزر', 'surgery' => 'جراحة', 'other' => 'أخرى'],
    'eye_side' => ['right' => 'العين اليمنى', 'left' => 'العين اليسرى', 'both' => 'كلتا العينين'],
    'suggestion_list' => ['illness' => 'الأمراض المرافقة', 'referring_doctor' => 'الأطباء المحيلون'],
];
