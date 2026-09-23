import { cn } from '@/lib/utils';

/**
 * Renders admin-authored HTML (article bodies, custom pages).
 *
 * Tailwind's preflight strips the browser's default styles for headings,
 * lists and quotes, which is right for a UI and wrong for a document: without
 * putting them back, an article renders as one flat wall of text. These are
 * the tags the back office's editor can actually produce.
 *
 * The HTML comes from staff through the admin panel, the same trust level as
 * a product description. Content from anywhere else would have to be
 * sanitised before reaching here.
 */
export function RichText({ html, className }: { html: string; className?: string }) {
    return (
        <div
            // Separate arguments rather than one concatenated string: Prettier
            // trims the whitespace at the end of each line of a `+` chain,
            // which silently welds the last class of one line to the first of
            // the next. cn() joins them itself, so there is nothing to trim.
            className={cn(
                'leading-relaxed [&_a]:underline [&_a]:underline-offset-4',
                '[&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_blockquote]:italic',
                '[&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold',
                '[&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold',
                '[&_img]:my-6 [&_img]:rounded-xl',
                '[&_li]:mt-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-5',
                '[&_p]:my-4 [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-5',
                className,
            )}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

/**
 * Article dates are carried as ISO 8601 so the machine-readable ones (JSON-LD,
 * <time datetime>) stay exact; this is the human side of the same value.
 */
export function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}
