# Lone Oak Billing Engine

A small Laravel application that bills property-tax protest clients a percentage of the tax it saved them. It imports properties and outcome events from JSON, runs billing as of a date, stores the invoices and adjustments it issues, and shows them per client with a full explanation of every charge.

## Requirements

- PHP 8.3+ with the `pdo_sqlite` extension (PHP 8.4 was used for development)
- Composer

No Node or asset build is needed; the pages use a plain stylesheet in `public/css`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

`composer setup` runs the same steps in one go. The seed step loads the three jurisdictions (ISD, CNTY, CITY) with their rates and homestead exemptions; the import will refuse to run without them.

## Usage

### 1. Import data

```bash
php artisan billing:import database/data/sample.json
```

Any file with the same shape works, so point it at another file to load a different data set. The import is safe to re-run: clients and properties are updated in place, and events already present (matched on `source_id`) are skipped.

### 2. Run billing

```bash
php artisan billing:run --as-of=2026-07-01
php artisan billing:run --as-of=2026-09-01
```

`--as-of` defaults to today. Each run bills every property whose latest outcome on or before that date is final and has not been billed, one invoice per client. Properties billed earlier whose outcome has since changed get an adjustment against the original invoice. Running again with no new information prints "Nothing new to bill."

### 3. Browse

```bash
php artisan serve
```

- `/clients` lists every client with invoiced, adjusted and net totals.
- `/clients/{id}` shows a client's properties, invoices, adjustments and outcome events. Each invoice line and adjustment has a "How this fee was calculated" section with the per-jurisdiction taxable values and tax, the savings, the fee before and after rounding, and whether the minimum or maximum applied.
- `/outcomes/create` records a new outcome event, with validation. It is billed on the next run like any imported event.

## Tests

```bash
php artisan test
```

Unit tests cover the tax and fee rules (exemptions, rounding halves up, the $10 waiver and $2,500 cap, the 25% volume rate). Feature tests cover the importer, the billing runs against the sample data's expected results, adjustments, idempotent re-runs, the form validation and the pages.

## Layout

| Area                                | Where                                                            |
| ----------------------------------- | ---------------------------------------------------------------- |
| Fee rules (rates, limits)           | `config/billing.php`                                             |
| Jurisdiction rates and exemptions   | `database/seeders/JurisdictionSeeder.php`                        |
| Tax and fee math                    | `app/Billing/TaxCalculator.php`, `app/Billing/FeeCalculator.php` |
| Billing run (invoices, adjustments) | `app/Billing/BillingRunner.php`                                  |
| JSON import                         | `app/Import/JsonImporter.php`                                    |
| Commands                            | `app/Console/Commands/`                                          |
| Pages and form                      | `app/Http/`, `resources/views/`                                  |

See `DECISIONS.md` for the choices made.
