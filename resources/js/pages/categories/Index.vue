<script setup lang="ts">
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import AlertError from '@/components/AlertError.vue';
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
import categoriesRoutes from '@/routes/categories';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface CategoryItem {
    id: number;
    name: string;
    description: string | null;
    documents_count: number;
}

defineProps<{
    categories: CategoryItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Kategorie',
        href: categoriesRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Kategorie" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Kategorie"
                    description="Zarzadzaj zestawem kategorii dla dokumentow."
                />
                <Button as-child>
                    <Link :href="categoriesRoutes.create().url">Nowa kategoria</Link>
                </Button>
            </div>

            <AlertError
                v-if="$page.props.errors?.category"
                :errors="[$page.props.errors.category]"
                title="Nie mozna usunac kategorii."
            />

            <div
                v-if="categories.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Brak kategorii. Dodaj pierwsza, aby przypisywac dokumenty.
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <Card v-for="category in categories" :key="category.id">
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between gap-4">
                            <span>{{ category.name }}</span>
                            <span class="text-sm font-normal text-muted-foreground">
                                {{ category.documents_count }} dokumentow
                            </span>
                        </CardTitle>
                        <CardDescription v-if="category.description">
                            {{ category.description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Button as-child size="sm" variant="secondary">
                            <Link :href="categoriesRoutes.edit(category.id).url">
                                Edytuj
                            </Link>
                        </Button>
                        <Button as-child size="sm" variant="destructive">
                            <Link
                                method="delete"
                                as="button"
                                :href="CategoryController.destroy(category.id).url"
                            >
                                Usun
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
