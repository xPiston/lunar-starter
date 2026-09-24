import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationProps {
    page: number;
    lastPage: number;
}

/**
 * Real links, not buttons: a page of a catalogue is a place, so it has to be
 * openable in a new tab and followable by a crawler. Each one keeps whatever
 * filters are already in the URL.
 */
export function Pagination({ page, lastPage }: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="mt-10 flex items-center justify-center gap-2">
            <PageLink page={page - 1} disabled={page === 1} label="Previous page">
                <ChevronLeft className="size-4" />
                <span className="hidden sm:inline">Previous</span>
            </PageLink>

            {pagesAround(page, lastPage).map((entry, index) =>
                entry === null ? (
                    <span key={`gap-${index}`} className="text-muted-foreground px-1">
                        …
                    </span>
                ) : (
                    <PageLink key={entry} page={entry} current={entry === page} label={`Page ${entry}`}>
                        {entry}
                    </PageLink>
                ),
            )}

            <PageLink page={page + 1} disabled={page === lastPage} label="Next page">
                <span className="hidden sm:inline">Next</span>
                <ChevronRight className="size-4" />
            </PageLink>
        </nav>
    );
}

function PageLink({
    page,
    children,
    label,
    disabled = false,
    current = false,
}: {
    page: number;
    children: React.ReactNode;
    label: string;
    disabled?: boolean;
    current?: boolean;
}) {
    if (disabled) {
        return (
            <Button variant="ghost" size="sm" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant={current ? 'default' : 'ghost'} size="sm" asChild>
            <Link href={urlForPage(page)} aria-label={label} aria-current={current ? 'page' : undefined} preserveScroll={false}>
                {children}
            </Link>
        </Button>
    );
}

function urlForPage(page: number): string {
    const params = new URLSearchParams(window.location.search);

    // Page 1 carries no parameter: it keeps the canonical URL of a listing
    // free of `?page=1`, which would otherwise be a second address for the
    // same page.
    if (page <= 1) {
        params.delete('page');
    } else {
        params.set('page', String(page));
    }

    const query = params.toString();

    return window.location.pathname + (query ? `?${query}` : '');
}

/**
 * First, last, and a window around the current page - so a catalogue with
 * forty pages doesn't render forty links.
 */
function pagesAround(page: number, lastPage: number): (number | null)[] {
    const pages = new Set<number>([1, lastPage, page, page - 1, page + 1]);
    const sorted = [...pages].filter((candidate) => candidate >= 1 && candidate <= lastPage).sort((a, b) => a - b);

    return sorted.flatMap((entry, index) => (index > 0 && entry - sorted[index - 1] > 1 ? [null, entry] : [entry]));
}
