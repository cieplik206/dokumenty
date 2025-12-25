<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import documentsRoutes from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

interface BinderOption {
    id: number;
    name: string;
}

type IntakeStatus = 'uploaded' | 'queued' | 'processing' | 'done' | 'failed' | 'finalized';

type StorageType = 'paper' | 'electronic';

interface IntakePage {
    id: number;
    page: number;
    url: string;
    thumb_url: string | null;
}

interface IntakeItem {
    id: number;
    status: IntakeStatus;
    document_id: number | null;
    original_name: string | null;
    title: string | null;
    storage_type: StorageType | null;
    preview_url: string | null;
    preview_full_url: string | null;
    pages: IntakePage[];
    scans_count: number;
    scans_size: number;
    error_message: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    finalized_at: string | null;
}

const props = defineProps<{
    binders: BinderOption[];
    initialIntakes?: IntakeItem[];
}>();

const intakeUrl = '/documents/intake';

const getXsrfToken = (): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
};

const buildHeaders = (): Record<string, string> => {
    const token = getXsrfToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    return headers;
};

const scansInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadError = ref<string | null>(null);

const uploads = ref<IntakeItem[]>(
    (props.initialIntakes ?? []).map((item) => ({
        ...item,
        title: item.title ?? null,
        pages: item.pages ?? [],
        scans_count: item.scans_count ?? 0,
        scans_size: item.scans_size ?? 0,
    })),
);
const selectionState = reactive<Record<number, { paperMode: boolean; binderId: number | null }>>({});
const actionBusy = reactive<Record<number, boolean>>({});
const bulkRemoveBusy = ref(false);
const previewItem = ref<IntakeItem | null>(null);
const isPreviewOpen = ref(false);
const activePreviewIndex = ref(0);

const nowTick = ref(Date.now());
let pollTimer: number | null = null;
let ticker: number | null = null;

const pendingUploads = computed(() => {
    return uploads.value.filter((item) => item.status === 'queued' || item.status === 'processing');
});

const previewPages = computed(() => previewItem.value?.pages ?? []);

const activePreviewPage = computed(() => {
    return previewPages.value[activePreviewIndex.value] ?? null;
});

const previewLabel = computed(() => {
    if (previewPages.value.length === 0) {
        return null;
    }

    const current = activePreviewPage.value?.page ?? activePreviewIndex.value + 1;

    return `Strona ${current} z ${previewPages.value.length}`;
});

const statusLabel = (item: IntakeItem): string => {
    switch (item.status) {
        case 'uploaded':
            return 'Czeka na start';
        case 'queued':
            return 'W kolejce';
        case 'processing':
            return 'Przetwarzanie w OpenAI';
        case 'done':
            return 'Gotowe do decyzji';
        case 'failed':
            return 'Blad analizy';
        case 'finalized':
            return 'Zakonczono';
        default:
            return 'Nieznany status';
    }
};

const canRetry = (item: IntakeItem): boolean => {
    return item.status === 'failed' || (item.status === 'done' && !item.document_id);
};

const canStart = (item: IntakeItem): boolean => {
    return item.status === 'uploaded';
};

const canFinalize = (item: IntakeItem): boolean => {
    return item.status === 'done' && item.document_id !== null;
};

const formatElapsed = (item: IntakeItem): string => {
    const start = item.started_at ?? item.created_at;

    if (!start) {
        return '00:00';
    }

    const diff = Math.max(0, nowTick.value - new Date(start).getTime());
    const totalSeconds = Math.floor(diff / 1000);
    const minutes = Math.floor(totalSeconds / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
};

const startTicker = (): void => {
    if (ticker !== null) {
        return;
    }

    ticker = window.setInterval(() => {
        nowTick.value = Date.now();
    }, 1000);
};

const stopTicker = (): void => {
    if (ticker === null) {
        return;
    }

    window.clearInterval(ticker);
    ticker = null;
};

const stopPolling = (): void => {
    if (pollTimer === null) {
        return;
    }

    window.clearTimeout(pollTimer);
    pollTimer = null;
};

const clearScansInput = (): void => {
    if (scansInput.value) {
        scansInput.value.value = '';
    }
};

const upsertUploads = (items: IntakeItem[]): void => {
    items.forEach((payload) => {
        const index = uploads.value.findIndex((item) => item.id === payload.id);

        if (index === -1) {
            uploads.value.unshift({
                ...payload,
                pages: payload.pages ?? [],
            });
            return;
        }

        uploads.value[index] = {
            ...uploads.value[index],
            ...payload,
            pages: payload.pages ?? uploads.value[index].pages ?? [],
            title: payload.title ?? uploads.value[index].title ?? null,
            scans_count: payload.scans_count ?? uploads.value[index].scans_count ?? 0,
            scans_size: payload.scans_size ?? uploads.value[index].scans_size ?? 0,
        };
    });
};

const formatFileSize = (size: number | null | undefined): string => {
    if (!size || size <= 0) {
        return '0 MB';
    }

    return `${(size / 1024 / 1024).toFixed(2)} MB`;
};

const parseErrorMessage = async (response: Response): Promise<string> => {
    try {
        const payload = await response.json();

        if (payload?.message) {
            return payload.message as string;
        }

        if (payload?.errors?.scans?.[0]) {
            return payload.errors.scans[0] as string;
        }
    } catch {
        // ignore invalid JSON
    }

    return 'Nie udalo sie przetworzyc pliku.';
};

const pollStatuses = async (): Promise<void> => {
    const ids = pendingUploads.value.map((item) => item.id);

    if (ids.length === 0) {
        stopPolling();
        return;
    }

    try {
        const response = await fetch(`${intakeUrl}?ids=${ids.join(',')}`, {
            headers: buildHeaders(),
            credentials: 'same-origin',
        });

        if (response.ok) {
            const payload = (await response.json()) as { items?: IntakeItem[] };
            if (payload.items) {
                upsertUploads(payload.items);
            }
        }
    } catch {
        // ignore polling errors
    }

    pollTimer = window.setTimeout(pollStatuses, 2000);
};

const startPolling = (): void => {
    stopPolling();

    if (pendingUploads.value.length > 0) {
        pollTimer = window.setTimeout(pollStatuses, 2000);
    }
};

const handleUploads = async (files: FileList | null): Promise<void> => {
    if (!files || files.length === 0) {
        return;
    }

    uploadError.value = null;
    isUploading.value = true;

    const formData = new FormData();
    Array.from(files).forEach((file) => formData.append('scans[]', file));

    try {
        const response = await fetch(intakeUrl, {
            method: 'POST',
            body: formData,
            headers: buildHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response));
        }

        const payload = (await response.json()) as { items?: IntakeItem[] };

        if (payload.items) {
            upsertUploads(payload.items);
        }
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie przetworzyc pliku.';
    } finally {
        isUploading.value = false;
        clearScansInput();
        startPolling();
    }
};

const handleScansChange = (event: Event): void => {
    const target = event.target as HTMLInputElement | null;
    void handleUploads(target?.files ?? null);
};

const handleScansDrop = (event: DragEvent): void => {
    isDragging.value = false;
    void handleUploads(event.dataTransfer?.files ?? null);
};

const openScansPicker = (): void => {
    scansInput.value?.click();
};

const ensureSelectionState = (item: IntakeItem): void => {
    if (selectionState[item.id]) {
        return;
    }

    selectionState[item.id] = {
        paperMode: false,
        binderId: props.binders[0]?.id ?? null,
    };
};

const finalizeIntake = async (item: IntakeItem, storageType: StorageType): Promise<void> => {
    ensureSelectionState(item);
    const selection = selectionState[item.id];

    actionBusy[item.id] = true;

    try {
        const payload: Record<string, unknown> = {
            storage_type: storageType,
        };

        if (storageType === 'paper') {
            payload.binder_id = selection.binderId;
        }

        const response = await fetch(`${intakeUrl}/${item.id}/finalize`, {
            method: 'POST',
            headers: {
                ...buildHeaders(),
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response));
        }

        const data = (await response.json()) as IntakeItem;
        upsertUploads([data]);
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie zapisac decyzji.';
    } finally {
        actionBusy[item.id] = false;
    }
};

const startIntake = async (item: IntakeItem): Promise<void> => {
    actionBusy[item.id] = true;

    try {
        const response = await fetch(`${intakeUrl}/${item.id}/start`, {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response));
        }

        const data = (await response.json()) as IntakeItem;
        upsertUploads([data]);
        startPolling();
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie uruchomic analizy.';
    } finally {
        actionBusy[item.id] = false;
    }
};

const retryIntake = async (item: IntakeItem): Promise<void> => {
    actionBusy[item.id] = true;

    try {
        const response = await fetch(`${intakeUrl}/${item.id}/retry`, {
            method: 'POST',
            headers: buildHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(await parseErrorMessage(response));
        }

        const data = (await response.json()) as IntakeItem;
        upsertUploads([data]);
        startPolling();
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie ponowic analizy.';
    } finally {
        actionBusy[item.id] = false;
    }
};

const removeIntake = async (item: IntakeItem): Promise<void> => {
    actionBusy[item.id] = true;

    try {
        const response = await fetch(`${intakeUrl}/${item.id}`, {
            method: 'DELETE',
            headers: buildHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok && response.status !== 204) {
            throw new Error(await parseErrorMessage(response));
        }

        uploads.value = uploads.value.filter((upload) => upload.id !== item.id);
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie usunac analizy.';
    } finally {
        actionBusy[item.id] = false;
    }
};

const removeAllIntakes = async (): Promise<void> => {
    if (uploads.value.length === 0) {
        return;
    }

    if (!window.confirm('Usunac wszystkie analizy z listy?')) {
        return;
    }

    bulkRemoveBusy.value = true;

    try {
        const response = await fetch(intakeUrl, {
            method: 'DELETE',
            headers: {
                ...buildHeaders(),
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ids: uploads.value.map((item) => item.id),
            }),
        });

        if (!response.ok && response.status !== 204) {
            throw new Error(await parseErrorMessage(response));
        }

        uploads.value = [];
        Object.keys(selectionState).forEach((key) => {
            delete selectionState[Number(key)];
        });
        Object.keys(actionBusy).forEach((key) => {
            delete actionBusy[Number(key)];
        });
    } catch (error) {
        uploadError.value =
            error instanceof Error ? error.message : 'Nie udalo sie usunac analiz.';
    } finally {
        bulkRemoveBusy.value = false;
    }
};

const openPreview = (item: IntakeItem): void => {
    if (item.pages.length === 0 && !item.preview_full_url) {
        return;
    }

    previewItem.value = item;
    activePreviewIndex.value = 0;
    isPreviewOpen.value = true;
};

watch(
    pendingUploads,
    (items) => {
        if (items.length > 0) {
            startTicker();
            startPolling();
        } else {
            stopTicker();
            stopPolling();
        }
    },
    { immediate: true },
);

watch(previewPages, (pages) => {
    if (pages.length === 0) {
        activePreviewIndex.value = 0;
        return;
    }

    if (activePreviewIndex.value >= pages.length) {
        activePreviewIndex.value = 0;
    }
});

watch(isPreviewOpen, (open) => {
    if (!open) {
        previewItem.value = null;
        activePreviewIndex.value = 0;
    }
});

onBeforeUnmount(() => {
    stopPolling();
    stopTicker();
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dokumenty',
        href: documentsRoutes.index().url,
    },
    {
        title: 'Nowe skany',
        href: documentsRoutes.create().url,
    },
];
</script>

<template>
    <Head title="Nowe skany" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Nowe skany"
                description="Dodaj jeden lub wiele plikow, a system wypelni dane automatycznie."
            />

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="scans">Skan dokumentu</Label>
                    <div
                        class="group relative flex min-h-[160px] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-6 text-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
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
                        <p v-if="isUploading" class="text-xs font-medium text-foreground">
                            Wysylanie plikow...
                        </p>
                    </div>
                    <p v-if="uploadError" class="text-xs text-destructive">
                        {{ uploadError }}
                    </p>
                </div>

                <div class="grid gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold">Lista analiz</p>
                        <Button
                            v-if="uploads.length > 0"
                            size="sm"
                            variant="destructive"
                            :disabled="bulkRemoveBusy"
                            @click="removeAllIntakes"
                        >
                            Usun wszystkie
                        </Button>
                    </div>

                    <div
                        v-if="uploads.length === 0"
                        class="rounded-xl border border-dashed bg-muted/10 p-6 text-center text-sm text-muted-foreground"
                    >
                        Brak wgranych plikow.
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="item in uploads"
                            :key="item.id"
                            class="rounded-xl border bg-background/60 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <button
                                        type="button"
                                        class="flex h-16 w-12 items-center justify-center overflow-hidden rounded-lg border bg-muted/20 text-[10px] text-muted-foreground transition hover:border-primary/50 disabled:cursor-not-allowed"
                                        :disabled="item.pages.length === 0 && !item.preview_full_url"
                                        @click="openPreview(item)"
                                    >
                                        <img
                                            v-if="item.pages[0]?.thumb_url || item.preview_url"
                                            :src="item.pages[0]?.thumb_url ?? item.preview_url ?? undefined"
                                            alt=""
                                            class="h-full w-full object-contain"
                                        />
                                        <span v-else>Brak podgladu</span>
                                    </button>
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-semibold text-foreground">
                                            {{ item.title || item.original_name || 'Dokument' }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Status: {{ statusLabel(item) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Plikow: {{ item.scans_count }} · {{ formatFileSize(item.scans_size) }}
                                        </p>
                                        <p
                                            v-if="item.pages.length > 1"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Stron: {{ item.pages.length }}
                                        </p>
                                        <p
                                            v-if="item.status === 'queued' || item.status === 'processing'"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Czas oczekiwania: {{ formatElapsed(item) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <Button
                                        v-if="canStart(item)"
                                        size="sm"
                                        :disabled="actionBusy[item.id]"
                                        @click="startIntake(item)"
                                    >
                                        Analizuj
                                    </Button>
                                    <Button
                                        v-if="canRetry(item)"
                                        size="sm"
                                        variant="outline"
                                        :disabled="actionBusy[item.id]"
                                        @click="retryIntake(item)"
                                    >
                                        Ponow
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :disabled="actionBusy[item.id]"
                                        @click="removeIntake(item)"
                                    >
                                        Usun
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="item.error_message"
                                class="mt-3 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive"
                            >
                                {{ item.error_message }}
                            </div>

                            <div
                                v-if="canFinalize(item)"
                                class="mt-4 flex flex-col gap-3"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Button
                                        size="sm"
                                        :disabled="actionBusy[item.id]"
                                        @click="
                                            ensureSelectionState(item);
                                            selectionState[item.id].paperMode = true;
                                        "
                                    >
                                        Papierowa
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="actionBusy[item.id]"
                                        @click="finalizeIntake(item, 'electronic')"
                                    >
                                        Elektroniczna
                                    </Button>
                                </div>

                                <div
                                    v-if="selectionState[item.id]?.paperMode"
                                    class="flex flex-col gap-2 rounded-lg border bg-muted/20 p-3"
                                >
                                    <div
                                        v-if="props.binders.length > 0"
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <Label :for="`binder_${item.id}`" class="text-xs">
                                            Segregator
                                        </Label>
                                        <select
                                            :id="`binder_${item.id}`"
                                            v-model.number="selectionState[item.id].binderId"
                                            class="h-9 min-w-[200px] rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                                        >
                                            <option
                                                v-for="binder in props.binders"
                                                :key="binder.id"
                                                :value="binder.id"
                                            >
                                                {{ binder.name }}
                                            </option>
                                        </select>
                                        <Button
                                            size="sm"
                                            :disabled="actionBusy[item.id] || !selectionState[item.id].binderId"
                                            @click="finalizeIntake(item, 'paper')"
                                        >
                                            Zapisz
                                        </Button>
                                    </div>
                                    <p
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Brak segregatorow.
                                        <Link class="underline" :href="bindersRoutes.create().url">
                                            Dodaj segregator
                                        </Link>
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="item.status === 'finalized'"
                                class="mt-3 text-xs text-muted-foreground"
                            >
                                Decyzja zapisana.
                            </div>

                            <div
                                v-if="item.status === 'done' && !item.document_id"
                                class="mt-3 text-xs text-muted-foreground"
                            >
                                Analiza nie utworzyla dokumentu. Ponow analize.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <Dialog :open="isPreviewOpen" @update:open="isPreviewOpen = $event">
        <DialogContent class="max-w-5xl">
            <DialogHeader>
                <DialogTitle>
                    {{ previewItem?.original_name || 'Podglad dokumentu' }}
                </DialogTitle>
            </DialogHeader>
            <div class="grid gap-4">
                <div
                    v-if="previewPages.length > 1"
                    class="flex gap-2 overflow-x-auto rounded-lg border bg-muted/10 p-2"
                >
                    <button
                        v-for="(page, index) in previewPages"
                        :key="page.id"
                        type="button"
                        class="flex h-16 w-12 flex-col items-center justify-center overflow-hidden rounded-md border bg-background/60 text-[10px] text-muted-foreground transition"
                        :class="
                            activePreviewIndex === index
                                ? 'border-primary shadow-sm'
                                : 'border-transparent hover:border-primary/40'
                        "
                        @click="activePreviewIndex = index"
                    >
                        <img
                            :src="page.thumb_url ?? page.url"
                            alt=""
                            class="h-full w-full object-contain"
                        />
                    </button>
                </div>

                <div class="flex max-h-[70vh] justify-center overflow-auto rounded-lg border bg-muted/10 p-4">
                    <img
                        v-if="activePreviewPage"
                        :src="activePreviewPage.url"
                        alt=""
                        class="max-h-[60vh] w-auto object-contain"
                    />
                    <img
                        v-else-if="previewItem?.preview_full_url"
                        :src="previewItem.preview_full_url"
                        alt=""
                        class="max-h-[60vh] w-auto object-contain"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Brak podgladu.
                    </p>
                </div>

                <p v-if="previewLabel" class="text-xs text-muted-foreground">
                    {{ previewLabel }}
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
