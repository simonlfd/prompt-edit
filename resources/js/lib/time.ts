const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

/** "2 minutes ago", "in 5 seconds". */
export function relativeTime(iso: string, now: number = Date.now()): string {
    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 45) {
        return seconds <= 0 ? 'just now' : rtf.format(seconds, 'second');
    }

    if (abs < 3600) {
        return rtf.format(Math.round(seconds / 60), 'minute');
    }

    if (abs < 86400) {
        return rtf.format(Math.round(seconds / 3600), 'hour');
    }

    return rtf.format(Math.round(seconds / 86400), 'day');
}

export function absoluteTime(iso: string): string {
    return new Date(iso).toLocaleString();
}
