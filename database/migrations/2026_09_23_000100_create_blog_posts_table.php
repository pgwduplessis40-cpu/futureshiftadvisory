<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 200)->unique();
            $table->string('title', 200);
            $table->string('description', 300);
            $table->text('body');
            $table->string('status', 16)->default('draft');
            $table->string('published_title', 200)->nullable();
            $table->string('published_description', 300)->nullable();
            $table->text('published_body')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('published_revision_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
            $table->index('author_id');
        });

        $this->insertLegacyPost();
        $this->enableRowLevelSecurity();
    }

    public function down(): void
    {
        if ($this->onPostgres()) {
            DB::unprepared(<<<'SQL'
                DROP POLICY IF EXISTS blog_posts_admin_delete ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_admin_update ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_admin_insert ON blog_posts;
                DROP POLICY IF EXISTS blog_posts_public_read ON blog_posts;
            SQL);
        }

        Schema::dropIfExists('blog_posts');
    }

    private function insertLegacyPost(): void
    {
        $publishedAt = CarbonImmutable::create(2026, 9, 22, 0, 0, 0, 'Pacific/Auckland')->utc();
        $body = <<<'MARKDOWN'
Most guides on how to start a business in New Zealand open with the paperwork - register a company, get an IRD number, open a bank account. That is the easy part, and doing it first is how people end up with a tidy company structure wrapped around an idea that was never going to work.

Start the other way round. Here is the order we walk founders through, and why it saves you money.

## First, find out whether the idea holds up

Before you register anything or spend real money, it is worth knowing what you are spending it on. Most business ideas fail for reasons that were visible at the start, to someone who knew where to look: there was not enough demand, the numbers never worked, or someone was already doing it better.

Testing that is called idea validation, and it is the cheapest step to get right first. You are looking for honest answers to four questions:

- **Demand** - is anyone genuinely willing to pay for this?
- **Competition** - who is already doing it, and what would make you different?
- **Feasibility** - what would it really take to build and deliver?
- **The numbers** - what has to be true for this to make money?

Sometimes the honest answer is "not in this form, and here is why." Hearing that after a conversation costs you very little. Hearing it two years and a mortgage later costs a great deal.

## Write a business plan you will actually use

Once the idea stands up, write it down as the thing you will run the business from - not a document you write once to satisfy a bank and never open again.

A useful plan covers the plan and the budget together, with real numbers worked through rather than guessed: what you will spend, what you need to earn, when you break even, and what happens if it takes longer than you hope. The moment anyone else's money is involved - a bank, an investor, a grant funder - this is what they will want to see.

## Choose a structure: sole trader or company

In New Zealand the two common starting points are:

- **Sole trader** - the simplest. You trade under your own IRD number, keep the admin light, and you are personally responsible for the debts. Good for testing something small.
- **Company** - a separate legal entity registered with the Companies Office. It limits your personal liability, looks more credible to investors and some customers, and comes with more compliance.

There is no single right answer - it depends on the risk, who you are dealing with, and where you want to take it. Many founders start as a sole trader and incorporate a company once the idea is proven and the stakes rise.

## Register the right things

When you are ready to trade, the practical checklist is roughly:

- **IRD number** - a company needs its own; a sole trader uses their personal one.
- **Companies Office** - register the company, with its directors and shareholders, if you are incorporating.
- **NZBN** - the New Zealand Business Number, a unique identifier every business can get.
- **GST** - you must register once your turnover passes $60,000 in any twelve-month period; you can register voluntarily below that.

Requirements change, so check the current details on the government's own sites before you file - but that is the shape of it.

## Get funding-ready before you ask

Banks, investors, and grant funders all want the same three things: believable numbers, a clear plan, and evidence you have thought about what could go wrong.

Get those in order before you approach anyone, because you generally get one good first impression per funder. Spending it on a half-ready pitch is an expensive way to learn.

## The order that saves you money

Put simply: validate the idea, write the plan and budget, choose a structure, register, then raise. The paperwork is real, but it is not where the risk is. The risk is in the idea and the numbers - so that is where to spend your attention first, while every option is still open and nothing has been built yet.

And you do not have to quit your job to do any of this. The early stretch - validating, planning, getting funding-ready - can be done in your own time, alongside the work you already have, so you make the leap on evidence rather than hope.
MARKDOWN;

        DB::table('blog_posts')->insertOrIgnore([
            'id' => '48ce989c-a221-4d29-abcd-0d9ce6df3f48',
            'slug' => 'how-to-start-a-business-in-new-zealand',
            'title' => 'How to start a business in New Zealand',
            'description' => 'A practical, honest guide for first-time founders - from testing the idea to registering and getting funding-ready, in the order that actually saves you money.',
            'body' => $body,
            'status' => 'published',
            'published_title' => 'How to start a business in New Zealand',
            'published_description' => 'A practical, honest guide for first-time founders - from testing the idea to registering and getting funding-ready, in the order that actually saves you money.',
            'published_body' => $body,
            'published_at' => $publishedAt,
            'published_revision_at' => $publishedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableRowLevelSecurity(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE blog_posts ENABLE ROW LEVEL SECURITY;
            ALTER TABLE blog_posts FORCE ROW LEVEL SECURITY;

            CREATE POLICY blog_posts_public_read ON blog_posts
                FOR SELECT
                USING (
                    (
                        status = 'published'
                        AND published_title IS NOT NULL
                        AND published_description IS NOT NULL
                        AND published_body IS NOT NULL
                        AND published_at IS NOT NULL
                    )
                    OR fsa_current_role() = 'super_admin'
                );

            CREATE POLICY blog_posts_admin_insert ON blog_posts
                FOR INSERT
                WITH CHECK (fsa_current_role() = 'super_admin');

            CREATE POLICY blog_posts_admin_update ON blog_posts
                FOR UPDATE
                USING (fsa_current_role() = 'super_admin')
                WITH CHECK (fsa_current_role() = 'super_admin');

            CREATE POLICY blog_posts_admin_delete ON blog_posts
                FOR DELETE
                USING (fsa_current_role() = 'super_admin');
        SQL);
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
