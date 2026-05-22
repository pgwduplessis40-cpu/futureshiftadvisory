# PV Integration And Waterfall

WO-43 surfaces PV outputs from WO-41 and WO-42 in a shared waterfall shape.

## Waterfall Builder

`PvWaterfallBuilder` assembles dashboard/report-ready data from:

- latest `business_valuations.reconciled_mid` as current PV
- total `improvement_opportunities.pv_of_impact`
- total `risk_costs.pv_of_cost` as risk-mitigation value
- target PV = current PV + improvement PV + risk-mitigation PV

The builder emits both summary totals and per-client waterfall steps:

- Current PV
- Improvements
- Risk mitigation
- Target PV

This data shape is server-side and reusable by future report generation.

## Report Chart

`PvWaterfallReportChart` renders the same waterfall steps through a Blade partial at
`resources/views/reports/partials/pv-waterfall-chart.blade.php`. The report engine
in WO-57 can embed that HTML in Browsershot-rendered PDFs without recalculating PV
or reshaping the data.

## Dashboard Surface

The advisor dashboard receives `pvWaterfall` in its Inertia payload. The first available client is rendered through `WaterfallChart`; the summary badges show current and target PV across visible clients.

## Boundaries

WO-43 does not create new PV calculations. It only reads the PV tables produced by WO-41 and WO-42 and presents reconciled numbers.

PV-to-milestone tracking remains Phase 3.
