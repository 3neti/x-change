<script setup lang="ts">
import type {
    StampDesignOption,
    StampPresentation,
    StampOptions,
} from '../stampQrPresentation';
import { computed } from 'vue';
const props = defineProps<{
    designs: StampDesignOption[];
    designId: string;
    revision: number;
    presentation: StampPresentation | null;
    customMessage: boolean;
    message: string;
    legacyArtwork?: boolean;
}>();
const emit = defineEmits<{
    choose: [id: string];
    change: [options: Partial<StampOptions>];
    reset: [];
    customMessage: [enabled: boolean];
    message: [value: string];
}>();
const active = computed(() =>
    props.designs.find(
        (d) => d.id === props.designId && d.revision === props.revision,
    ),
);
const controls = computed(() =>
    props.legacyArtwork ? [] : (active.value?.customization?.controls ?? []),
);
const options = computed(() => ({
    preset: 'transparent',
    crop: 'center',
    darkening: 0,
    show_tagline: true,
    ...active.value?.customization?.defaults,
    ...props.presentation?.options,
}));
</script>
<template>
    <div class="space-y-5" data-testid="stamp-designer">
        <fieldset>
            <legend class="mb-2 text-sm font-semibold">Choose a design</legend>
            <p v-if="legacyArtwork" class="mb-2 text-xs text-muted-foreground">
                Custom artwork is active. Choose a design to switch back to a
                system stamp.
            </p>
            <p v-if="!active" class="mb-2 text-xs text-muted-foreground">
                Saved design preserved. Choose a card to use its latest design.
            </p>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="design in designs"
                    :key="design.id"
                    type="button"
                    :aria-pressed="!legacyArtwork && active?.id === design.id"
                    class="overflow-hidden rounded-lg border-2 text-left focus-visible:outline-2 focus-visible:outline-offset-2"
                    :class="
                        !legacyArtwork && active?.id === design.id
                            ? 'border-primary'
                            : 'border-transparent'
                    "
                    @click="emit('choose', design.id)"
                >
                    <img
                        :src="design.thumbnail"
                        alt=""
                        class="aspect-[1200/630] w-full object-cover"
                        loading="lazy"
                    />
                    <span class="block px-2 py-1 text-xs font-medium">{{
                        design.name
                    }}</span>
                </button>
            </div>
        </fieldset>
        <fieldset class="space-y-2">
            <legend class="mb-2 text-sm font-semibold">Stamp message</legend>
            <label class="flex items-center gap-2 text-sm"
                ><input
                    type="checkbox"
                    :checked="!customMessage"
                    @change="
                        emit(
                            'customMessage',
                            !($event.target as HTMLInputElement).checked,
                        )
                    "
                />Use Order purpose</label
            >
            <textarea
                v-if="customMessage"
                aria-label="Stamp-only message"
                :value="message"
                maxlength="240"
                rows="3"
                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                placeholder="Message for this stamp"
                @input="
                    emit(
                        'message',
                        ($event.target as HTMLTextAreaElement).value,
                    )
                "
            />
            <p class="text-xs text-muted-foreground">
                Appearance changes do not change your Order.
            </p>
        </fieldset>
        <details v-if="controls.length" class="rounded-lg border p-3" open>
            <summary class="cursor-pointer text-sm font-semibold">
                Customize
            </summary>
            <div class="mt-3 space-y-4">
                <label v-if="controls.includes('qr')" class="block text-sm"
                    >QR appearance
                    <select
                        :value="options.preset"
                        aria-label="QR appearance"
                        class="mt-1 block w-full rounded-md border bg-background px-3 py-2"
                        @change="
                            emit('change', {
                                preset: ($event.target as HTMLSelectElement)
                                    .value as StampOptions['preset'],
                            })
                        "
                    >
                        <option value="clear">Clear</option>
                        <option value="soft">Soft</option>
                        <option value="transparent">Transparent</option>
                    </select>
                </label>
                <label v-if="controls.includes('crop')" class="block text-sm"
                    >Artwork crop
                    <select
                        :value="options.crop"
                        aria-label="Artwork crop"
                        class="mt-1 block w-full rounded-md border bg-background px-3 py-2"
                        @change="
                            emit('change', {
                                crop: ($event.target as HTMLSelectElement)
                                    .value as StampOptions['crop'],
                            })
                        "
                    >
                        <option value="top">Top</option>
                        <option value="center">Center</option>
                        <option value="bottom">Bottom</option>
                    </select>
                </label>
                <label
                    v-if="controls.includes('darkening')"
                    class="block text-sm"
                    >Artwork darkening: {{ options.darkening }}%
                    <input
                        type="range"
                        aria-label="Artwork darkening"
                        min="0"
                        max="40"
                        step="5"
                        :value="options.darkening"
                        class="mt-2 block w-full"
                        @input="
                            emit('change', {
                                darkening: Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            })
                        "
                    />
                </label>
                <label
                    v-if="controls.includes('tagline')"
                    class="flex items-center gap-2 text-sm"
                    ><input
                        type="checkbox"
                        :checked="options.show_tagline"
                        @change="
                            emit('change', {
                                show_tagline: (
                                    $event.target as HTMLInputElement
                                ).checked,
                            })
                        "
                    />Show tagline</label
                >
            </div>
        </details>
        <button
            type="button"
            class="rounded-md border px-3 py-2 text-sm"
            @click="emit('reset')"
        >
            Reset appearance
        </button>
        <details class="rounded-lg border p-3">
            <summary class="cursor-pointer text-sm font-semibold">
                Advanced artwork and layout
            </summary>
            <div class="mt-4"><slot /></div>
        </details>
    </div>
</template>
