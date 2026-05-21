# WO-11 terms acceptance gate

WO-11 turns the WO-10 versioned terms model into the authenticated access gate.

## Gate rules

`RequireAcceptedTerms` runs on the web stack after session security. It only acts once the user is authenticated, verified, MFA-enrolled, and MFA-verified.

Allowed routes:

- `terms.*`
- `mfa.*`
- `two-factor.*`
- `verification.*`
- `logout`

If a user has no active acceptance for the latest published material terms, the middleware redirects to `terms.pending`. For material republishes, an existing prior acceptance remains valid until its `expires_at`; after that timestamp, the user is sent back to the gate. Non-material published updates do not force users with any active prior acceptance back through the gate.

If the user previously declined the terms, `suspended_at` and `suspended_reason = terms_declined` keep the account on `terms.declined` until the user accepts. Accepting later clears that suspension state.

## Signed PDF evidence

`SignedAcceptancePdf` renders the accepted version into HTML, including:

- Future Shift Advisory brand heading
- accepted terms version and title
- user name, email, and id
- IP address and user agent
- acceptance timestamp
- exact clause title and body text from the accepted `terms_clauses` rows

`BrowsershotRenderer` is the production renderer and uses Spatie Browsershot. Tests bind `PdfRenderer` to an in-memory fake so CI does not require a live browser.

The PDF bytes are written to the encrypted `secure_local` disk under `terms/acceptances/...`. The raw SHA-256 is not stored. Instead, `KeyEnvelope` encrypts the hash and `terms_acceptances` stores:

- `signed_pdf_path`
- `signed_pdf_sha256_envelope`
- `signed_pdf_envelope_meta`
- `signed_pdf_byte_size`

## Decline notifications

Declining terms creates a declined `terms_acceptances` row, suspends the user, writes `terms.declined`, and sends `TermsDeclinedUrgentNotification` to all current advisor and super-admin users. WO-12 will move this urgent path into the central notification preference resolver.
