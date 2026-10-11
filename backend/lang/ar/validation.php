<?php

/*
| Arabic messages for the rules used in this application. Any rule missing
| here falls back to English (APP_FALLBACK_LOCALE).
*/

return [
    'accepted' => 'يجب قبول :attribute.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً لاحقاً أو مساوياً لـ :date.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'date' => 'يجب أن يكون :attribute تاريخاً صحيحاً.',
    'digits' => 'يجب أن يتكون :attribute من :digits أرقام.',
    'distinct' => 'للحقل :attribute قيمة مكررة.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string' => 'يجب ألا يزيد طول :attribute عن :max حرفاً.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفاً من النوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'file' => 'يجب أن يكون حجم :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب أن تكون قيمة :attribute :min على الأقل.',
        'string' => 'يجب أن يكون طول :attribute :min أحرف على الأقل.',
    ],
    'regex' => 'صيغة :attribute غير صحيحة.',
    'required' => 'حقل :attribute مطلوب.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عناصر.',
        'file' => 'يجب أن يكون حجم :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون طول :attribute :size حرفاً.',
    ],
    'string' => 'يجب أن يكون :attribute نصاً.',
    'ulid' => 'يجب أن يكون :attribute معرّف ULID صحيحاً.',
    'unique' => 'قيمة :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل رفع :attribute.',
    'school_email' => 'يجب أن يكون :attribute حساباً مدرسياً ينتهي بـ @:domain.',

    'custom' => [],

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'locale' => 'اللغة',
        'roles' => 'الأدوار',
        'roles.*' => 'الدور',
        'file' => 'الملف',
        'password' => 'كلمة المرور',
        'code' => 'رمز التحقق',
        'search' => 'البحث',
        'status' => 'الحالة',
        'role' => 'الدور',
    ],
];
