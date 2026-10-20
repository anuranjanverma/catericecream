# Location fixtures (LOCAL TEST DATA, NOT APPROVED COVERAGE)

These CSVs exist only to develop and test the location features before the owner supplies the
historical city code and the reviewed production dataset. `DATA_CONTRACTS.json` lists
`approved_production_zip_rows: []`, so no row here is an approved service decision.

- Every row is imported with `dataset = fixture` and `source_reference = fixture:local-dev-2026-10-20`.
- Fixture rows count only when `wp_get_environment_type()` is `local` or `development`
  (`CIC\Locations\Tables::fixtures_allowed()`). On staging or production they are ignored and the
  importer refuses to load them.
- Cities follow the county lists in the approved Service Areas mockup. Coordinates are rounded
  approximations used only to draw approximate points.
- The service states are **invented test scenarios** chosen to exercise every coverage rule:
  - a fully served city (Pasadena);
  - partially served cities (Glendale, Los Angeles);
  - a ZIP shared with the excluded city Compton (90220);
  - a disabled ZIP that an enabled mapping cannot override (90221);
  - an excluded city (Compton);
  - nonresidential or review ZIPs (90021, 90224, 90810);
  - unreviewed geography (91208, 93023, 93108);
  - ZIPs that are absent (unknown).

Import (local only, repeatable, never duplicates):

    wp cic locations import-fixtures

Replace with the owner's reviewed data through the same importer using `--dataset=production`.
Production rows can never be overwritten by fixture rows.
