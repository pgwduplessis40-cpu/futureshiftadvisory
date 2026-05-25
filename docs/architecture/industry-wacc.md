# Industry WACC Automation

WO-116 adds an industry WACC reference feed for PV discount-rate automation.

## Feed

`industry-wacc:refresh` imports MBIE-backed industry WACC records through the existing integration resilience pattern. Active records live in `industry_wacc_data`; a new record supersedes prior active rows for the same industry and source.

The quarterly scheduler runs after valuation multiple refresh:

- valuation multiples: `04:00` on the first day of Jan/Apr/Jul/Oct
- industry WACC: `04:15` on the same days

## Resolver Behavior

`DiscountRateResolver` still respects explicit `rate` or `industry_wacc` options. When `DiscountMethod::IndustryWacc` is requested without an explicit rate, it resolves the client industry code and uses the latest active `industry_wacc_data` row.

If no reference row exists, the resolver falls back to the existing conservative default and attributes the source to `industry_wacc:default`.
