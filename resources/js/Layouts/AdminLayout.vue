<script setup lang="ts">
import { ref, onMounted, computed, watch } from "vue";
import { useEdtStore } from '@/stores/edtStore'
import HeaderMenu from "@/Components/Navigation/HeaderMenu.vue";
import { sidebarMenuItems } from "@/config/navigation";
import MainLayout from "./MainLayout.vue";
import AddYearPopup from "@/Components/Popup/AddYearPopup.vue";
import IconButton from "@/Components/IconButton.vue";
import Filter from "@/Components/Filter.vue";
import axios from "axios";

interface Year {
    id: number;
    name: string;
}

const currentPath = ref(window.location.pathname.split("/")[1]);
const showAddYearPopup = ref(false);
const years = ref<Year[]>([]);
const selectedYearIndex = ref(0);

const selectedYear = computed(() => {
    return years.value[selectedYearIndex.value]?.name || "Aucune année";
});

watch(selectedYear, (val) => {
    (window as any).appSelectedYear = val;
    const edt = useEdtStore()
    edt.setYear(val as string)
});

onMounted(() => {
    (window as any).appSelectedYear = selectedYear.value;
    const edt = useEdtStore()
    edt.setYear(selectedYear.value as string)
});

const handlePreviousYear = () => {
    if (selectedYearIndex.value > 0) {
        selectedYearIndex.value--;
    }
};

const handleNextYear = () => {
    if (selectedYearIndex.value < years.value.length - 1) {
        selectedYearIndex.value++;
    }
};

const getCurrentSubmenu = () => {
    const currentItem = sidebarMenuItems.find(
        (item) => item.route === currentPath.value
    );
    return currentItem?.submenu;
};

const handleAddYear = () => {
    showAddYearPopup.value = true;
};

onMounted(async () => {
    try {
        const response = await axios.get("/api/years");
        years.value = response.data;
    } catch (error) {
        console.error("Erreur lors de la récupération des années:", error);
    }
});
</script>

<template>
    <MainLayout>
        <div class="flex flex-col gap-10 -mt-10 h-[calc(100%+40px)]">
            <div class="flex justify-between items-center px-6">
                <HeaderMenu :items="getCurrentSubmenu()!" />
                <div class="flex items-center gap-4">
                    <Filter
                        :selectedItemName="selectedYear"
                        :hasBorder="true"
                        @previous="handlePreviousYear"
                        @next="handleNextYear"
                    />
                    <IconButton
                        @click="handleAddYear"
                        icon-class="Plus"
                        bg-color="#FFD8E4"
                        icon-color="black"
                        :small="false"
                        :style="{
                            borderRadius: '8px',
                            height: '42px',
                            width: '42px',
                            padding: '0',
                        }"
                    />
                </div>
            </div>
            <div class="content w-full flex-1">
                <slot />
            </div>
        </div>
    </MainLayout>

    <AddYearPopup
        v-if="showAddYearPopup"
        :show="showAddYearPopup"
        @close="showAddYearPopup = false"
    />
</template>
