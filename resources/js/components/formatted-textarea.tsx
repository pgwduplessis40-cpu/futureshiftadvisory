import { Bold, Italic, List, ListOrdered } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type FormattingAction = 'bold' | 'italic' | 'bullet-list' | 'numbered-list';

type FormattingControl = {
    action: FormattingAction;
    label: string;
    Icon: LucideIcon;
};

type FormatResult = {
    value: string;
    selectionStart: number;
    selectionEnd: number;
};

type FormattedTextareaProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    rows?: number;
    maxLength?: number;
    placeholder?: string;
    disabled?: boolean;
    ariaLabel?: string;
    className?: string;
    textareaClassName?: string;
};

type FormattedMarkdownProps = {
    value: string | null | undefined;
    className?: string;
    emptyFallback?: ReactNode;
};

type MarkdownBlock =
    | {
          type: 'paragraph';
          lines: string[];
      }
    | {
          type: 'unordered-list';
          items: string[];
      }
    | {
          type: 'ordered-list';
          items: string[];
      };

const formattingControls: FormattingControl[] = [
    { action: 'bold', label: 'Bold', Icon: Bold },
    { action: 'italic', label: 'Italic', Icon: Italic },
    { action: 'bullet-list', label: 'Bullet list', Icon: List },
    { action: 'numbered-list', label: 'Numbered list', Icon: ListOrdered },
];

export function FormattedTextarea({
    id,
    value,
    onChange,
    rows = 4,
    maxLength,
    placeholder,
    disabled = false,
    ariaLabel,
    className,
    textareaClassName,
}: FormattedTextareaProps) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const applyFormatting = (action: FormattingAction) => {
        const textarea = textareaRef.current;
        const selectionStart = textarea?.selectionStart ?? value.length;
        const selectionEnd = textarea?.selectionEnd ?? value.length;
        const result = formatValue(value, selectionStart, selectionEnd, action);

        if (maxLength !== undefined && result.value.length > maxLength) {
            textarea?.focus();

            return;
        }

        onChange(result.value);

        window.requestAnimationFrame(() => {
            const activeTextarea = textareaRef.current;

            activeTextarea?.focus();
            activeTextarea?.setSelectionRange(
                result.selectionStart,
                result.selectionEnd,
            );
        });
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border bg-background shadow-xs',
                disabled && 'opacity-60',
                className,
            )}
        >
            <TooltipProvider>
                <div className="flex h-10 items-center gap-1 border-b bg-muted/20 px-2">
                    {formattingControls.map(({ action, label, Icon }) => (
                        <Tooltip key={action}>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 rounded-md"
                                    aria-label={label}
                                    disabled={disabled}
                                    onClick={() => applyFormatting(action)}
                                >
                                    <Icon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{label}</TooltipContent>
                        </Tooltip>
                    ))}
                </div>
            </TooltipProvider>
            <Textarea
                ref={textareaRef}
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={rows}
                maxLength={maxLength}
                placeholder={placeholder}
                disabled={disabled}
                aria-label={ariaLabel}
                className={cn(
                    'min-h-0 resize-y rounded-none border-0 bg-transparent shadow-none focus-visible:border-transparent focus-visible:ring-0',
                    textareaClassName,
                )}
            />
        </div>
    );
}

export function FormattedMarkdown({
    value,
    className,
    emptyFallback = '-',
}: FormattedMarkdownProps) {
    const blocks = parseMarkdownBlocks(value ?? '');

    if (blocks.length === 0) {
        return (
            <span className={cn('text-sm', className)}>{emptyFallback}</span>
        );
    }

    return (
        <div className={cn('space-y-2 text-sm leading-relaxed', className)}>
            {blocks.map((block, blockIndex) => {
                if (block.type === 'unordered-list') {
                    return (
                        <ul
                            key={`block-${blockIndex}`}
                            className="ml-5 list-disc space-y-1"
                        >
                            {block.items.map((item, itemIndex) => (
                                <li key={`item-${itemIndex}`}>
                                    {renderInlineMarkdown(item)}
                                </li>
                            ))}
                        </ul>
                    );
                }

                if (block.type === 'ordered-list') {
                    return (
                        <ol
                            key={`block-${blockIndex}`}
                            className="ml-5 list-decimal space-y-1"
                        >
                            {block.items.map((item, itemIndex) => (
                                <li key={`item-${itemIndex}`}>
                                    {renderInlineMarkdown(item)}
                                </li>
                            ))}
                        </ol>
                    );
                }

                return (
                    <p key={`block-${blockIndex}`}>
                        {block.lines.map((line, lineIndex) => (
                            <Fragment key={`line-${lineIndex}`}>
                                {lineIndex > 0 ? <br /> : null}
                                {renderInlineMarkdown(line)}
                            </Fragment>
                        ))}
                    </p>
                );
            })}
        </div>
    );
}

function formatValue(
    value: string,
    selectionStart: number,
    selectionEnd: number,
    action: FormattingAction,
): FormatResult {
    if (action === 'bold') {
        return wrapSelection(value, selectionStart, selectionEnd, {
            prefix: '**',
            suffix: '**',
            placeholder: 'bold text',
        });
    }

    if (action === 'italic') {
        return wrapSelection(value, selectionStart, selectionEnd, {
            prefix: '*',
            suffix: '*',
            placeholder: 'italic text',
        });
    }

    return formatList(value, selectionStart, selectionEnd, {
        ordered: action === 'numbered-list',
    });
}

function wrapSelection(
    value: string,
    selectionStart: number,
    selectionEnd: number,
    options: { prefix: string; suffix: string; placeholder: string },
): FormatResult {
    const selected = value.slice(selectionStart, selectionEnd);
    const replacement = selected || options.placeholder;
    const formatted = `${options.prefix}${replacement}${options.suffix}`;
    const nextValue =
        value.slice(0, selectionStart) + formatted + value.slice(selectionEnd);
    const nextSelectionStart = selectionStart + options.prefix.length;

    return {
        value: nextValue,
        selectionStart: nextSelectionStart,
        selectionEnd: nextSelectionStart + replacement.length,
    };
}

function formatList(
    value: string,
    selectionStart: number,
    selectionEnd: number,
    options: { ordered: boolean },
): FormatResult {
    const lineStart = value.lastIndexOf('\n', Math.max(selectionStart - 1, 0));
    const rangeStart = lineStart === -1 ? 0 : lineStart + 1;
    const nextLineBreak = value.indexOf('\n', selectionEnd);
    const rangeEnd = nextLineBreak === -1 ? value.length : nextLineBreak;
    const selectedLines = value.slice(rangeStart, rangeEnd);
    const lines = selectedLines.length > 0 ? selectedLines.split('\n') : [''];
    const formattedLines = lines.map((line, index) => {
        const indentation = line.match(/^\s*/)?.[0] ?? '';
        const content = line.replace(/^\s*(?:[-*]|\d+[.)])\s+/, '').trimStart();
        const marker = options.ordered ? `${index + 1}. ` : '- ';

        return `${indentation}${marker}${content || 'List item'}`;
    });
    const replacement = formattedLines.join('\n');
    const nextValue =
        value.slice(0, rangeStart) + replacement + value.slice(rangeEnd);

    return {
        value: nextValue,
        selectionStart: rangeStart,
        selectionEnd: rangeStart + replacement.length,
    };
}

function parseMarkdownBlocks(value: string): MarkdownBlock[] {
    const lines = value.replace(/\r\n/g, '\n').split('\n');
    const blocks: MarkdownBlock[] = [];
    let paragraphLines: string[] = [];
    let index = 0;

    const flushParagraph = () => {
        if (paragraphLines.length === 0) {
            return;
        }

        blocks.push({
            type: 'paragraph',
            lines: paragraphLines,
        });
        paragraphLines = [];
    };

    while (index < lines.length) {
        const line = lines[index];

        if (line.trim() === '') {
            flushParagraph();
            index += 1;

            continue;
        }

        const unorderedMatch = line.match(/^\s*[-*]\s+(.+)$/);

        if (unorderedMatch) {
            const items: string[] = [];

            flushParagraph();

            while (index < lines.length) {
                const candidate = lines[index].match(/^\s*[-*]\s+(.+)$/);

                if (!candidate) {
                    break;
                }

                items.push(candidate[1]);
                index += 1;
            }

            blocks.push({ type: 'unordered-list', items });

            continue;
        }

        const orderedMatch = line.match(/^\s*\d+[.)]\s+(.+)$/);

        if (orderedMatch) {
            const items: string[] = [];

            flushParagraph();

            while (index < lines.length) {
                const candidate = lines[index].match(/^\s*\d+[.)]\s+(.+)$/);

                if (!candidate) {
                    break;
                }

                items.push(candidate[1]);
                index += 1;
            }

            blocks.push({ type: 'ordered-list', items });

            continue;
        }

        paragraphLines.push(line.trim());
        index += 1;
    }

    flushParagraph();

    return blocks;
}

function renderInlineMarkdown(value: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    const pattern = /(\*\*[^*\n]+?\*\*|__[^_\n]+?__|\*[^*\n]+?\*|_[^_\n]+?_)/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(value)) !== null) {
        if (match.index > lastIndex) {
            nodes.push(value.slice(lastIndex, match.index));
        }

        const token = match[0];
        const key = `${match.index}-${token}`;

        if (token.startsWith('**') || token.startsWith('__')) {
            nodes.push(<strong key={key}>{token.slice(2, -2)}</strong>);
        } else {
            nodes.push(<em key={key}>{token.slice(1, -1)}</em>);
        }

        lastIndex = match.index + token.length;
    }

    if (lastIndex < value.length) {
        nodes.push(value.slice(lastIndex));
    }

    return nodes;
}
