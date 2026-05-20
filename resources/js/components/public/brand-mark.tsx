type BrandMarkProps = {
    variant?: 'light' | 'dark';
    showWordmark?: boolean;
    className?: string;
    width?: number;
};

export function BrandMark({
    variant = 'light',
    showWordmark = true,
    className,
    width,
}: BrandMarkProps) {
    const isDark = variant === 'dark';
    const wordFill = isDark ? '#F5F0E8' : '#1C2B45';
    const subFill = isDark ? '#6ACABA' : '#5A7A70';
    const goldStroke = isDark ? '#D4A020' : '#B8860B';
    const goldRule = isDark ? '#D4A020' : '#B8860B';
    const goldDot = isDark ? '#D4A020' : '#B8860B';

    const bar1 = isDark ? '#4A6A8A' : '#2A3B5C';
    const bar2 = isDark ? '#5A8AAA' : '#1B5070';
    const bar3 = isDark ? '#3ABAAA' : '#0D7A7A';
    const bar4 = isDark ? '#2ACAAA' : '#0D6A5A';

    if (!showWordmark) {
        return (
            <svg
                viewBox="0 0 50 56"
                width={width ?? 40}
                height={(width ?? 40) * (56 / 50)}
                className={className}
                role="img"
                aria-label="Future Shift Advisory"
            >
                <rect x="0" y="42" width="9" height="10" rx="1.5" fill={bar1} />
                <rect x="13" y="32" width="9" height="20" rx="1.5" fill={bar2} />
                <rect x="26" y="20" width="9" height="32" rx="1.5" fill={bar3} />
                <rect x="39" y="10" width="9" height="42" rx="1.5" fill={bar4} />
                <line
                    x1="4"
                    y1="47"
                    x2="43"
                    y2="14"
                    stroke={goldStroke}
                    strokeWidth="1.8"
                    strokeLinecap="round"
                />
                <circle cx="43" cy="14" r="3" fill={goldDot} />
            </svg>
        );
    }

    return (
        <svg
            viewBox="0 0 230 64"
            width={width ?? 184}
            height={(width ?? 184) * (64 / 230)}
            className={className}
            role="img"
            aria-label="Future Shift Advisory"
        >
            <rect x="0" y="42" width="9" height="10" rx="1.5" fill={bar1} />
            <rect x="13" y="32" width="9" height="20" rx="1.5" fill={bar2} />
            <rect x="26" y="20" width="9" height="32" rx="1.5" fill={bar3} />
            <rect x="39" y="10" width="9" height="42" rx="1.5" fill={bar4} />
            <line
                x1="4"
                y1="47"
                x2="43"
                y2="14"
                stroke={goldStroke}
                strokeWidth="1.8"
                strokeLinecap="round"
            />
            <circle cx="43" cy="14" r="3" fill={goldDot} />
            <text
                x="62"
                y="26"
                fontFamily="'DM Serif Display', serif"
                fontSize="18"
                fill={wordFill}
            >
                Future Shift
            </text>
            <line
                x1="62"
                y1="32"
                x2="224"
                y2="32"
                stroke={goldRule}
                strokeWidth="0.75"
            />
            <text
                x="62"
                y="46"
                fontFamily="'Outfit', sans-serif"
                fontSize="11"
                fontWeight="500"
                fill={subFill}
                letterSpacing="0.2em"
            >
                ADVISORY
            </text>
        </svg>
    );
}
