<script setup lang="ts">
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import documentsRoutes from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface ScanItem {
    id: number;
    file_name: string;
    size: number;
    mime_type: string;
    created_at: string | null;
    download_url: string;
    delete_url: string;
}

interface DocumentDetails {
    id: number;
    title: string;
    reference_number: string | null;
    issuer: string | null;
    category: string | null;
    category_id: number | null;
    document_date: string | null;
    received_at: string | null;
    notes: string | null;
    tags: string | null;
    binder: {
        id: number;
        name: string;
        location: string | null;
    };
    scans: ScanItem[];
}

defineProps<{
    document: DocumentDetails;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dokumenty',
        href: documentsRoutes.index().url,
    },
    {
        title: 'Podglad dokumentu',
        href: documentsRoutes.index().url,
    },
];
</script>

<template>
    <Head :title="document.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="document.title"
                    description="Szczegoly dokumentu i skany."
                />
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="secondary">
                        <Link :href="documentsRoutes.edit(document.id).url">
                            Edytuj
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="documentsRoutes.index().url">Lista</Link>
                    </Button>
                    <Button as-child variant="destructive">
                        <Link
                            method="delete"
                            :href="DocumentController.destroy(document.id).url"
                            as="button"
                        >
                            Usun
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Metadane</CardTitle>
                    <CardDescription>
                        Segregator:
                        <Link
                            class="underline underline-offset-4"
                            :href="bindersRoutes.show(document.binder.id).url"
                        >
                            {{ document.binder.name }}
                        </Link>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div class="grid gap-2 md:grid-cols-2">
                        <p>Numer: {{ document.reference_number || 'Brak' }}</p>
                        <p>Wystawca: {{ document.issuer || 'Brak' }}</p>
                        <p>Kategoria: {{ document.category || 'Brak' }}</p>
                        <p>Tagi: {{ document.tags || 'Brak' }}</p>
                        <p>Data dokumentu: {{ document.document_date || 'Brak' }}</p>
                        <p>Otrzymano: {{ document.received_at || 'Brak' }}</p>
                        <p class="md:col-span-2">
                            Lokalizacja segregatora:
                            {{ document.binder.location || 'Brak' }}
                        </p>
                    </div>
                    <p v-if="document.notes" class="text-muted-foreground">
                        {{ document.notes }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Skany</CardTitle>
                    <CardDescription>
                        Pliki powiazane z dokumentem.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="document.scans.length === 0"
                        class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Brak skanow. Dodaj je w edycji dokumentu.
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <div
                            v-for="scan in document.scans"
                            :key="scan.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm"
                        >
                            <div>
                                <p class="font-medium">{{ scan.file_name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ scan.created_at || 'Brak daty' }} ·
                                    {{ Math.round(scan.size / 1024) }} KB
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button as-child size="sm" variant="secondary">
                                    <a :href="scan.download_url">Pobierz</a>
                                </Button>
                                <Button as-child size="sm" variant="destructive">
                                    <Link
                                        method="delete"
                                        :href="scan.delete_url"
                                        as="button"
                                    >
                                        Usun
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
