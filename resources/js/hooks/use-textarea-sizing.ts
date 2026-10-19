import { useLayoutEffect, useRef } from 'react';
import type { RefObject } from 'react';

type Options = {
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    value: string;
    rows: number;
    maxRows?: number;
    expanded: boolean;
};

export function useTextareaSizing({
    textareaRef,
    value,
    rows,
    maxRows,
    expanded,
}: Options) {
    const sizing = useRef({ height: 0, manualHeight: 0, expanded: false });

    useLayoutEffect(() => {
        const textarea = textareaRef.current;

        if (!textarea || maxRows === undefined) {
            return;
        }

        const resize = () => {
            const previous = sizing.current;
            const { height } = textarea.getBoundingClientRect();

            // Keep a height chosen with the native resize handle while typing.
            if (
                previous.height > 0 &&
                previous.expanded === expanded &&
                !expanded &&
                Math.abs(height - previous.height) > 1
            ) {
                previous.manualHeight = height;
            }

            const style = window.getComputedStyle(textarea);
            const lineHeight = parseFloat(style.lineHeight);
            const padding =
                parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
            const border =
                parseFloat(style.borderTopWidth) +
                parseFloat(style.borderBottomWidth);
            const minimum = rows * lineHeight + padding + border;
            const maximum = Math.max(minimum, window.innerHeight * 0.75);
            const automaticMaximum = Math.min(
                maximum,
                Math.max(rows, maxRows) * lineHeight + padding + border,
            );
            const scrollTop = textarea.scrollTop;

            textarea.style.minHeight = `${minimum}px`;
            textarea.style.maxHeight = `${maximum}px`;
            textarea.style.height = '0px';

            const contentHeight = textarea.scrollHeight + border;
            const nextHeight = expanded
                ? Math.max(automaticMaximum, window.innerHeight * 0.6)
                : Math.max(
                      previous.manualHeight,
                      Math.min(contentHeight, automaticMaximum),
                  );

            textarea.style.height = `${Math.min(maximum, Math.max(minimum, nextHeight))}px`;
            textarea.scrollTop = scrollTop;
            sizing.current = {
                height: textarea.getBoundingClientRect().height,
                manualHeight: previous.manualHeight,
                expanded,
            };
        };

        resize();

        // Reflow wrapped answers when the grid or viewport changes width.
        let width = textarea.clientWidth;
        const observer = new ResizeObserver(() => {
            if (textarea.clientWidth !== width) {
                width = textarea.clientWidth;
                resize();
            }
        });

        observer.observe(textarea);
        window.addEventListener('resize', resize);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', resize);
        };
    }, [textareaRef, value, rows, maxRows, expanded]);
}
