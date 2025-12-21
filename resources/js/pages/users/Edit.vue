<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/UserController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import usersRoutes from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';

interface UserItem {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    appearance: string;
    two_factor_enabled: boolean;
}

const props = defineProps<{
    user: UserItem;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Uzytkownicy',
        href: usersRoutes.index().url,
    },
    {
        title: 'Edycja uzytkownika',
        href: usersRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Edycja uzytkownika" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Edycja uzytkownika"
                    description="Zmien dane, role i preferencje konta."
                />
                <Badge :variant="user.two_factor_enabled ? 'default' : 'destructive'">
                    2FA {{ user.two_factor_enabled ? 'wlaczone' : 'wylaczone' }}
                </Badge>
            </div>

            <AlertError
                v-if="$page.props.errors?.user"
                :errors="[$page.props.errors.user]"
                title="Operacja nie powiodla sie."
            />

            <Form
                :action="UserController.update(user.id).url"
                method="put"
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
                        :default-value="user.name"
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
                        :default-value="user.email"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="appearance">Tryb wygladu</Label>
                    <select
                        id="appearance"
                        name="appearance"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30"
                    >
                        <option value="system" :selected="user.appearance === 'system'">
                            System
                        </option>
                        <option value="light" :selected="user.appearance === 'light'">
                            Jasny
                        </option>
                        <option value="dark" :selected="user.appearance === 'dark'">
                            Ciemny
                        </option>
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
                        <option value="0" :selected="!user.is_admin">Uzytkownik</option>
                        <option value="1" :selected="user.is_admin">Administrator</option>
                    </select>
                    <InputError :message="errors.is_admin" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Nowe haslo (opcjonalnie)</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        placeholder="Wpisz, aby zmienic haslo"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Powtorz nowe haslo</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Powtorz nowe haslo"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <Button :disabled="processing">Zapisz</Button>
                    <Button as-child variant="ghost">
                        <Link :href="usersRoutes.index().url">Wroc</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
