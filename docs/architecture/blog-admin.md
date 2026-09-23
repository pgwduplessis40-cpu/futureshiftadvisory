# Blog administration

**Work order:** WO-125
**Status:** implemented; pending the full validation suite

## Goal

Move public blog content from repository Markdown files to the database so a
super-admin can draft, review, import, publish, unpublish, and delete a post
without a code deployment. The public URLs and reading experience stay the
same.

## Publishing model

`blog_posts` stores a private working copy (`title`, `slug`, `description`,
and Markdown `body`) and a separate published snapshot
(`published_title`, `published_description`, `published_body`). Public readers
only ever receive the snapshot.

| Action | Working copy | Public snapshot | Status and dates |
| --- | --- | --- | --- |
| Save draft | Saves partial values | Unchanged | Draft or published unchanged |
| Publish | Must contain a valid slug plus non-blank title, description, and body | Replaced from working values | Status becomes `published`; `published_at` is set only once; `published_revision_at` changes on every publish |
| Unpublish | Unchanged | Retained privately | Status becomes `draft`; original publication date is retained |

Editing a published post never changes its live version until an explicit
Publish action. A slug is generated from the title when blank, can be edited
before the first publication, and is frozen afterwards because redirects are
out of scope. `published_at` is the immutable first-publication timestamp;
`published_revision_at` supplies the structured-data `dateModified` value.

## Access, rendering, and content limits

- All administration routes require authenticated, verified, MFA-complete
  super-admin access and a `BlogPostPolicy` check.
- PostgreSQL RLS permits public reads only of complete published snapshots;
  super-admins can read all rows and are the only writers. Other roles cannot
  read drafts or write.
- Every create, draft save, publish, unpublish, and delete operation records a
  metadata-only audit event in the same transaction as the data change.
- Markdown is stored as source and rendered by one CommonMark service for both
  public posts and admin preview. Raw HTML is escaped and unsafe links are
  disabled.
- Dates display in `Pacific/Auckland`. `datePublished` remains the immutable
  first-publish date; `dateModified` uses the publication revision timestamp.
- Form and importer limits are title 200 characters, slug 200, description
  300, body 100,000, and upload 512 KB. Import accepts only UTF-8 `.md` files
  with exactly `title`, `description`, and `date` frontmatter fields. It parses
  in memory to pre-fill the create form; the uploaded file and its date are not
  persisted.

## Public cutover

`BlogPosts` is the single published-post read service used by the public blog,
sitemap, and `llms.txt`; drafts are absent from all three. The legacy article
is inserted idempotently by the migration before RLS is enabled, retaining its
`2026-09-22` New Zealand publication date and existing URL.

## Deliberately not included

Images or a media library, tags, categories, comments, scheduled publishing,
rich-text editing, and slug redirects are not part of this version.

## Verification expectations

The feature tests cover public and draft-leak behaviour, save-versus-publish
separation, immutable first-publication dates, structured-data modification
dates, slug freezing, Markdown hardening, importer failures, audit atomicity,
and the PostgreSQL RLS role matrix. Project checks include PHPUnit, Pint,
ESLint, Prettier, and TypeScript.
