<script setup lang="ts">
import { ref, defineEmits, computed } from "vue";
import Filter from "@/Components/Filter.vue";
import SearchBar from "@/Components/SearchBar.vue";
import SelectionnableEditableButtonList from "./SelectionnableEditableButtonList.vue";
import { Item } from "@/types/models/utils";
import { Period } from "@/types/models/periods";

const props = defineProps<{
    title: string;
    periods?: Period[];
    items?: Item[];
    selectedItemsId?: number[];
    hasFilter?: boolean;
    hasAdd?: boolean;
    canAdd?: boolean;
    hasImport?: boolean;
}>();

const emit = defineEmits(["select", "edit", "add"]);

const selectedPeriodId = ref(0);

const searchValue = ref("");

const visibleItems = computed(() => {
    if (!props.items) return [];

    if (props.periods)
        return props.items
            .filter(
                (item) =>
                    item.period?.id != null &&
                    item.period.id - 1 === selectedPeriodId.value
            )
            .filter(
                (item) =>
                    item?.name
                        ?.toLowerCase()
                        ?.includes(searchValue.value.toLowerCase()) ?? false
            );
    else
        return props.items.filter(
            (item) =>
                item?.name
                    ?.toLowerCase()
                    ?.includes(searchValue.value.toLowerCase()) ?? false
        );
});

const handleNextPeriod = () => {
    selectedPeriodId.value =
        selectedPeriodId.value < (props.periods?.length ?? 0) - 1
            ? selectedPeriodId.value + 1
            : 0;
};

const handlePreviousPeriod = () => {
    selectedPeriodId.value =
        selectedPeriodId.value === 0
            ? (props.periods?.length ?? 1) - 1
            : selectedPeriodId.value - 1;
};

const handleSearch = (event: Event) => {
    searchValue.value = (event.target as HTMLInputElement).value;
};

const listManager = ref<HTMLElement | null>(null);

// Normalize select payloads: if payload is an object with `id`, emit id, otherwise emit payload
const handleItemSelect = (payload: any) => {
    try {
        if (payload && typeof payload === "object") {
            if (payload.id != null) {
                emit("select", payload.id);
                return;
            }
            // nested cases
            if (payload.teacher && payload.teacher.id != null) {
                emit("select", payload.teacher.id);
                return;
            }
            if (payload.value != null) {
                emit("select", payload.value);
                return;
            }
        }
        // fallback
        emit("select", payload);
    } catch (e) {
        // prevent uncaught exceptions from bubbling up to Vue's global handler
        // and log for debugging
        // eslint-disable-next-line no-console
        console.error("handleItemSelect error:", e, payload);
    }
};
</script>

<template>
    <div
        ref="listManager"
        class="list-manager h-full flex flex-col p-6 bg-white rounded-3xl shadow-lg "
    >
        <h1 class="text-2xl font-bold mb-4">{{ title }}</h1>
        <SearchBar
            placeholder="Rechercher..."
            :hasAdd
            :canAdd
            :hasImport
            class="mb-4"
            @input="handleSearch"
            @addClick="emit('add')"
        />

        <Filter
            v-if="periods"
            class="w-52 mb-4"
            hasBorder
            :selectedItemName="periods[selectedPeriodId].name"
            @previous="handlePreviousPeriod"
            @next="handleNextPeriod"
        />

        <div class="flex-1 min-h-0 overflow-y-auto">
            <SelectionnableEditableButtonList
                v-if="visibleItems.length > 0"
                class="w-full"
                :items="visibleItems"
                :selectedItemsId="props.selectedItemsId"
                @select="handleItemSelect"
                @edit="(e) => emit('edit', e)"
            />
            <div v-else class="flex items-center justify-center h-full">
                <p>Aucun élément trouvé</p>
            </div>
        </div>
    </div>
</template>
