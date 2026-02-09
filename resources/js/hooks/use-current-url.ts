import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export type IsCurrentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

export function useCurrentUrl(): UseCurrentUrlReturn {
    const page = usePage();
    const baseOrigin =
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost';

    const normalizePath = (path: string) => {
        const normalized = path.replace(/\/+$/, '');
        return normalized === '' ? '/' : normalized;
    };

    const toPathname = (url: string) => {
        try {
            return normalizePath(new URL(url, baseOrigin).pathname);
        } catch {
            const fallback = url.split('?')[0]?.split('#')[0] || '/';
            return normalizePath(fallback);
        }
    };

    const currentUrlPath = toPathname(page.url);

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        const urlToCompare = normalizePath(currentUrl ?? currentUrlPath);
        const targetPath = toPathname(toUrl(urlToCheck));

        if (targetPath === '/') {
            return urlToCompare === '/';
        }

        return (
            urlToCompare === targetPath ||
            urlToCompare.startsWith(`${targetPath}/`)
        );
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        whenCurrentUrl,
    };
}
