# Valuation Multiple Data Feed

WO-39 adds the NZ-benchmarked reference-data feed that future PV and business valuation work will consume. It persists industry-level EBITDA and SDE multiple ranges without performing valuation calculations.

## Runtime Shape

`valuation-multiples:refresh` is the operational entry point. It accepts:

- `--quarter=2026Q2` for deterministic quarter labels
- `--fetched-at=...` for deterministic test/operator runs

The scheduler runs the command quarterly at 04:00 on the first day of January, April, July, and October.

## Source Data

WO-39 extends the MBIE integration client with `valuationMultiples()`. The fixture feed contains MBIE and NZ Business Brokers reference rows, while live mode uses the existing `ResilientHttp` pattern and degrades to fixture-backed rows when credentials or upstream calls fail.

The live source/licence question remains an owner input before production live mode. Until then, fixtures make the refresh deterministic for tests and local development.

## Storage Contract

`valuation_multiples` is append-style reference data. A row stores:

- industry code and label
- metric (`ebitda` or `sde`)
- low, mid, and high multiples
- source (`mbie` or `nz_business_brokers`)
- source badge/degraded/correlation metadata
- source quarter and fetch timestamp
- optional `superseded_at`
- a unique `record_hash` for idempotent refreshes

When a refresh imports a new record for the same industry, metric, and source, the prior active row is marked with `superseded_at`. Re-running the same source data does not create duplicate rows or candidates.

## Lookup

`ValuationMultipleProvider` exposes the lookup surface for WO-41:

- `lookup()` returns the active `ValuationMultiple` model
- `rangeFor()` returns a compact low/mid/high array with a `valuation_multiple:{id}` source reference

If no source is supplied, active NZ Business Brokers rows are preferred over MBIE when fetch recency is equal.

## Governed Candidates

Every refresh that imports new active rows creates at most one `learning_updates` candidate with `layer_id=13` and `source.type=valuation_multiple_refresh`.

The candidate asks for review of valuation multiple assumptions and sets `automatic_application=false`. WO-39 never mutates PV assumptions, existing valuations, or analysis output; that remains WO-41 and later.
