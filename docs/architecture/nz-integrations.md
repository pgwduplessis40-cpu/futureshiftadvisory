# NZ integration scaffolds

WO-13 creates the Phase 1 integration surface for registry and tax lookups without requiring live third-party credentials.

## Active clients

The active Phase 1 clients are:

- `NzbnClient::lookupByNzbn(string $nzbn)`
- `CompaniesOfficeClient::companyProfile(string $nzbn)`
- `CompaniesOfficeClient::directorsForCompany(string $nzbn)`
- `CompaniesEntityRoleSearchClient::search(string $name, string $roleType = 'ALL')`
- `PpsrClient::securityInterests(string $nzbn)`
- `IrdClient::gstStatus(string $nzbn)` (regulatory-deferred; returns a client-supplied/not-IRD-verified status until approval is available)
- `FspClient::lookup(string $fspNumber)` (WO-71 broker panel validation)

Application code resolves the interfaces from the container. It should not instantiate live or fake clients directly.

## Runtime behavior

`IntegrationServiceProvider` binds each active interface to a fallback resolver. The resolver asks the live client first; if the live feature flag is off, the live client throws `IntegrationDisabledException` and the resolver returns the deterministic stub.

Live mode is controlled by:

- `FEATURE_NZBN_LIVE`
- `FEATURE_COMPANIES_OFFICE_LIVE`
- `FEATURE_COMPANIES_ENTITY_ROLE_SEARCH_LIVE`
- `FEATURE_PPSR_LIVE`
- `FEATURE_IRD_LIVE` (ignored while the IRD registry entry remains deferred)

When a live flag is on but no credential is configured, the live client still routes through `ResilientHttp`. The call fails into the WO-05 resilience ledger, then returns cached data if available or the fixture-backed stub with a `source_badge` of `stub_live_fallback`.

IRD is the exception. Inland Revenue declined FSA's current Gateway Services use case because it relies on accessing IRD data for advisory/customer verification purposes rather than helping customers meet tax obligations or sending data back to IRD. The registry marks IRD as `deferred` pending the proposed Data Consumer intermediary category, currently anticipated from 2027. Until that category is available and FSA is approved, the app must label IRD/GST values as client supplied and not verified with IRD, and reports/proposals must treat this as a regulatory limitation rather than a successful source check.

MBIE confirmed that public company profile, directors, shareholdings, registration status, and Incorporated Societies public data should be sourced through the NZBN API rather than the Companies API. The live `CompaniesOfficeClient` therefore uses the NZBN subscription-key gateway and exists as a compatibility adapter for older application call sites. Companies Entity Role Search is a separate live client for director/shareholder name searches.

PPSR live support is wired for debtor-organisation searches via `financing-statements-search`, but fee-bearing search use still depends on the PPSR sandbox/production organisation setup and direct-debit approval steps. Until those prerequisites are complete, PPSR degrades through the resilience layer to fixtures.

MBIE also confirmed there is no FSPR API. Broker FSP checks remain fixture/manual/bulk-data backed until a monthly FSPR bulk data import is added.

## Fixture data

Stub data lives in `database/fixtures/integration/*.json`.

The known test NZBN is `9429000000000`, which resolves to canned NZBN, Companies Office, and PPSR records. IRD/GST stubs intentionally return a regulatory-deferred payload rather than verified IRD data. The known broker FSP fixtures are `FSP100001` (current) and `FSP999999` (lapsed). Stub payloads include:

- `source_badge` for UI/source stamping
- `degraded` when the response came from fallback or a missing fixture
- `correlation_id` when the response passed through `ResilientHttp`

## Empty named scaffolds

WO-13 also creates interface plus fake class files for the remaining planned integrations: LINZ, IPONZ, Stats NZ, RBNZ, MBIE, NZ Parliament, WorkSafe, Stripe, Windcave, Xero, MYOB, QuickBooks, SES/SendGrid, Whisper, Google Calendar, and Microsoft Graph.

These files intentionally contain no behavior. Future WOs should fill in methods only when a product flow needs them, and live network calls must still use `ResilientHttp`.

FSP is no longer an empty scaffold after WO-71. It has fixture and fallback
clients and is used by the broker portal approval and re-verification flow.
There is no FSPR live API; future live updates should come from an approved bulk
data import process.

## NZ business tools

WO-113 adds active clients for Employment Hero, Cin7, and Tradify. These follow
the same fake/live/fallback structure, but their OAuth connection state and
encrypted token storage live in `NzToolConnector` and `nz_tool_connections`.

Live flags are disabled by default:

- `FEATURE_EMPLOYMENT_HERO_LIVE`
- `FEATURE_CIN7_LIVE`
- `FEATURE_TRADIFY_LIVE`

Operational snapshots preserve `source_badge`, `degraded`, and
`correlation_id` fields so advisor-facing workflows can distinguish live,
cached, and fixture-backed data.
