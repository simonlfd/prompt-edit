import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { toast } from 'vue-sonner';
import { download, favourite } from '@/routes/generations';
import type { Generation } from '@/types';

// Optimistic favourite state shared by every component on the page, so the star flips instantly
// everywhere (stage, rail, gallery) and settles when the server answers.
const pending = reactive(new Map<string, boolean>());

export function useGenerationActions() {
    function isFavourite(g: Generation): boolean {
        return pending.get(g.id) ?? g.is_favourite;
    }

    function toggleFavourite(g: Generation): void {
        const next = !isFavourite(g);
        pending.set(g.id, next);

        router.put(
            favourite.url(g.id),
            { favourite: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () =>
                    toast.error(
                        'Couldn’t update favourites. Please try again.',
                    ),
                onFinish: () => pending.delete(g.id),
            },
        );
    }

    async function copyPrompt(g: Generation): Promise<void> {
        try {
            await navigator.clipboard.writeText(g.prompt);
            toast.success('Prompt copied');
        } catch {
            toast.error(
                'Couldn’t copy. Your browser blocked clipboard access.',
            );
        }
    }

    function downloadUrl(g: Generation): string {
        return download.url(g.id);
    }

    return { isFavourite, toggleFavourite, copyPrompt, downloadUrl };
}
