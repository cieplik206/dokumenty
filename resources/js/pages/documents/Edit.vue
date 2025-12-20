<script setup lang="ts">
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import CategoryPicker from '@/components/CategoryPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import categoriesRoutes from '@/routes/categories';
import documentsRoutes from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface BinderOption {
    id: number;
    name: string;
}

interface CategoryOption {
    id: number;
    name: string;
}

interface ScanItem {
    id: number;
    file_name: string;
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
    binder_id: number;
    scans: ScanItem[];
}

const props = defineProps<{
    document: DocumentDetails;
    binders: BinderOption[];
    categories: CategoryOption[];
}>();

const selectedCategoryId = ref<number | null>(
    props.document.category_id ?? props.categories[0]?.id ?? null,
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dokumenty',
        href: documentsRoutes.index().url,
    },
    {
        title: 'Edycja dokumentu',
        href: documentsRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Edycja dokumentu" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Edycja dokumentu"
                description="Zmien dane dokumentu lub dodaj nowe skany."
            />

            <Form
                :action="DocumentController.update(document.id).url"
                method="put"
                class="flex max-w-3xl flex-col gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="title">Tytul</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        :default-value="document.title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="binder_id">Segregator</Label>
                    <select
                        id="binder_id"
                        name="binder_id"
                        required
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                        :value="document.binder_id"
                    >
                        <option disabled value="">Wybierz segregator</option>
                        <option
                            v-for="binderOption in binders"
                            :key="binderOption.id"
                            :value="binderOption.id"
                        >
                            {{ binderOption.name }}
                        </option>
                    </select>
                    <InputError :message="errors.binder_id" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="reference_number">Numer referencyjny</Label>
                        <Input
                            id="reference_number"
                            name="reference_number"
                            :default-value="document.reference_number || ''"
                        />
                        <InputError :message="errors.reference_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="issuer">Wystawca</Label>
                        <Input
                            id="issuer"
                            name="issuer"
                            :default-value="document.issuer || ''"
                        />
                        <InputError :message="errors.issuer" />
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="category_id">Kategoria</Label>
                            <Link
                                class="text-xs text-muted-foreground underline underline-offset-4"
                                :href="categoriesRoutes.create().url"
                            >
                                Dodaj kategorie
                            </Link>
                        </div>
                        <CategoryPicker
                            v-model="selectedCategoryId"
                            :categories="categories"
                            name="category_id"
                        />
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="tags">Tagi</Label>
                        <Input id="tags" name="tags" :default-value="document.tags || ''" />
                        <InputError :message="errors.tags" />
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="document_date">Data dokumentu</Label>
                        <Input
                            id="document_date"
                            name="document_date"
                            type="date"
                            :default-value="document.document_date || ''"
                        />
                        <InputError :message="errors.document_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="received_at">Data otrzymania</Label>
                        <Input
                            id="received_at"
                            name="received_at"
                            type="date"
                            :default-value="document.received_at || ''"
                        />
                        <InputError :message="errors.received_at" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notatki</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                        :value="document.notes || ''"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="scans">Dodaj nowe skany</Label>
                    <input
                        id="scans"
                        name="scans[]"
                        type="file"
                        multiple
                        class="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium"
                    />
                    <p class="text-xs text-muted-foreground">
                        Dodaj kolejne pliki PDF lub obrazy (jpg, png).
                    </p>
                    <InputError :message="errors.scans" />
                    <InputError :message="errors['scans.0']" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="documentsRoutes.show(document.id).url">Wroc</Link>
                    </Button>
                </div>
            </Form>

            <div class="flex flex-col gap-3">
                <Heading
                    title="Skanowane pliki"
                    description="Zarzadzaj dodanymi skanami."
                />
                <div
                    v-if="document.scans.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Brak skanow. Dodaj pliki powyzej.
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
                                {{ scan.created_at || 'Brak daty' }}
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
            </div>
        </div>
    </AppLayout>
</template>
