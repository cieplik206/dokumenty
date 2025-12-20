<script setup lang="ts">
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import categoriesRoutes from '@/routes/categories';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Kategorie',
        href: categoriesRoutes.index().url,
    },
    {
        title: 'Nowa kategoria',
        href: categoriesRoutes.create().url,
    },
];
</script>

<template>
    <Head title="Nowa kategoria" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Nowa kategoria"
                description="Dodaj kategorie, ktora bedzie wybierana dla dokumentow."
            />

            <Form
                :action="CategoryController.store().url"
                method="post"
                class="flex max-w-2xl flex-col gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nazwa</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        placeholder="Faktury"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Opis</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                        placeholder="Krotki opis kategorii"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="categoriesRoutes.index().url">Anuluj</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
