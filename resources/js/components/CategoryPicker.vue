<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

interface CategoryOption {
    id: number;
    name: string;
}

const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        modelValue: number | null;
        name?: string;
    }>(),
    {
        name: 'category_id',
    },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: number | null): void;
}>();

const inputName = computed(() => props.name ?? 'category_id');
</script>

<template>
    <input
        type="hidden"
        :id="inputName"
        :name="inputName"
        :value="modelValue ?? ''"
    />
    <div class="flex flex-wrap gap-2">
        <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            class="rounded-full border border-transparent transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
            :class="{
                'border-ring/60 shadow-sm': modelValue === category.id,
            }"
            :aria-pressed="modelValue === category.id"
            @click="emit('update:modelValue', category.id)"
        >
            <Badge :variant="modelValue === category.id ? 'default' : 'secondary'">
                {{ category.name }}
            </Badge>
        </button>
    </div>
</template>
