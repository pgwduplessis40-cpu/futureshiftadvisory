import { FileText } from 'lucide-react';
import type { NavItem } from '@/types';

export const legalAdministrationNavItems: NavItem[] = [
    {
        title: 'Terms & Conditions',
        href: '/admin/terms',
        icon: FileText,
    },
    {
        title: 'Terms of Use',
        href: '/admin/terms-of-use',
        icon: FileText,
    },
    {
        title: 'Privacy Policy',
        href: '/admin/privacy-policy',
        icon: FileText,
    },
    {
        title: 'Partner Agreement',
        href: '/admin/partner-agreement',
        icon: FileText,
    },
];
