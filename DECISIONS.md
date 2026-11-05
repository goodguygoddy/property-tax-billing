# Decisions

## Money and math

- All intermediate math uses exact decimals (`brick/math`); nothing is rounded until the fee is rounded to the cent, halves up. Fees and invoice totals are stored as integer cents. Savings and the raw fee are also stored so the pages can show "$69.375 → $69.38".
- Every invoice line and adjustment stores a per-jurisdiction breakdown (taxable value and tax at both values, the rate, the fee rate, and the waived/capped flags). A charge can be explained later even if rates or rules change.
- Jurisdiction rates and exemptions live in a seeded table rather than code so they can be changed without a deploy. Fee rates and limits live in `config/billing.php`.

## Outcomes

- The outcome that counts for a property is the latest event recorded on or before the as-of date (ties broken by insertion order). Events recorded after the as-of date are invisible to that run.
- If a property's latest event is pending, it is skipped, even if it was billed before. It is re-evaluated once a final event arrives.
- Imported events must carry a `source_id`; events with an existing `source_id` are skipped (with a warning if the data differs). Stored events are never edited. Events from the form have no `source_id`.
- "Enrolled that season" is read as the number of properties the client has in the data set; the data carries no season, so one data set is one season. The rate is evaluated at run time.

## Invoices and adjustments

- A property is placed on an invoice exactly once (enforced by a unique index). Any later change is an adjustment that references the original invoice and line.
- An adjustment is issued when a billed property has a newer final event and the recomputed fee differs from the fee last billed (the original line, or the most recent adjustment). The amount is the difference, so repeated revisions chain correctly and a revision that leaves the fee unchanged produces nothing.
- One adjustment per property, not per client, so each one points at exactly one original line.
- $0 invoices are issued, matching the sample data, so finance can see that a final property was considered.
- Every run is recorded, including empty ones, as an audit trail. A run dated before an earlier run is refused: it would see fewer events than that run did.
- Invoice and adjustment numbers (`INV-00001`, `ADJ-00001`) are derived from the row id.

## How I checked it

- Feature tests import the sample file and assert the expected results for run 1 (all five lines, three invoice totals, P6 skipped), run 2 (the +$53.22 adjustment against INV-00001, invoice unchanged), and a third run that produces nothing.
- Further tests cover a pending property becoming final, a second revision crediting the earlier charge, a revision that changes the value but not the fee, the as-of cutoff, the volume rate counting properties without outcomes, and import validation leaving the database untouched on failure.
- Unit tests pin the rounding edge ($9.9957 rounds to $10.00 and is kept; $9.9944 is waived), the floor at zero on the ISD exemption, and exact fractional cents.

## What I'd do next

- Add a season/tax year to properties and events so several years can coexist.
- Lock the fee rate onto the invoice (currently derived from the enrolled count at run time).
- Invoice and adjustment detail pages, plus CSV or PDF export for finance.
- A dry-run flag for `billing:run` that previews what would be issued.
