# Blog administration

**Work order:** WO-125
**Status:** implemented; pending the full validation suite

## Goal

Move public blog content from repository Markdown files to the database so a
super-admin can draft, review, batch import, schedule, publish, unpublish, and
delete a post without a code deployment. The public URLs and reading experience
stay the same.

## Publishing model

`blog_posts` stores a private working copy (`title`, `slug`, `description`,
and Markdown `body`) and a separate published snapshot
(`published_title`, `published_description`, `published_body`). Public readers
only ever receive the snapshot.

| Action | Working copy | Public snapshot | Status and dates |
| --- | --- | --- | --- |
| Save draft | Saves partial values | Unchanged | Draft or published unchanged |
| Publish | Must contain a valid slug plus non-blank title, description, and body | Replaced from working values | Status becomes `published`; `published_at` is set only once; `published_revision_at` changes on every publish |
| Schedule / update schedule | Saves the current working values | Unchanged | Status becomes `scheduled`; an approved scheduled snapshot and UTC due time are stored |
| Automatic due publication | Working copy remains private | Replaced from the approved scheduled snapshot | Status becomes `published` once the due command runs |
| Cancel schedule | Unchanged | Unchanged | Clears the scheduled snapshot and returns the post to draft |
| Unpublish | Unchanged | Retained privately | Status becomes `draft`; original publication date is retained |

Editing a published post never changes its live version until an explicit
Publish action. A slug is generated from the title when blank, can be edited
before the first publication, and is frozen afterwards because redirects are
out of scope. `published_at` is the immutable first-publication timestamp;
`published_revision_at` supplies the structured-data `dateModified` value.

A scheduled post contains a separate approved snapshot
(`scheduled_title`, `scheduled_description`, `scheduled_body`) and
`scheduled_at`. The date picker and the list display use
`Pacific/Auckland`; the timestamp is stored in UTC. Saving later working-copy
edits does not affect that scheduled snapshot. The owner must choose **Update
scheduled version** to deliberately replace it.

## Access, rendering, and content limits

- All administration routes require authenticated, verified, MFA-complete
  super-admin access and a `BlogPostPolicy` check.
- PostgreSQL RLS permits public reads only of complete published snapshots;
  super-admins can read all rows and are the only interactive writers. The
  narrowly scoped `system` command can read and publish due scheduled rows.
  Other roles cannot read drafts or write.
- Every create, draft save, schedule, due publication, publish, unpublish, and
  delete operation records a metadata-only audit event in the same transaction
  as the data change.
- Markdown is stored as source and rendered by one CommonMark service for both
  public posts and admin preview. Raw HTML is escaped and unsafe links are
  disabled.
- Dates display in `Pacific/Auckland`. `datePublished` remains the immutable
  first-publish date; `dateModified` uses the publication revision timestamp.
- Form and importer limits are title 200 characters, slug 200, description
  300, body 100,000, and upload 512 KB. Import accepts only UTF-8 `.md` files
  with exactly `title`, `description`, and `date` frontmatter fields. One file
  parses in memory to pre-fill the create form. A batch of up to 20 files is
  reviewed in memory before its drafts and optional schedules are created; no
  uploaded file or frontmatter date is persisted.
- The index uses visible action labels with matching hover and keyboard-focus
  tooltips. Unpublish and Delete ask for confirmation. Immediate publishing
  returns to the index, which provides a labelled **View live** action.

## Public cutover

`BlogPosts` is the single published-post read service used by the public blog,
sitemap, and `llms.txt`; drafts are absent from all three. The legacy article
is inserted idempotently by the migration before RLS is enabled, retaining its
`2026-09-22` New Zealand publication date and existing URL.

## Deliberately not included

Images or a media library, tags, categories, comments, rich-text editing, and
slug redirects are not part of this version.

## Verification expectations

The feature tests cover public and draft-leak behaviour, save-versus-publish
separation, immutable first-publication dates, structured-data modification
dates, slug freezing, Markdown hardening, importer failures, batch review,
scheduled snapshot isolation, due publication, audit atomicity, and the
PostgreSQL RLS role matrix. Project checks include PHPUnit, Pint, ESLint,
Prettier, and TypeScript.
