<script setup lang="ts">
import BinderController from '@/actions/App/Http/Controllers/BinderController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import bindersRoutes from '@/routes/binders';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

interface BinderItem {
    id: number;
    name: string;
    location: string | null;
    description: string | null;
    sort_order: number;
}

defineProps<{
    binder: BinderItem;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Segregatory',
        href: bindersRoutes.index().url,
    },
    {
        title: 'Edycja segregatora',
        href: bindersRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Edycja segregatora" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Edycja segregatora"
                description="Zmien nazwe, lokalizacje lub opis segregatora."
            />

            <Form
                :action="BinderController.update(binder.id).url"
                method="put"
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
                        :default-value="binder.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="location">Lokalizacja</Label>
                    <Input
                        id="location"
                        name="location"
                        autocomplete="off"
                        :default-value="binder.location || ''"
                    />
                    <InputError :message="errors.location" />
                </div>

                <div class="grid gap-2">
                    <Label for="sort_order">Kolejnosc</Label>
                    <Input
                        id="sort_order"
                        name="sort_order"
                        type="number"
                        min="0"
                        :default-value="binder.sort_order"
                    />
                    <InputError :message="errors.sort_order" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Opis</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                        :value="binder.description || ''"
                        placeholder="Co trzymasz w tym segregatorze?"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="bindersRoutes.show(binder.id).url">Wroc</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
