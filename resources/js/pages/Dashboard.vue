<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

interface Props {
    stats: {
        documentsCount: number;
        totalSize: number;
        lastDocumentAt: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString: string | null): string {
    if (!dateString) return 'Brak dokumentów';
    const date = new Date(dateString);
    return date.toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col items-center justify-center">
                        <span class="text-5xl font-bold text-card-foreground">
                            {{ props.stats.documentsCount }}
                        </span>
                        <span class="mt-2 text-sm text-muted-foreground">
                            Dokumentów
                        </span>
                    </div>
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col items-center justify-center">
                        <span class="text-5xl font-bold text-card-foreground">
                            {{ formatBytes(props.stats.totalSize) }}
                        </span>
                        <span class="mt-2 text-sm text-muted-foreground">
                            Zajęte miejsce
                        </span>
                    </div>
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <div class="flex h-full flex-col items-center justify-center">
                        <span class="text-3xl font-bold text-card-foreground">
                            {{ formatDate(props.stats.lastDocumentAt) }}
                        </span>
                        <span class="mt-2 text-sm text-muted-foreground">
                            Ostatni dokument
                        </span>
                    </div>
                </div>
            </div>
            <div
                class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
            >
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>
