<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem
} from '@/components/ui/dropdown-menu'

import { ref } from 'vue'

// ISO 3166-1 alpha-2 country codes (you can replace these with full names if needed)
const countries = [
    'PH', 'US', 'CA', 'GB', 'AU', 'SG', 'MY', 'JP', 'KR', 'CN',
    'DE', 'FR', 'IT', 'ES', 'IN', 'TH', 'VN', 'ID', 'BR', 'ZA'
]

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="w-full px-3 py-2 border rounded text-left"
        >
            {{ modelValue }}
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-48">
            <DropdownMenuLabel>Select Country</DropdownMenuLabel>
            <DropdownMenuRadioGroup
                :modelValue="modelValue"
                @update:modelValue="emit('update:modelValue', $event)"
            >
                <DropdownMenuRadioItem
                    v-for="country in countries"
                    :key="country"
                    :value="country"
                >
                    {{ country }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
