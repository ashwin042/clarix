# R2 task_code prefix collision — scoping note

**Date:** 2026-08-16
**Status:** Options A and B both **done** (2026-08-16). Every file referenced by
`task_files` is now unit-scoped. What remains is not a layout question but an
orphan-cleanup one — see "Outcome" below.
**Blocks:** nothing. The organizations work is no longer gated on this.

## Summary

Task files are stored under `task-files/{task_code}/…`, but `task_code` is only
unique per unit (`tasks` has `unique(['unit_id', 'task_code'])`). Two units that
pick the same task code therefore share one R2 prefix.

This is a **tenant-isolation and addressability** problem, not a data-loss one.
See the correction below.

## Correction to the original report

The bug was first raised as "one unit's file could overwrite another's". That
overstates it. `UploadedFile::store()` names objects via `hashName()`, which is
`Str::random(40)` plus the extension
(`vendor/laravel/framework/src/Illuminate/Http/FileHelpers.php:48`). Object keys
are random per upload, so a silent overwrite would need a 40-character random
collision. That is not a real risk.

What is real is that **the prefix no longer identifies a tenant**.

## Current exposure

Measured on the local database, 2026-08-16:

- `task_code` values shared by more than one unit: **0**
- Storage operations that act on a whole prefix (`deleteDirectory`, prefix
  listing): **none in `app/`**

So the defect is entirely latent today. `Task::deleteWithFiles()` removes files
object-by-object from the stored `file_path`, and `storage:reconcile` lists the
whole bucket and attributes keys by exact `file_path` match — both are correct
regardless of prefix layout.

## Why it still matters

The cost is paid the first time anything needs to address a tenant's objects as
a set. That is exactly what organizations will need.

1. **Offboarding and erasure.** "Delete everything belonging to this tenant"
   cannot be expressed as a prefix delete. It has to enumerate `task_files`,
   which misses any object whose row was already lost — precisely the 910
   orphans reconciliation just found.
2. **Blast radius of a future prefix delete.** The moment someone implements
   task cleanup as `Storage::disk('r2')->deleteDirectory('task-files/'.$code)`,
   it deletes the other unit's files too. Nothing prevents that today; the
   layout invites it.
3. **Bucket-level controls.** Per-tenant lifecycle rules, retention policies and
   scoped access credentials are all expressed as key prefixes. None can be
   applied.
4. **Reconciliation cost.** Reconcile must list the entire bucket because there
   is no per-tenant prefix to scope `ListObjectsV2` to. Cost grows with total
   objects rather than with the tenant being checked, so per-tenant or
   incremental reconciliation is not available.

## Options

### A. Prefix new uploads only — `task-files/{unit_id}/{task_code}/…` — DONE

Change the three upload sites; leave existing objects untouched. Reads and
deletes go through the stored `file_path`, so nothing breaks and no data moves.

- **Cost:** ~3 lines plus tests.
- **Downside:** the bucket is permanently bimodal. Per-tenant prefix operations
  cover new files only, so offboarding still needs the `task_files` enumeration
  as a second pass. The legacy set stops growing but never shrinks.

**Implemented 2026-08-16.** Path construction lives in `Task::storagePrefix()`
and `Task::completedStoragePrefix()`, so the layout is defined in one place
rather than repeated at each upload site. Covered by
`tests/Feature/Storage/TaskFileStoragePathTest.php`, including two units sharing
a `task_code` landing in disjoint prefixes, legacy keys still downloading and
deleting, and `storage:reconcile` totalling a bucket that holds both layouts.

### B. Option A plus a one-off backfill

Same path change, plus a resumable command that server-side copies each legacy
object to its unit prefix, rewrites `file_path`, then deletes the source.

- **Cost:** ~1 day including the command, a dry-run mode and verification.
- **Ordering matters:** copy → update `file_path` → delete source, so a reader
  mid-move always resolves to an object that exists. Must be idempotent and
  resumable; a half-finished run should be safe to re-run.
- **Upside:** one uniform layout, and every prefix-level capability above
  becomes available.
- **Downside:** rewrites live storage. Wants a verified backup first.

### C. Leave paths alone; treat `task_files` as the only index

Accept that R2 layout carries no tenant meaning and mediate every per-tenant
operation through the database.

- **Cost:** zero.
- **Downside:** the orphan problem is unsolvable by construction — an object
  whose row is gone can never be attributed to a tenant, only found and
  reported. 1.07 GB across 910 objects is already in that state.

### D. Bucket per tenant

Genuine isolation, and the only option that supports per-tenant credentials.
Rejected as disproportionate: it adds bucket provisioning to tenant signup and
runs into per-account bucket limits.

## Recommendation

**A now, B scheduled before organizations land.**

The path change is small and stops the legacy set growing while the decision is
being made. The backfill is what actually unlocks prefix-level isolation, and it
is cheapest to run while the bucket is ~910 objects rather than after the
platform grows.

**Key scheduling point — use `unit_id`, and it survives the org migration.**
The instinct is to wait and key the prefix on `organization_id` so the backfill
only runs once. That is not necessary. If organizations end up sitting *above*
units (one org, many units), then a unit prefix is strictly finer than an org
prefix: an organization's objects are the union of its units' prefixes, which is
still a bounded set of prefix queries. No second data migration is needed.

A second backfill would only be required if organizations *replace* units
one-for-one — in which case the rename makes `unit_id` and `organization_id` the
same numbers anyway, and the prefix stays valid.

So the prefix change is safe to make now under either tenancy outcome.

## Work involved

| Change | Where |
|---|---|
| Upload path | `TaskFileController::store`, `::storeCompleted`, `CreateTask::save` |
| Backfill command (option B) | new `app/Console/Commands/` entry |
| Per-prefix reconcile mode | `ReconcileStorageUsage` — optional follow-up |

**Tests to add**
- Two units with the same `task_code` write to different prefixes.
- A legacy `file_path` still downloads and deletes correctly after the change.
- Backfill is idempotent: running it twice leaves one copy and a correct
  `file_path`.
- Backfill is resumable: interrupting mid-run and re-running loses nothing.
- `storage:reconcile` totals are unchanged across the migration.

## Outcome (2026-08-16)

Both options were carried out. `storage:backfill-prefix` moved all 12
`task_files` rows to the unit-led layout — copy, verify, update `file_path`,
delete source — and `storage:reconcile` afterwards reported no drift, with
per-unit totals byte-identical before and after (unit 24: 4,082,270; unit 25:
297,279).

**The bucket is still not single-layout, and cannot be made so.** Measured after
the backfill:

| Layout | Objects | Size |
|---|---:|---:|
| `task-files/{unit_id}/{task_code}/…` | 12 | 4.4 MB |
| `task-files/{task_code}/…` (legacy) | 910 | 1.07 GB |

Every remaining legacy object is an **orphan** — no `task_files` row, therefore
no task, therefore no unit to key a destination on. They are unmovable by
construction, not by omission. Reaching a genuinely single layout means deciding
what to do with them, which is a deletion question, not a migration one.

### Key-parsing caveat

Layout cannot be inferred reliably from an object key. Two orphans sit at
`task-files/12/…` where `12` is a legacy `task_code` that also happens to be a
real unit id. Segment depth separates the common cases — legacy regular files
have three segments, unit-scoped ones four — but a legacy *completed* file
(`task-files/{code}/completed/{file}`) is also four.

Any future prefix tooling must treat `task_files.file_path` as the authority
rather than parsing keys. This did not affect the backfill, which works from
database rows.

## Decision needed

1. ~~A alone, or A plus B?~~ Both done.
2. ~~If B: run it before or after the organizations work starts?~~ Done before.
3. **Open:** delete the 910 orphaned objects (1.07 GB), or leave them? Nothing
   references them and they are charged to no unit, so they cost storage
   only. Deleting is irreversible and is the only remaining step toward a
   single layout.

## Related

- Orphan objects surfaced by the first `storage:reconcile` run: 910 objects,
  1.07 GB, across 333 task codes with no surviving task row. Separate cleanup
  question — this note does not propose deleting them.
