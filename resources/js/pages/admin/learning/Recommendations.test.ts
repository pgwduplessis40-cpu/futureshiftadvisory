import assert from 'node:assert/strict';
import test from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { PlainEnglishSummaryBlock } from './LearningDisplay';
import {
    RecommendationDeliveryPanel,
    RecommendationDraft,
} from './Recommendations';
import type { LearningRecommendation } from './Recommendations';

const recommendation: LearningRecommendation = {
    id: 'recommendation-1',
    learning_update_id: 'learning-1',
    title: 'Calibrate scoring language',
    failure_shortfall: 'Praise exceeds the supporting evidence.',
    impact: 'Advice may overstate confidence.',
    impact_area: 'Entrepreneur scoring',
    recommendation: 'Use evidence-calibrated criteria.',
    recommendation_impact: 'Improve accuracy without changing client content.',
    acceptance_criteria: ['Criteria match the evidence.'],
    regression_journeys: ['Entrepreneur assessment'],
    delivery_owner: null,
    delivery_target: null,
    baseline_metrics: [],
    rollback_plan: null,
    status: 'draft',
    approved_at: null,
    development_reference: null,
    release_reference: null,
    released_at: null,
    verified_at: null,
    verification_notes: null,
    review_due_at: null,
    approve_url: '/admin/learning-recommendations/recommendation-1/approve',
    delivery_url: '/admin/learning-recommendations/recommendation-1/delivery',
};

function renderDelivery(status: string): string {
    return renderToStaticMarkup(
        createElement(RecommendationDeliveryPanel, {
            recommendations: [{ ...recommendation, status }],
        }),
    );
}

test('an empty recommendation queue adds no delivery panel', () => {
    assert.equal(
        renderToStaticMarkup(
            createElement(RecommendationDeliveryPanel, {
                recommendations: [],
            }),
        ),
        '',
    );
});

test('draft recommendations retain the admin approval action and evidence context', () => {
    const html = renderDelivery('draft');
    assert.match(html, /Approve for development review/);
    assert.match(html, /Praise exceeds the supporting evidence/);
    assert.match(html, /Improve accuracy without changing client content/);
    assert.match(html, /Entrepreneur assessment/);
    assert.doesNotMatch(html, /Update delivery/);
});

test('approved recommendations start development without exposing release or verification controls', () => {
    const html = renderDelivery('approved');
    assert.match(html, /value="in_development" selected/);
    assert.match(html, /Development reference/);
    assert.match(html, /Update delivery/);
    assert.doesNotMatch(html, /placeholder="Deployment\/version evidence"/);
    assert.doesNotMatch(html, /Verification evidence/);
});

test('released recommendations retain release and verification evidence controls', () => {
    const html = renderDelivery('released');
    assert.match(html, /value="verified" selected/);
    assert.match(html, /Release reference/);
    assert.match(html, /Verification evidence/);
    assert.match(html, /value="rolled_back"/);
});

test('the recommendation form retains all governed fields and the no-automatic-change notice', () => {
    const html = renderToStaticMarkup(
        createElement(RecommendationDraft, {
            learningUpdateId: recommendation.learning_update_id,
            defaults: recommendation,
        }),
    );

    for (const label of [
        'Failure / shortfall',
        'Impact',
        'Area of impact',
        'Recommendation',
        'Impact of recommendation',
        'Acceptance criteria (one per line)',
        'Regression journeys to verify (one per line)',
        'Save recommendation for approval',
    ]) {
        assert.ok(html.includes(label), label);
    }

    assert.match(html, /It cannot change the live product automatically/);
});

test('learning summaries retain full and compact evidence presentation', () => {
    const summary = {
        what_we_learnt: 'Observed learning',
        why_it_matters: 'Evidence matters',
        review_decision: 'Review before changing advice',
        signals: ['Observed signal'],
    };
    const full = renderToStaticMarkup(
        createElement(PlainEnglishSummaryBlock, { summary }),
    );
    assert.match(full, /Evidence matters/);
    assert.match(full, /Decision needed/);
    assert.match(full, /Observed signal/);
    const compact = renderToStaticMarkup(
        createElement(PlainEnglishSummaryBlock, { summary, compact: true }),
    );
    assert.match(compact, /Review before changing advice/);
    assert.doesNotMatch(
        compact,
        /Evidence matters|Decision needed|Observed signal/,
    );
});
