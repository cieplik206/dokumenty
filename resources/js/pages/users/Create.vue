<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import usersRoutes from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Uzytkownicy',
        href: usersRoutes.index().url,
    },
    {
        title: 'Nowy uzytkownik',
        href: usersRoutes.create().url,
    },
];
</script>

<template>
    <Head title="Nowy uzytkownik" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <Heading
                title="Nowy uzytkownik"
                description="Utworz konto, ustaw role i preferencje wygladu."
            />

            <Form
                :action="UserController.store().url"
                method="post"
                class="flex max-w-2xl flex-col gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Imie i nazwisko</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="name"
                        placeholder="Jan Kowalski"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        placeholder="jan@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Haslo</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Ustaw haslo"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Powtorz haslo</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Powtorz haslo"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="appearance">Tryb wygladu</Label>
                    <select
                        id="appearance"
                        name="appearance"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                    >
                        <option value="system" selected>System</option>
                        <option value="light">Jasny</option>
                        <option value="dark">Ciemny</option>
                    </select>
                    <InputError :message="errors.appearance" />
                </div>

                <div class="grid gap-2">
                    <Label for="is_admin">Rola</Label>
                    <select
                        id="is_admin"
                        name="is_admin"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                    >
                        <option value="0" selected>Uzytkownik</option>
                        <option value="1">Administrator</option>
                    </select>
                    <InputError :message="errors.is_admin" />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="usersRoutes.index().url">Anuluj</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
