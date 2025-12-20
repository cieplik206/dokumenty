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
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface BinderItem {
    id: number;
    name: string;
    location: string | null;
    description: string | null;
    documents_count: number;
    sort_order: number;
}

defineProps<{
    binders: BinderItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Segregatory',
        href: bindersRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Segregatory" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Segregatory"
                    description="Zarzadzaj segregatorami i przypisanymi dokumentami."
                />
                <Button as-child>
                    <Link :href="bindersRoutes.create().url">Nowy segregator</Link>
                </Button>
            </div>

            <div
                v-if="binders.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Brak segregatorow. Dodaj pierwszy, aby uporzadkowac dokumenty.
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Card v-for="binder in binders" :key="binder.id">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between gap-4">
                            <span>{{ binder.name }}</span>
                            <span class="text-sm font-normal text-muted-foreground">
                                {{ binder.documents_count }} dokumentow
                            </span>
                        </CardTitle>
                        <CardDescription v-if="binder.location">
                            Lokalizacja: {{ binder.location }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="binder.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ binder.description }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child size="sm" variant="secondary">
                                <Link :href="bindersRoutes.show(binder.id).url">
                                    Podglad
                                </Link>
                            </Button>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="bindersRoutes.edit(binder.id).url">
                                    Edytuj
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
