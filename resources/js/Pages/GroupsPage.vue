<script setup lang="ts">
import AdminLayout from "@/Layouts/AdminLayout.vue";
import GroupListManager from "@/Features/ListManagers/Groups/GroupListManager.vue";
import SubgroupListManager from "@/Features/ListManagers/Groups/SubgroupListManager.vue";
import { ref, computed } from "vue";
import { useEdtStore } from '@/stores/edtStore'
import PromotionListManager from "@/Features/ListManagers/Groups/PromotionListManager.vue";

const edtStore = useEdtStore()
const yearId = computed<number | null>(() => {
    return (typeof edtStore.year === 'number') ? edtStore.year : (edtStore.year ? Number(edtStore.year) : null)
})
const selectedPromotionId = ref<number | undefined>();
const selectedGroupId = ref<number | undefined>();
const groupListRefreshKey = ref(0);
const promotionListRefreshKey = ref(0);

const unselectPromotion = () => {
    selectedPromotionId.value = undefined;
    selectedGroupId.value = undefined;
};

const unselectGroup = () => {
    selectedGroupId.value = undefined;
};

const handlePromotionSelect = (id: number) => {
    if (selectedPromotionId.value === id) {
        unselectPromotion();
        return;
    }
    selectedPromotionId.value = id;
    selectedGroupId.value = undefined;
};

const handleGroupSelect = (id: number) => {
    if (selectedGroupId.value === id) {
        selectedGroupId.value = undefined;
        return;
    }
    selectedGroupId.value = id;
};

const handlePromotionDeleted = (id: number) => {
    console.log(id);
    if (selectedPromotionId.value === id) {
        unselectPromotion();
    }
};

const handleGroupDeleted = (id: number) => {
    if (selectedGroupId.value === id) {
        unselectGroup();
    }
};

const refreshGroupAndPromotionTotals = () => {
    groupListRefreshKey.value += 1;
    promotionListRefreshKey.value += 1;
};
</script>

<template>
    <AdminLayout>
        <div class="flex gap-10 w-full h-full">
            <PromotionListManager
                :key="promotionListRefreshKey"
                v-if="yearId !== null"
                class="w-full h-full"
                :yearId="yearId"
                :selectedPromotionId="selectedPromotionId"
                @select="handlePromotionSelect"
                @successfullyDeleted="handlePromotionDeleted"
            />
            <GroupListManager
                :key="groupListRefreshKey"
                class="w-full h-full"
                :promotionId="selectedPromotionId"
                :selectedGroupId="selectedGroupId"
                @select="handleGroupSelect"
                @successfullyDeleted="handleGroupDeleted"
            />
            <SubgroupListManager
                :groupId="selectedGroupId"
                class="w-full h-full"
                @successfullyAdded="refreshGroupAndPromotionTotals"
                @successfullyEdited="refreshGroupAndPromotionTotals"
                @successfullyDeleted="refreshGroupAndPromotionTotals"
            />
        </div>
    </AdminLayout>
</template>
