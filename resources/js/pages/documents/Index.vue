<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import documentsRoutes from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown, FileText, Filter, FolderOpen, Paperclip, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface BinderOption {
    id: number;
    name: string;
}

interface DocumentItem {
    id: number;
    title: string;
    reference_number: string | null;
    issuer: string | null;
    category: string | null;
    category_id: number | null;
    document_date: string | null;
    received_at: string | null;
    media_count: number;
    thumbnail_url: string | null;
    binder: {
        id: number;
        name: string;
    } | null;
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

const props = defineProps<{
    documents: Pagination<DocumentItem>;
    binders: BinderOption[];
    categories: CategoryOption[];
    filters: {
        q?: string | null;
        binder?: number | null;
        category?: number | null;
        from?: string | null;
        to?: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dokumenty',
        href: documentsRoutes.index().url,
    },
];

const filtersOpen = ref(false);

const hasAdvancedFilters = computed(() => {
    return props.filters.binder || props.filters.category || props.filters.from || props.filters.to;
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (props.filters.q) count++;
    if (props.filters.binder) count++;
    if (props.filters.category) count++;
    if (props.filters.from) count++;
    if (props.filters.to) count++;
    return count;
});
</script>

<template>
    <Head title="Dokumenty" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    title="Dokumenty"
                    description="Przegladaj i wyszukuj dokumenty."
                />
                <div class="flex gap-2">
                    <Button as-child size="sm">
                        <Link :href="documentsRoutes.create().url">
                            <Plus class="size-4" />
                            Dodaj
                        </Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="bindersRoutes.index().url">
                            <FolderOpen class="size-4" />
                            Segregatory
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border bg-card">
                <Form
                    :action="documentsRoutes.index().url"
                    method="get"
                    class="flex flex-col"
                >
                    <!-- Main search row -->
                    <div class="flex flex-wrap items-center gap-2 p-3">
                        <div class="relative flex-1 min-w-[200px]">
                            <Input
                                id="q"
                                name="q"
                                placeholder="Szukaj: tytul, numer, wystawca..."
                                :default-value="filters.q || ''"
                                class="pl-3"
                            />
                        </div>
                        <Button type="submit" size="sm">
                            Szukaj
                        </Button>
                        <Button
                            v-if="activeFiltersCount > 0"
                            as-child
                            variant="ghost"
                            size="sm"
                        >
                            <Link :href="documentsRoutes.index().url">
                                Wyczysc
                            </Link>
                        </Button>
                    </div>

                    <!-- Advanced filters toggle -->
                    <Collapsible v-model:open="filtersOpen">
                        <CollapsibleTrigger as-child>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 border-t px-3 py-2 text-sm text-muted-foreground hover:bg-muted/50 transition-colors"
                            >
                                <Filter class="size-3.5" />
                                <span>Wiecej filtrow</span>
                                <Badge v-if="hasAdvancedFilters" variant="secondary" class="ml-1">
                                    {{ activeFiltersCount - (filters.q ? 1 : 0) }}
                                </Badge>
                                <ChevronDown
                                    class="ml-auto size-4 transition-transform"
                                    :class="{ 'rotate-180': filtersOpen }"
                                />
                            </button>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <div class="grid gap-3 border-t p-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="space-y-1.5">
                                    <Label for="binder" class="text-xs">Segregator</Label>
                                    <select
                                        id="binder"
                                        name="binder"
                                        class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                                        :value="filters.binder || ''"
                                    >
                                        <option value="">Wszystkie</option>
                                        <option
                                            v-for="binderOption in binders"
                                            :key="binderOption.id"
                                            :value="binderOption.id"
                                        >
                                            {{ binderOption.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="category" class="text-xs">Kategoria</Label>
                                    <select
                                        id="category"
                                        name="category"
                                        class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
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
                                <div class="space-y-1.5">
                                    <Label for="from" class="text-xs">Data od</Label>
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        :default-value="filters.from || ''"
                                        class="h-8"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="to" class="text-xs">Data do</Label>
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        :default-value="filters.to || ''"
                                        class="h-8"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </Form>
            </div>

            <!-- Documents list -->
            <div class="rounded-lg border bg-card">
                <!-- Empty state -->
                <div
                    v-if="documents.data.length === 0"
                    class="flex flex-col items-center justify-center gap-3 p-12 text-center"
                >
                    <div class="rounded-full bg-muted p-3">
                        <FileText class="size-6 text-muted-foreground" />
                    </div>
                    <div>
                        <p class="font-medium">Brak dokumentow</p>
                        <p class="text-sm text-muted-foreground">
                            Dodaj pierwszy dokument lub zmien filtry.
                        </p>
                    </div>
                    <Button as-child size="sm" class="mt-2">
                        <Link :href="documentsRoutes.create().url">
                            <Plus class="size-4" />
                            Dodaj dokument
                        </Link>
                    </Button>
                </div>

                <!-- Documents table/list -->
                <div v-else class="divide-y">
                    <Link
                        v-for="document in documents.data"
                        :key="document.id"
                        :href="documentsRoutes.show(document.id).url"
                        class="flex items-center gap-4 p-3 hover:bg-muted/50 transition-colors"
                    >
                        <!-- Icon -->
                        <div class="hidden sm:flex shrink-0 size-10 items-center justify-center overflow-hidden rounded-lg bg-muted">
                            <img
                                v-if="document.thumbnail_url"
                                :src="document.thumbnail_url"
                                alt=""
                                class="h-full w-full object-cover"
                            />
                            <FileText v-else class="size-5 text-muted-foreground" />
                        </div>

                        <!-- Main content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium truncate">{{ document.title }}</span>
                                <Badge v-if="document.category" variant="secondary" class="hidden sm:inline-flex shrink-0">
                                    {{ document.category }}
                                </Badge>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5 text-sm text-muted-foreground">
                                <span v-if="document.reference_number" class="truncate">
                                    {{ document.reference_number }}
                                </span>
                                <span v-if="document.issuer" class="truncate">
                                    {{ document.issuer }}
                                </span>
                                <span v-if="document.document_date">
                                    {{ document.document_date }}
                                </span>
                            </div>
                        </div>

                        <!-- Right side info -->
                        <div class="flex items-center gap-3 shrink-0 text-sm text-muted-foreground">
                            <span
                                class="hidden md:flex items-center gap-1.5"
                                :title="document.binder?.name ?? 'Elektroniczny'"
                            >
                                <FolderOpen class="size-3.5" />
                                <span class="max-w-[120px] truncate">
                                    {{ document.binder?.name ?? 'Elektroniczny' }}
                                </span>
                            </span>
                            <span class="flex items-center gap-1" :title="`${document.media_count} skanow`">
                                <Paperclip class="size-3.5" />
                                {{ document.media_count }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="documents.links && documents.links.length > 3"
                class="flex flex-wrap justify-center gap-1"
            >
                <template v-for="link in documents.links" :key="link.label">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        disabled
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
