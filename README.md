# ROP Registry

A local registry for **retinopathy of prematurity (ROP) screening**. It replaces a one-row-per-baby Excel workbook with an application that is easier to keep correct: every baby has a record, every examination and treatment is entered once, and the statistics, reminders and exports are derived from that.

It is built for one clinic computer, used by one administrator, in English or Arabic.

## What it does

- **Patients** – birth and neonatal history, referral, contact details, follow-up status.
- **Visits** – an examination form modelled on the paper form: both eyes side by side with zone, stage, plus disease, A-ROP and type; postmenstrual age is calculated; the ETROP type and the guideline follow-up interval are suggested.
- **Treatments** – injections and laser that were actually performed, kept separate from the recommended plan.
- **Attachments** – pictures and PDFs per patient (file picker, drag and drop, or paste).
- **Camera inbox** – images printed to a virtual printer (`capture-images.bat`) or exported into a watched folder wait in an inbox until they are assigned to a patient; "Receive camera images" on a patient sends them there directly.
- **Reminders** – appointments today and upcoming, planned visits not yet recorded, pending treatments, patients under post-injection surveillance.
- **Statistics** – by date range, with denominators and unknown counts; patient, eye and session counts are kept apart.
- **Data quality** – review queue for uncertain facts, duplicate warning, identity-unverified records, recycle bin, full audit log.
- **Export and print** – Excel workbook (one row per baby plus long-form sheets), CSV, printable examination report and patient summary.
- **Backups** – automatic daily database backup, full backups with attachments, restore from the interface.

## Stack

Laravel 13 · Inertia 3 · Vue 3 (TypeScript) · Tailwind CSS 4 with shadcn-vue components · SQLite · Laravel Fortify for the single login · OpenSpout for Excel export.

## Running it

Requirements: PHP 8.4+ (with `pdo_sqlite`, `zip`, `fileinfo`), Composer and Node 20+. [Laravel Herd](https://herd.laravel.com) provides PHP on Windows.

```bash
composer setup        # install, create .env and the database, build the interface
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then create the administrator:

```bash
php artisan db:seed
```

Start it with `start-registry.bat` (Windows) or `php artisan serve`, and open <http://localhost:8000>. Use `composer run dev` while changing code.

## Moving to another computer

1. On the old computer: **Backups → Full backup → Download**.
2. On the new computer: install the application as above and sign in.
3. **Backups → Restore from a file**, choose the downloaded archive and confirm.

A full backup holds the database and every attachment. The command-line equivalents are `php artisan registry:backup` and `php artisan registry:restore <file>`.

## Privacy

Patient data lives only in `database/database.sqlite` and `storage/app/private`, both excluded from version control. Never commit them, the `.env` file, or backup archives.

## Checks

```bash
composer test         # code style, static analysis and the test suite
npm run check         # lint and format the interface
```
