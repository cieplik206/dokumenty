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
import { Chat } from '@ai-sdk/vue';
import { DefaultChatTransport } from 'ai';
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

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

const binderId = ref<number | null>(
    props.selectedBinderId ?? props.binders[0]?.id ?? null,
);
const isPaper = ref(binderId.value !== null);
const lastPaperBinderId = ref<number | null>(binderId.value);

const setPaper = (): void => {
    isPaper.value = true;
    if (lastPaperBinderId.value !== null) {
        binderId.value = lastPaperBinderId.value;
        return;
    }

    binderId.value = props.selectedBinderId ?? props.binders[0]?.id ?? null;
};

const setElectronic = (): void => {
    isPaper.value = false;
    binderId.value = null;
};

const selectedCategoryId = ref<number | null>(
    props.selectedCategoryId ?? props.categories[0]?.id ?? null,
);
const categoryTouched = ref(false);

const formValues = reactive({
    title: '',
    reference_number: '',
    issuer: '',
    tags: '',
    document_date: '',
    received_at: '',
    notes: '',
});

const touchedFields = new Set<string>();
const markTouched = (field: keyof typeof formValues): void => {
    touchedFields.add(field);
};

const intakeUrl = '/documents/intake';
const getXsrfToken = (): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
};

const chat = new Chat({
    messages: [],
    transport: new DefaultChatTransport({
        api: intakeUrl,
        credentials: 'same-origin',
        headers: () => {
            const token = getXsrfToken();
            const headers: Record<string, string> = {
                Accept: 'application/json, text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (token) {
                headers['X-XSRF-TOKEN'] = token;
            }

            return headers;
        },
    }),
});

const aiHasRun = ref(false);
const aiErrorMessage = ref<string | null>(null);
const aiExtractedText = ref('');
const aiExtractedContent = ref<Record<string, unknown> | null>(null);
const aiMetadata = ref<Record<string, unknown> | null>(null);
const aiCategoryMatchName = ref<string | null>(null);
const aiCategorySuggestion = ref<string | null>(null);

const lastScans = ref<File[]>([]);
const streamBuffer = ref('');
const processedLength = ref(0);

const assistantMessage = computed(() => {
    return [...chat.messages].reverse().find((message) => message.role === 'assistant');
});

const assistantText = computed(() => {
    if (!assistantMessage.value) {
        return '';
    }

    return assistantMessage.value.parts
        .filter((part) => part.type === 'text')
        .map((part) => part.text ?? '')
        .join('');
});

const reasoningText = computed(() => {
    if (!assistantMessage.value) {
        return '';
    }

    return assistantMessage.value.parts
        .filter((part) => part.type === 'reasoning')
        .map((part) => part.text ?? '')
        .join('');
});

const toolParts = computed(() => {
    if (!assistantMessage.value) {
        return [];
    }

    return assistantMessage.value.parts.filter((part) => {
        return (
            typeof part.type === 'string' &&
            (part.type.startsWith('tool-') || part.type === 'dynamic-tool')
        );
    });
});

const aiStatusLabel = computed(() => {
    if (chat.status === 'submitted') {
        return 'Wysylanie skanow';
    }

    if (chat.status === 'streaming') {
        return 'Analiza w toku';
    }

    if (chat.status === 'error') {
        return 'Blad analizy';
    }

    return aiHasRun.value ? 'Zakonczono' : 'Gotowe';
});

const extractedContentPayload = computed(() => {
    return aiExtractedContent.value ? JSON.stringify(aiExtractedContent.value) : '';
});

const aiMetadataPayload = computed(() => {
    return aiMetadata.value ? JSON.stringify(aiMetadata.value) : '';
});

const scansInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const selectedScansCount = ref(0);

const resetIntakeState = (): void => {
    chat.stop();
    chat.messages = [];
    processedLength.value = 0;
    streamBuffer.value = '';
    aiHasRun.value = false;
    aiErrorMessage.value = null;
    aiExtractedText.value = '';
    aiExtractedContent.value = null;
    aiMetadata.value = null;
    aiCategoryMatchName.value = null;
    aiCategorySuggestion.value = null;
};

const applyFieldUpdate = (key: string, value: unknown): void => {
    if (key === 'title' && !touchedFields.has('title')) {
        formValues.title = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'notes' && !touchedFields.has('notes')) {
        formValues.notes = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'reference_number' && !touchedFields.has('reference_number')) {
        formValues.reference_number = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'issuer' && !touchedFields.has('issuer')) {
        formValues.issuer = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'document_date' && !touchedFields.has('document_date')) {
        formValues.document_date = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'received_at' && !touchedFields.has('received_at')) {
        formValues.received_at = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'tags' && !touchedFields.has('tags')) {
        if (Array.isArray(value)) {
            formValues.tags = value.filter((tag) => typeof tag === 'string').join(', ');
            return;
        }

        formValues.tags = typeof value === 'string' ? value : '';
        return;
    }

    if (key === 'category_id' && !categoryTouched.value) {
        const parsed =
            typeof value === 'number'
                ? value
                : typeof value === 'string'
                  ? Number.parseInt(value, 10)
                  : NaN;

        selectedCategoryId.value = Number.isFinite(parsed) ? parsed : null;
        return;
    }

    if (key === 'category_name') {
        aiCategoryMatchName.value = typeof value === 'string' ? value : null;
        return;
    }

    if (key === 'category_name_new') {
        aiCategorySuggestion.value = typeof value === 'string' ? value : null;
    }
};

const processStreamLine = (line: string): void => {
    const trimmed = line.trim();

    if (trimmed === '') {
        return;
    }

    try {
        const payload = JSON.parse(trimmed) as {
            type?: string;
            key?: string;
            value?: unknown;
        };

        if (!payload?.type) {
            return;
        }

        if (payload.type === 'field' && payload.key) {
            applyFieldUpdate(payload.key, payload.value);
            return;
        }

        if (payload.type === 'extracted_text') {
            aiExtractedText.value = typeof payload.value === 'string' ? payload.value : '';
            return;
        }

        if (payload.type === 'extracted_content') {
            if (payload.value && typeof payload.value === 'object') {
                aiExtractedContent.value = payload.value as Record<string, unknown>;
            }
            return;
        }

        if (payload.type === 'metadata') {
            if (payload.value && typeof payload.value === 'object') {
                aiMetadata.value = payload.value as Record<string, unknown>;
            }
        }
    } catch {
        // Ignore malformed lines until more chunks arrive.
    }
};

const processStreamBuffer = (): void => {
    const lines = streamBuffer.value.split('\n');
    streamBuffer.value = lines.pop() ?? '';

    lines.forEach(processStreamLine);
};

const buildFileList = (files: File[]): FileList => {
    const dataTransfer = new DataTransfer();
    files.forEach((file) => dataTransfer.items.add(file));
    return dataTransfer.files;
};

const startIntake = async (files: FileList | null): Promise<void> => {
    if (!files || files.length === 0) {
        return;
    }

    touchedFields.clear();
    categoryTouched.value = false;
    resetIntakeState();
    aiHasRun.value = true;
    lastScans.value = Array.from(files);

    try {
        await chat.sendMessage({
            files,
            text: 'Przeanalizuj dokument.',
        });
    } catch (error) {
        aiErrorMessage.value =
            error instanceof Error ? error.message : 'Nie udalo sie uruchomic analizy.';
    }
};

const retryIntake = (): void => {
    if (lastScans.value.length === 0) {
        return;
    }

    void startIntake(buildFileList(lastScans.value));
};

watch(assistantText, (nextValue) => {
    const delta = nextValue.slice(processedLength.value);

    if (delta.length === 0) {
        return;
    }

    processedLength.value = nextValue.length;
    streamBuffer.value += delta;
    processStreamBuffer();
});

watch(
    () => chat.status,
    (status) => {
        if (status !== 'ready') {
            return;
        }

        const remaining = streamBuffer.value.trim();

        if (remaining === '') {
            return;
        }

        processStreamLine(remaining);
        streamBuffer.value = '';
    },
);

watch(
    () => chat.error,
    (error) => {
        aiErrorMessage.value = error?.message ?? null;
    },
);

watch(binderId, (value) => {
    if (isPaper.value) {
        lastPaperBinderId.value = value;
    }
});

onBeforeUnmount(() => {
    chat.stop();
});

const updateSelectedScans = (files: FileList | null): void => {
    selectedScansCount.value = files?.length ?? 0;
};

const handleScansChange = (event: Event): void => {
    const target = event.target as HTMLInputElement | null;
    updateSelectedScans(target?.files ?? null);
    void startIntake(target?.files ?? null);
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
        void startIntake(scansInput.value.files);
        return;
    }

    updateSelectedScans(files);
    void startIntake(files);
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

            <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <Form
                    :action="DocumentController.store().url"
                    method="post"
                    enctype="multipart/form-data"
                    class="flex flex-col gap-6"
                    v-slot="{ errors, processing }"
                >
                    <input
                        type="hidden"
                        name="extracted_content"
                        :value="extractedContentPayload"
                    />
                    <input type="hidden" name="ai_metadata" :value="aiMetadataPayload" />
                    <input
                        type="hidden"
                        name="is_paper"
                        :value="isPaper ? '1' : '0'"
                    />

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
                            v-model="formValues.title"
                            name="title"
                            required
                            placeholder="Faktura za prad"
                            @input="markTouched('title')"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Label for="binder_id">Segregator</Label>
                            <div
                                class="inline-flex items-center rounded-lg border border-input bg-background p-0.5 text-xs font-medium text-muted-foreground"
                            >
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1 transition"
                                    :class="
                                        isPaper
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'hover:text-foreground'
                                    "
                                    :aria-pressed="isPaper"
                                    @click="setPaper"
                                >
                                    Papierowa
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1 transition"
                                    :class="
                                        !isPaper
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'hover:text-foreground'
                                    "
                                    :aria-pressed="!isPaper"
                                    @click="setElectronic"
                                >
                                    Elektroniczna
                                </button>
                            </div>
                        </div>
                        <select
                            v-if="isPaper"
                            id="binder_id"
                            v-model.number="binderId"
                            name="binder_id"
                            :required="isPaper"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
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
                        <p v-else class="text-xs text-muted-foreground">
                            Dokument elektroniczny nie wymaga segregatora.
                        </p>
                        <InputError :message="errors.binder_id" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="reference_number">Numer referencyjny</Label>
                            <Input
                                id="reference_number"
                                v-model="formValues.reference_number"
                                name="reference_number"
                                placeholder="REF-2024-001"
                                @input="markTouched('reference_number')"
                            />
                            <InputError :message="errors.reference_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="issuer">Wystawca</Label>
                            <Input
                                id="issuer"
                                v-model="formValues.issuer"
                                name="issuer"
                                placeholder="Tauron"
                                @input="markTouched('issuer')"
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
                                @update:modelValue="categoryTouched = true"
                            />
                            <div
                                v-if="aiCategoryMatchName || aiCategorySuggestion"
                                class="text-xs text-muted-foreground"
                            >
                                <p v-if="aiCategoryMatchName">
                                    Dopasowana: {{ aiCategoryMatchName }}
                                </p>
                                <p v-else>
                                    Sugestia nowej: {{ aiCategorySuggestion }}
                                </p>
                            </div>
                            <InputError :message="errors.category_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tags">Tagi</Label>
                            <Input
                                id="tags"
                                v-model="formValues.tags"
                                name="tags"
                                placeholder="dom, prad, 2024"
                                @input="markTouched('tags')"
                            />
                            <InputError :message="errors.tags" />
                        </div>
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="document_date">Data dokumentu</Label>
                            <Input
                                id="document_date"
                                v-model="formValues.document_date"
                                name="document_date"
                                type="date"
                                @input="markTouched('document_date')"
                            />
                            <InputError :message="errors.document_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="received_at">Data otrzymania</Label>
                            <Input
                                id="received_at"
                                v-model="formValues.received_at"
                                name="received_at"
                                type="date"
                                @input="markTouched('received_at')"
                            />
                            <InputError :message="errors.received_at" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notatki</Label>
                        <textarea
                            id="notes"
                            v-model="formValues.notes"
                            name="notes"
                            rows="4"
                            class="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                            placeholder="Dodatkowe informacje o dokumencie"
                            @input="markTouched('notes')"
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

                <aside class="flex h-fit flex-col gap-4 rounded-xl border bg-muted/20 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-semibold">Analiza AI</p>
                            <p class="text-xs text-muted-foreground">
                                Status: {{ aiStatusLabel }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="chat.status === 'streaming' || lastScans.length === 0"
                            @click="retryIntake"
                        >
                            Ponow
                        </Button>
                    </div>

                    <div
                        v-if="aiErrorMessage"
                        class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive"
                    >
                        {{ aiErrorMessage }}
                    </div>

                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Wyciagniety tekst
                            </p>
                            <div
                                class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border bg-background/60 p-3 text-xs text-muted-foreground"
                            >
                                {{
                                    aiExtractedText ||
                                    (chat.status === 'streaming'
                                        ? 'Trwa ekstrakcja...'
                                        : 'Brak danych')
                                }}
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Strumien (NDJSON)
                            </p>
                            <div
                                class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg border bg-background/60 p-3 text-xs text-muted-foreground"
                            >
                                {{ assistantText || '—' }}
                            </div>
                        </div>

                        <div v-if="reasoningText" class="flex flex-col gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Reasoning
                            </p>
                            <div
                                class="max-h-32 overflow-auto whitespace-pre-wrap rounded-lg border bg-background/60 p-3 text-xs text-muted-foreground"
                            >
                                {{ reasoningText }}
                            </div>
                        </div>

                        <div v-if="toolParts.length" class="flex flex-col gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Tool calls
                            </p>
                            <div class="flex flex-col gap-2">
                                <div
                                    v-for="(toolPart, index) in toolParts"
                                    :key="toolPart.toolCallId ?? index"
                                    class="flex flex-col gap-1 rounded-lg border bg-background/60 p-3 text-xs"
                                >
                                    <p class="font-medium text-foreground">
                                        {{ toolPart.toolName ?? toolPart.type }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        Stan: {{ toolPart.state ?? '—' }}
                                    </p>
                                    <div class="whitespace-pre-wrap text-muted-foreground">
                                        {{
                                            JSON.stringify(
                                                toolPart.input ?? toolPart.output ?? {},
                                                null,
                                                2,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="aiMetadata" class="flex flex-col gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Metadane
                            </p>
                            <div
                                class="whitespace-pre-wrap rounded-lg border bg-background/60 p-3 text-xs text-muted-foreground"
                            >
                                {{ JSON.stringify(aiMetadata, null, 2) }}
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
