# NZ integration scaffolds

WO-13 creates the Phase 1 integration surface for registry and tax lookups without requiring live third-party credentials.

## Active clients

The active Phase 1 clients are:

- `NzbnClient::lookupByNzbn(string $nzbn)`
- `CompaniesOfficeClient::companyProfile(string $nzbn)`
- `CompaniesOfficeClient::directorsForCompany(string $nzbn)`
- `IrdClient::gstStatus(string $nzbn)`

Application code resolves the interfaces from the container. It should not instantiate live or fake clients directly.

## Runtime behavior

`IntegrationServiceProvider` binds each active interface to a fallback resolver. The resolver asks the live client first; if the live feature flag is off, the live client throws `IntegrationDisabledException` and the resolver returns the deterministic stub.

Live mode is controlled by:

- `FEATURE_NZBN_LIVE`
- `FEATURE_COMPANIES_OFFICE_LIVE`
- `FEATURE_IRD_LIVE`

When a live flag is on but no credential is configured, the live client still routes through `ResilientHttp`. The call fails into the WO-05 resilience ledger, then returns cached data if available or the fixture-backed stub with a `source_badge` of `stub_live_fallback`.

## Fixture data

Stub data lives in `database/fixtures/integration/*.json`.

The known test NZBN is `9429000000000`, which resolves to canned NZBN, Companies Office, and IRD/GST records. Stub payloads include:

- `source_badge` for UI/source stamping
- `degraded` when the response came from fallback or a missing fixture
- `correlation_id` when the response passed through `ResilientHttp`

## Empty named scaffolds

WO-13 also creates interface plus fake class files for the remaining planned integrations: FSP, PPSR, LINZ, IPONZ, Stats NZ, RBNZ, MBIE, NZ Parliament, WorkSafe, Stripe, Windcave, Xero, MYOB, QuickBooks, SES/SendGrid, Whisper, Google Calendar, and Microsoft Graph.

These files intentionally contain no behavior. Future WOs should fill in methods only when a product flow needs them, and live network calls must still use `ResilientHttp`.
