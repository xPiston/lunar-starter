import type { CollectionSummary } from '@/types/storefront';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    // Total quantity in the current cart, for the storefront header badge.
    cartItemCount: number;
    // One-off messages from the redirect that led here.
    flash?: { success: string | null; status: string | null };
    // Navbar collection links - only shared on storefront routes, by
    // App\Http\Middleware\HandleInertiaRequests.
    navCollections?: CollectionSummary[];
    // Editorial content links, shared the same way: custom pages for the
    // footer, and whether any article exists to justify a News tab.
    navContent?: { pages: { title: string; slug: string }[]; has_news: boolean };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
