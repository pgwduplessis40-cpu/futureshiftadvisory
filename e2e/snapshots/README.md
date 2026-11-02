# Approved authenticated-browser baselines

These ten images are the visual compliance record for the authenticated browser
quality gate. The initial set was captured from GitHub Actions browser-e2e run
`#20` for commit `51337a7aa12fbc8b9eb800f85dfa216cfbd7a600` and reviewed and
approved on 2026-08-27.

The source artifact digest is
`sha256:be2ed08d4278ccf470effccdb28623b1c0ac3892a8dd95fb899f69709d3ef261`.

The Client Screen desktop and mobile baselines were updated from browser-e2e
run `#172` for commit `b76f9552` and reviewed and approved on 2026-09-04. Their
source artifact digest is
`sha256:4e94bcadb64096ef3afb4ff61f9a006e7ef4758203a26c3d48a5f441ee50fb8d`.

The Client Screen desktop and mobile baselines were updated for the approved
Actions-first client workflow from browser-e2e run `#349` for commit
`9f0d9324`. They were visually reviewed and approved on 2026-09-16. Their
source artifact digest is
`sha256:cc6a4650474ca413cf1cddbef8cfb43e743f70e0e985374c61c2fb3bd3960587`.

The Budget and Runway Builder desktop and mobile baselines were updated for
the approved optional Business Plan & Budget journey from browser-e2e run
`#394` for commit `0d34e667`. They were visually reviewed and approved on
2026-09-21. The Standard Advisory fixture now sees the FSA quote-and-approval
journey before its Business Plan & Budget workspace is opened. Their source
artifact digest is
`sha256:dd7b2926a3221d117fa6e2f767047b34ef4cdc8fb8c8967032b38fdc6fe292f1`.

The Budget and Runway Builder desktop baseline was visually reviewed and
approved on 2026-11-02 from browser-e2e run `#584`, attempt 2, for commit
`a32e8791538e86a6b54772d59b48caf78f5bad4c`. It preserves the fixed-fee request
journey, acknowledgement, navigation and visible keyboard focus, and includes
the screen-support ready indicator. The previous baseline differed at text
edges and omitted that indicator; no copy or layout change was approved.
The two independent main-branch attempts produced matching desktop captures
(zero differing pixels under the existing comparison settings). Accessibility,
HTTP/console checks and screen-support collaboration reported no failures.
The source artifact is `13126257867` from run `41411002468`, with digest
`sha256:a7ad1399239921be8851bfd283087d72e7e617caca1be57c0fe9ab1751f03fbc`.
The comparison threshold and visual-noise budget remain unchanged.

Each flow has a desktop (1440px) and mobile (390px) capture. Do not replace a
baseline merely to make CI pass: review the resulting image and record an
explicit approval with the change.
