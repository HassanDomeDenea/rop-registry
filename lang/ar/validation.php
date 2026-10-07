<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Arabic messages for the validation rules used by the registry. Rules
    | that are not listed here fall back to the English messages.
    |
    */

    'accepted' => 'يجب قبول :attribute.',
    'after' => 'يجب أن يكون :attribute تاريخاً بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً مساوياً أو لاحقاً لـ :date.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'before' => 'يجب أن يكون :attribute تاريخاً قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخاً مساوياً أو سابقاً لـ :date.',
    'between' => [
        'array' => 'يجب أن يحتوي :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و :max حرفاً.',
    ],
    'boolean' => 'يجب أن تكون قيمة :attribute نعم أو لا.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => ':attribute ليس تاريخاً صحيحاً.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'enum' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'exists' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'extensions' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المختارة لـ :attribute غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول :attribute :max حرفاً.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل طول :attribute عن :min حرفاً.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => ':attribute المدخلة ظهرت في تسريب بيانات. يرجى اختيار غيرها.',
    ],
    'required' => 'حقل :attribute مطلوب.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع :attribute.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'locale' => 'اللغة',
        'file_number' => 'رقم الملف',
        'dob' => 'تاريخ الولادة',
        'sex' => 'الجنس',
        'birth_weight_g' => 'وزن الولادة',
        'ga_weeks' => 'أسابيع عمر الحمل',
        'ga_days' => 'أيام عمر الحمل',
        'multiplicity' => 'تعدد الولادة',
        'delivery_mode' => 'طريقة الولادة',
        'referral_date' => 'تاريخ الإحالة',
        'referring_doctor' => 'الطبيب المحيل',
        'nicu_days' => 'مدة البقاء في الخدج',
        'respiratory_support' => 'الدعم التنفسي',
        'support_days' => 'مدة الدعم',
        'o2_days' => 'أيام الأوكسجين',
        'cpap_days' => 'أيام CPAP',
        'systemic_illness' => 'الأمراض الجهازية',
        'phone' => 'الهاتف',
        'phone_alt' => 'الهاتف الثاني',
        'address' => 'العنوان',
        'notes' => 'الملاحظات',
        'status' => 'الحالة',
        'kind' => 'نوع السجل',
        'visit_date' => 'تاريخ الفحص',
        'next_visit_date' => 'تاريخ الزيارة القادمة',
        'examiner' => 'الفاحص',
        'assessment' => 'التقييم',
        'management_plan' => 'خطة العلاج',
        'management_notes' => 'تفاصيل الخطة',
        'fee' => 'الأجر',
        'type' => 'العلاج',
        'eye' => 'العين',
        'performed_date' => 'تاريخ الإجراء',
        'visit_id' => 'الزيارة المرتبطة',
        'agent' => 'الدواء / الجرعة',
        'performed_by' => 'أجراه',
        'location' => 'المكان',
        'files' => 'الملفات',
        'files.*' => 'الملف',
        'caption' => 'الوصف',
        'issue' => 'الملاحظة',
        'path' => 'المجلد',
        'archive' => 'ملف النسخة الاحتياطية',
        'backup' => 'النسخة الاحتياطية',
        'confirmation' => 'كلمة التأكيد',
        'today' => 'اليوم',
    ],

];
