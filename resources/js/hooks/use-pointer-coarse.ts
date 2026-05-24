import { useEffect, useState } from 'react';

const COARSE_POINTER_QUERY = '(hover: none), (pointer: coarse)';

function pointerIsCoarse() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia(COARSE_POINTER_QUERY).matches;
}

export function usePointerCoarse() {
    const [isCoarse, setIsCoarse] = useState(pointerIsCoarse);

    useEffect(() => {
        const query = window.matchMedia(COARSE_POINTER_QUERY);
        const update = () => setIsCoarse(query.matches);

        update();
        query.addEventListener('change', update);

        return () => query.removeEventListener('change', update);
    }, []);

    return isCoarse;
}
