<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import documentsRoutes from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

interface BinderDetails {
    id: number;
    name: string;
    location: string | null;
    description: string | null;
    documents_count: number;
}

interface DocumentItem {
    id: number;
    title: string;
    reference_number: string | null;
    issuer: string | null;
    category: string | null;
    document_date: string | null;
    received_at: string | null;
    media_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Pagination<T> {
    data: T[];
    links: PaginationLink[];
}

interface CategoryOption {
    id: number;
    name: string;
}

defineProps<{
    binder: BinderDetails;
    documents: Pagination<DocumentItem>;
    categories: CategoryOption[];
    filters: {
        q?: string | null;
        category?: number | null;
        from?: string | null;
        to?: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Segregatory',
        href: bindersRoutes.index().url,
    },
    {
        title: 'Segregator',
        href: bindersRoutes.index().url,
    },
];
</script>

<template>
    <Head :title="binder.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="binder.name"
                    description="Dokumenty przypisane do tego segregatora."
                />
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="secondary">
                        <Link
                            :href="documentsRoutes.create({
                                query: { binder: binder.id },
                            }).url"
                        >
                            Dodaj dokument
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="bindersRoutes.edit(binder.id).url">Edytuj</Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Szczegoly</CardTitle>
                    <CardDescription>
                        Lokalizacja: {{ binder.location || 'Brak' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <p v-if="binder.description">{{ binder.description }}</p>
                    <p class="text-muted-foreground">
                        Liczba dokumentow: {{ binder.documents_count }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Filtry</CardTitle>
                    <CardDescription>Znajdz dokumenty po danych.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        :action="bindersRoutes.show(binder.id).url"
                        method="get"
                        class="grid gap-4 md:grid-cols-2"
                    >
                        <div class="grid gap-2">
                            <Label for="q">Szukaj</Label>
                            <Input
                                id="q"
                                name="q"
                                placeholder="Tytul, numer, wystawca"
                                :default-value="filters.q || ''"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="category">Kategoria</Label>
                            <select
                                id="category"
                                name="category"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                                :value="filters.category || ''"
                            >
                                <option value="">Wszystkie</option>
                                <option
                                    v-for="categoryOption in categories"
                                    :key="categoryOption.id"
                                    :value="categoryOption.id"
                                >
                                    {{ categoryOption.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="from">Data od</Label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                :default-value="filters.from || ''"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="to">Data do</Label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                :default-value="filters.to || ''"
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2 md:col-span-2">
                            <Button type="submit">Filtruj</Button>
                            <Button as-child variant="ghost">
                                <Link :href="bindersRoutes.show(binder.id).url">
                                    Wyczysc
                                </Link>
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-4">
                <div
                    v-if="documents.data.length === 0"
                    class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Brak dokumentow w tym segregatorze.
                </div>

                <div v-else class="flex flex-col gap-4">
                    <Card v-for="document in documents.data" :key="document.id">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between gap-4">
                                <span>{{ document.title }}</span>
                                <span class="text-sm font-normal text-muted-foreground">
                                    {{ document.media_count }} skanow
                                </span>
                            </CardTitle>
                            <CardDescription>
                                <span v-if="document.category">{{ document.category }}</span>
                                <span v-else>Brak kategorii</span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div class="grid gap-2 md:grid-cols-2">
                                <p>
                                    Numer: {{ document.reference_number || 'Brak' }}
                                </p>
                                <p>Wystawca: {{ document.issuer || 'Brak' }}</p>
                                <p>
                                    Data dokumentu:
                                    {{ document.document_date || 'Brak' }}
                                </p>
                                <p>
                                    Otrzymano:
                                    {{ document.received_at || 'Brak' }}
                                </p>
                            </div>
                            <Button as-child size="sm" variant="secondary">
                                <Link :href="documentsRoutes.show(document.id).url">
                                    Zobacz
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div
                v-if="documents.links && documents.links.length > 1"
                class="flex flex-wrap gap-2"
            >
                <template v-for="link in documents.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'ghost'"
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="ghost"
                        disabled
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
