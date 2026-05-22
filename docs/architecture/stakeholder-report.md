# Stakeholder report and PowerPoint export

WO-58 adds the stakeholder report profile on top of the WO-57 report engine.
It is intended for boards, banks, investors, and other external decision-makers.

## Redaction Rules

Stakeholder reports include persisted valuation, PV waterfall, diagnostic,
predictive, and prescriptive findings, plus a liability disclaimer. They exclude
Future Shift Advisory methodology/IP notes and do not include advisor-only fee
proposal sections.

The report metadata records `redactions = ["fsa_methodology", "fsa_ip"]` so
future export/download surfaces can display the applied profile.

## Exports

`ReportComposer` renders the branded PDF through `PdfRenderer` and, for
stakeholder reports only, renders a `.pptx` artifact through the
`PptxGenerator` contract. The default `OpenXmlPptxGenerator` emits a simple
Open XML presentation with a title slide and one slide per report section. Tests
bind a fake generator so CI does not depend on PowerPoint.

Both PDF and PowerPoint paths and byte sizes are stored on `reports`. The
artifacts are persisted on `secure_local`.

## Liability Disclaimer

Every stakeholder report includes a dedicated liability disclaimer section. The
same section is present in the generated PDF and in the PowerPoint payload.
