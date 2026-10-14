import assert from 'node:assert/strict';
import test from 'node:test';
import { renderToStaticMarkup } from 'react-dom/server';
import { PlanRequirementsLayout } from './plan-requirements-layout';

test('plan requirements have a visible collapse control and clear incomplete status', () => {
    const html = renderToStaticMarkup(
        <PlanRequirementsLayout
            phases={[
                {
                    key: 'foundation',
                    title: 'Foundation',
                    requirements: [
                        {
                            key: 'financial-assumptions',
                            phase_key: 'foundation',
                            phase_title: 'Foundation',
                            title: 'Financial assumptions',
                            description: 'Set the Budget assumptions.',
                            complete: false,
                            section_id: null,
                        },
                    ],
                },
            ]}
            selectedRequirement={null}
            onSelect={() => undefined}
        >
            <div>Budget editor</div>
        </PlanRequirementsLayout>,
    );

    assert.match(html, /Minimise plan steps/);
    assert.match(html, /Financial assumptions: Needs attention/);
    assert.match(html, /Hide this list for more editing room/);
    assert.match(html, /data-co-browse-target="entrepreneur.plan.steps"/);
});
