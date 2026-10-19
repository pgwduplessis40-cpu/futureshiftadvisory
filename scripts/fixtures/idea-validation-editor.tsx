import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { FormattedTextarea } from '../../resources/js/components/formatted-textarea';
import { usePersistedWorkspaceDraft } from '../../resources/js/hooks/use-persisted-workspace-draft';
import { IDEA_VALIDATION_FIELD_MAX_LENGTH } from '../../resources/js/pages/portal/entrepreneur/plan-types';
import '../../resources/css/app.css';

function EditorFixture() {
    const [problem, setProblem] = useState('');
    const draft = usePersistedWorkspaceDraft({
        url: '/draft',
        data: { problem },
        hydrate: (payload) => setProblem(payload.problem ?? ''),
    });

    return (
        <main className="mx-auto max-w-3xl space-y-4 p-4">
            <h1>Idea Validation</h1>
            <label htmlFor="problem">Customer problem</label>
            <FormattedTextarea
                id="problem"
                value={problem}
                onChange={setProblem}
                rows={6}
                autoGrowMaxRows={12}
                expandable
                ariaLabel="Customer problem"
                maxLength={IDEA_VALIDATION_FIELD_MAX_LENGTH}
            />
            <output id="draft-state">{draft.state}</output>
            <FormattedTextarea
                id="plan-detail"
                value="Existing plan detail"
                onChange={() => undefined}
                rows={8}
                ariaLabel="Plan detail"
            />
        </main>
    );
}

createRoot(document.getElementById('root')!).render(<EditorFixture />);
