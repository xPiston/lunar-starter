import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Single light/dark switch for the storefront header, on top of the same
 * `useAppearance` preference the dashboard's own three-way dropdown writes
 * (localStorage + the `dark` class on <html>), so a choice made on either
 * side sticks everywhere.
 *
 * The resolved theme is read back from the DOM rather than derived from
 * `appearance` alone: with 'system' selected, only the class actually
 * applied by `applyTheme()` tells us which way it resolved.
 */
export function ThemeToggle() {
    const { updateAppearance } = useAppearance();
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const root = document.documentElement;
        const sync = () => setIsDark(root.classList.contains('dark'));

        sync();

        const observer = new MutationObserver(sync);
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return (
        <Button
            variant="ghost"
            size="icon"
            className="rounded-full"
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
            aria-label={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
        >
            {isDark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </Button>
    );
}
