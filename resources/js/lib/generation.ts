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

/**
 * Size an image frame so it fits BOTH the available width and a maximum height, keeping the
 * image's aspect ratio. Width alone would make a portrait (2:3) frame 1.5× taller than it is wide
 * and push everything below it off screen.
 *
 * @param maxHeight any CSS length, e.g. "min(58dvh, 34rem)"
 */
export function frameStyle(
    size: string,
    maxHeight: string,
): Record<string, string> {
    const [w, h] = size.split('x').map(Number);
    const ratio = w && h ? w / h : 1;

    return {
        aspectRatio: `${w || 1} / ${h || 1}`,
        width: `min(100%, calc(${maxHeight} * ${ratio}))`,
    };
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
