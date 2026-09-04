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

Each flow has a desktop (1440px) and mobile (390px) capture. Do not replace a
baseline merely to make CI pass: review the resulting image and record an
explicit approval with the change.
