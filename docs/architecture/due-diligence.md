# Due diligence

WO-75 starts the DD track with onboarding, target isolation, the DD-specific
questionnaire, and the standard liability disclaimer. Later WOs add the data
room, workstreams, valuation, plan builder, report, and post-acquisition
conversion.

## Onboarding

`DdOnboarding::start()` creates a `dd_engagements` row only when:

- the buyer client uses the `due_diligence` engagement type
- a fresh `ConflictDeclarer::DUE_DILIGENCE` declaration exists for the same
  advisor and client
- the DD-specific questionnaire is published

The target business is stored on `dd_engagements.target_details`, not on the
buyer `clients` row. The advisor client detail page receives a
`due_diligence` payload and renders an acquisition-target panel so buyer data
and target data stay visually separate.

## Questionnaire

`DdSpecificQuestionnaireSeeder` publishes version 1 of the `dd_specific`
questionnaire. DD clients use that set only during onboarding; standard advisory
is deferred until the post-acquisition gap flow.

## Disclaimer

Every DD engagement records acknowledgement of the standard disclaimer:
FSA support is advisory only and is not legal, tax, accounting, investment, or
acquisition advice. A qualified New Zealand lawyer and accountant must be
engaged before any acquisition decision, and FSA accepts no liability for
acquisition decisions made from platform DD outputs.
