import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Future Shift Advisory"
        >
            <rect x="7" y="46" width="9" height="10" rx="1.5" fill="#2A3B5C" />
            <rect x="20" y="36" width="9" height="20" rx="1.5" fill="#1B5070" />
            <rect x="33" y="24" width="9" height="32" rx="1.5" fill="#0D7A7A" />
            <rect x="46" y="14" width="9" height="42" rx="1.5" fill="#0D6A5A" />
            <line
                x1="11"
                y1="51"
                x2="50"
                y2="18"
                stroke="#B8860B"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <circle cx="50" cy="18" r="3.5" fill="#B8860B" />
        </svg>
    );
}
