import type { Generation } from '@/types';

export const TOKEN_LABELS = {
    input_text_tokens: 'Prompt text',
    input_image_tokens: 'Reference images',
    output_tokens: 'Image output',
    reasoning_tokens: 'Reasoning',
} as const;

export function isInFlight(g: Generation): boolean {
    return (
        g.status === 'pending' ||
        g.status === 'queued' ||
        g.status === 'processing'
    );
}

export function imageAvailable(
    g: Generation,
    now: number = Date.now(),
): boolean {
    return (
        g.status === 'succeeded' &&
        !!g.image_url &&
        (!g.image_url_expires_at ||
            new Date(g.image_url_expires_at).getTime() > now)
    );
}

/** Tailwind aspect class for a "1536x1024"-style size. */
export function aspectClass(size: string): string {
    const [w, h] = size.split('x').map(Number);

    return w === h ? 'aspect-square' : w > h ? 'aspect-[3/2]' : 'aspect-[2/3]';
}

/** What's happening right now, in a few words. */
export function progressLabel(g: Generation): string {
    if (g.status === 'pending') {
        return g.waiting_for_capacity
            ? 'Waiting for Pixelforge capacity'
            : 'Sending to Pixelforge';
    }

    return (
        { queued: 'In Pixelforge’s queue', processing: 'Painting your image' }[
            g.status as 'queued' | 'processing'
        ] ?? ''
    );
}

/** "0:07", "1:32". */
export function formatElapsed(seconds: number): string {
    const s = Math.max(0, Math.floor(seconds));

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** "18s", "1m 32s". */
export function formatDuration(seconds: number): string {
    const s = Math.max(0, Math.round(seconds));

    return s < 60
        ? `${s}s`
        : `${Math.floor(s / 60)}m ${String(s % 60).padStart(2, '0')}s`;
}

export function secondsBetween(fromIso: string, to: string | number): number {
    return (
        ((typeof to === 'number' ? to : new Date(to).getTime()) -
            new Date(fromIso).getTime()) /
        1000
    );
}

/** "23h left", "45m left": how long until the image link expires. */
export function expiresIn(
    iso: string | null,
    now: number = Date.now(),
): string | null {
    if (!iso) {
        return null;
    }

    const minutes = Math.floor((new Date(iso).getTime() - now) / 60000);

    if (minutes <= 0) {
        return null;
    }

    return minutes >= 60
        ? `${Math.floor(minutes / 60)}h left`
        : `${minutes}m left`;
}
