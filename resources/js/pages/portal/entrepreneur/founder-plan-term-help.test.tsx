import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import {
    FounderPlanTermLabel,
    IdeaValidationFieldLabel,
} from './founder-plan-term-help';

test('idea validation labels explain the non-financial business terms founders meet first', () => {
    const html = renderToStaticMarkup(
        <>
            <IdeaValidationFieldLabel label="Target customer" />
            <IdeaValidationFieldLabel label="Demand signal" />
            <IdeaValidationFieldLabel label="Revenue model" />
        </>,
    );

    assert.match(html, /aria-label="Explain Target customer"/);
    assert.match(html, /aria-label="Explain Demand signal"/);
    assert.match(html, /aria-label="Explain Revenue model"/);
});

test('operating model help explains its connection to budget costs and capacity', () => {
    const html = renderToStaticMarkup(
        <FounderPlanTermLabel term="operating-model">
            Operating model
        </FounderPlanTermLabel>,
    );

    assert.match(html, /aria-label="Explain Operating model"/);
});
