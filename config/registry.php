<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator Account
    |--------------------------------------------------------------------------
    |
    | The registry is a single-user application. The database seeder creates
    | (or updates) this administrator account, which is used to sign in.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@rop.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    */

    'locales' => [
        'en' => ['label' => 'English', 'dir' => 'ltr'],
        'ar' => ['label' => 'العربية', 'dir' => 'rtl'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | Backup archives contain a consistent copy of the SQLite database and
    | every patient attachment. A backup is created automatically once a
    | day while the application is in use, and old archives are pruned.
    |
    */

    'backup' => [
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        'keep' => (int) env('BACKUP_KEEP', 30),
        'auto_interval_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => 'local',
        'max_kilobytes' => 51200,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Camera inbox
    |--------------------------------------------------------------------------
    |
    | "folder" is where the camera software exports images; the registry takes
    | over whatever appears there. It can also be set from the Camera inbox
    | page. "receive_minutes" is how long incoming images go straight to a
    | patient after "Receive camera images" is pressed on the patient page.
    |
    */

    'capture' => [
        'folder' => env('CAPTURE_FOLDER'),
        'receive_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    |
    | "upcoming_days" controls how far ahead appointments are listed, and
    | "injection_surveillance_weeks" how long a patient stays on the
    | post-injection watch list after the last intravitreal injection.
    |
    */

    'reminders' => [
        'upcoming_days' => 14,
        'injection_surveillance_weeks' => 26,
    ],

    /*
    |--------------------------------------------------------------------------
    | Clinic Letterhead
    |--------------------------------------------------------------------------
    |
    | Shown on printed examination reports.
    |
    */

    'clinic' => [
        'name' => env('CLINIC_NAME', 'ROP Screening Unit'),
        'doctor' => env('CLINIC_DOCTOR'),
        'address' => env('CLINIC_ADDRESS'),
        'phone' => env('CLINIC_PHONE'),
    ],

];
