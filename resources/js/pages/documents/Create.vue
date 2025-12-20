<script setup lang="ts">
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import CategoryPicker from '@/components/CategoryPicker.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
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

const props = defineProps<{
    binders: BinderOption[];
    categories: CategoryOption[];
    selectedBinderId?: number | null;
    selectedCategoryId?: number | null;
}>();

const selectedCategoryId = ref<number | null>(
    props.selectedCategoryId ?? props.categories[0]?.id ?? null,
);

const scansInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const selectedScansCount = ref(0);

const updateSelectedScans = (files: FileList | null): void => {
    selectedScansCount.value = files?.length ?? 0;
};

const handleScansChange = (event: Event): void => {
    const target = event.target as HTMLInputElement | null;
    updateSelectedScans(target?.files ?? null);
};

const handleScansDrop = (event: DragEvent): void => {
    isDragging.value = false;
    const files = event.dataTransfer?.files;

    if (!files || files.length === 0) {
        return;
    }

    if (scansInput.value) {
        const dataTransfer = new DataTransfer();
        Array.from(files).forEach((file) => dataTransfer.items.add(file));
        scansInput.value.files = dataTransfer.files;
        updateSelectedScans(scansInput.value.files);
        return;
    }

    updateSelectedScans(files);
};

const openScansPicker = (): void => {
    scansInput.value?.click();
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dokumenty',
        href: documentsRoutes.index().url,
    },
    {
        title: 'Nowy dokument',
        href: documentsRoutes.create().url,
    },
];
</script>

<template>
    <Head title="Nowy dokument" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Nowy dokument"
                description="Dodaj dokument i przypisz go do segregatora."
            />

            <div
                v-if="binders.length === 0 || categories.length === 0"
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                <p v-if="binders.length === 0">
                    Najpierw dodaj segregator, aby moc przypisac dokument.
                </p>
                <p v-if="categories.length === 0">
                    Dodaj przynajmniej jedna kategorie dokumentow.
                </p>
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <Link
                        v-if="binders.length === 0"
                        class="underline underline-offset-4"
                        :href="bindersRoutes.create().url"
                    >
                        Dodaj segregator
                    </Link>
                    <Link
                        v-if="categories.length === 0"
                        class="underline underline-offset-4"
                        :href="categoriesRoutes.create().url"
                    >
                        Dodaj kategorie
                    </Link>
                </div>
            </div>

            <Form
                v-else
                :action="DocumentController.store().url"
                method="post"
                enctype="multipart/form-data"
                class="flex max-w-3xl flex-col gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="scans">Skan dokumentu</Label>
                    <div
                        class="group relative flex min-h-[140px] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-6 text-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        :class="
                            isDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-input/70 bg-muted/20'
                        "
                        role="button"
                        tabindex="0"
                        @click="openScansPicker"
                        @keydown.enter.prevent="openScansPicker"
                        @keydown.space.prevent="openScansPicker"
                        @dragenter.prevent="isDragging = true"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleScansDrop"
                    >
                        <input
                            id="scans"
                            ref="scansInput"
                            name="scans[]"
                            type="file"
                            multiple
                            class="sr-only"
                            @change="handleScansChange"
                        />
                        <p class="text-sm font-medium">
                            Przeciagnij pliki tutaj lub kliknij, aby dodac
                        </p>
                        <p class="text-xs text-muted-foreground">
                            PDF, JPG, PNG · do 10MB kazdy
                        </p>
                        <p
                            v-if="selectedScansCount"
                            class="text-xs font-medium text-foreground"
                        >
                            Wybrano {{ selectedScansCount }} plikow
                        </p>
                    </div>
                    <InputError :message="errors.scans" />
                    <InputError :message="errors['scans.0']" />
                </div>

                <div class="grid gap-2">
                    <Label for="title">Tytul</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        placeholder="Faktura za prad"
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
                        :value="selectedBinderId || (binders[0]?.id ?? '')"
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
                            placeholder="REF-2024-001"
                        />
                        <InputError :message="errors.reference_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="issuer">Wystawca</Label>
                        <Input id="issuer" name="issuer" placeholder="Tauron" />
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
                        <Input
                            id="tags"
                            name="tags"
                            placeholder="dom, prad, 2024"
                        />
                        <InputError :message="errors.tags" />
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="document_date">Data dokumentu</Label>
                        <Input id="document_date" name="document_date" type="date" />
                        <InputError :message="errors.document_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="received_at">Data otrzymania</Label>
                        <Input id="received_at" name="received_at" type="date" />
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
                        placeholder="Dodatkowe informacje o dokumencie"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="documentsRoutes.index().url">Anuluj</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
