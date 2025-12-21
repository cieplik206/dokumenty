<script setup lang="ts">
import UserController from '@/actions/App/Http/Controllers/UserController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import usersRoutes from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface UserItem {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    appearance: string;
    two_factor_enabled: boolean;
}

defineProps<{
    users: UserItem[];
}>();

const appearanceLabels: Record<string, string> = {
    system: 'System',
    light: 'Jasny',
    dark: 'Ciemny',
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Uzytkownicy',
        href: usersRoutes.index().url,
    },
];
</script>

<template>
    <Head title="Uzytkownicy" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Uzytkownicy"
                    description="Zarzadzaj kontami, rolami i ustawieniami wygladu."
                />
                <Button as-child>
                    <Link :href="usersRoutes.create().url">Nowy uzytkownik</Link>
                </Button>
            </div>

            <AlertError
                v-if="$page.props.errors?.user"
                :errors="[$page.props.errors.user]"
                title="Operacja nie powiodla sie."
            />

            <div
                v-if="users.length === 0"
                class="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Brak uzytkownikow. Dodaj pierwsze konto administratora.
            </div>

            <div v-else class="overflow-hidden rounded-xl border">
                <div
                    v-for="user in users"
                    :key="user.id"
                    class="flex flex-col gap-4 border-b border-border/70 p-4 last:border-b-0 md:flex-row md:items-center md:justify-between"
                >
                    <div class="space-y-2">
                        <div>
                            <div class="text-base font-medium text-foreground">
                                {{ user.name }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge :variant="user.is_admin ? 'default' : 'secondary'">
                                {{ user.is_admin ? 'Administrator' : 'Uzytkownik' }}
                            </Badge>
                            <Badge variant="secondary">
                                Wyglad: {{ appearanceLabels[user.appearance] || 'System' }}
                            </Badge>
                            <Badge :variant="user.two_factor_enabled ? 'default' : 'destructive'">
                                2FA {{ user.two_factor_enabled ? 'wlaczone' : 'wylaczone' }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button as-child size="sm" variant="secondary">
                            <Link :href="usersRoutes.edit(user.id).url">Edytuj</Link>
                        </Button>
                        <Button as-child size="sm" variant="destructive">
                            <Link
                                method="delete"
                                as="button"
                                :href="UserController.destroy(user.id).url"
                            >
                                Usun
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
