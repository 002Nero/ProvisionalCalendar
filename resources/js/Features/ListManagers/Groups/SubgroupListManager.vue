<script setup lang="ts">
import ListManager from "@/Components/ListManager/ListManager.vue";
import { defineProps, defineEmits, onMounted, computed, ref, watch } from "vue";
import { useLabelsStore } from "@/stores/labelsStore";
import { Item } from "@/types/models/utils";
import { Subgroup } from "@/types/models/groups";
import AddSubgroupPopup from "@/Features/Popups/Groups/Subgroups/AddSubgroupPopup.vue";
import EditSubgroupPopup from "@/Features/Popups/Groups/Subgroups/EditSubgroupPopup.vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";
import { useSubgroupService } from "@/services/groups/subgroupService";

const labelsStore = useLabelsStore();

const props = defineProps<{ groupId?: number }>();

const emit = defineEmits([
    "select",
    "successfullyAdded",
    "successfullyEdited",
    "successfullyDeleted",
]);

const subgroupService = useSubgroupService();
const subgroups = ref<Item[] | undefined>();

const subgroupToEditId = ref<number | undefined>();

const isAddSubgroupPopupVisible = ref<boolean>(false);

const errorMessage = ref<string>();

watch(
    () => props.groupId,
    () => fetchSubgroups()
);

const title = computed(() => {
    return labelsStore.getLabel("Demi-groupe");
});

onMounted(() => labelsStore.fetchLabels());

const showAddSubgroupPopup = () => (isAddSubgroupPopupVisible.value = true);
const hideAddSubgroupPopup = () => (isAddSubgroupPopupVisible.value = false);

const showEditSubgroupPopup = (subgroupId: number) =>
    (subgroupToEditId.value = subgroupId);
const hideEditSubgroupPopup = () => (subgroupToEditId.value = undefined);

const showErrorPopup = (error: string) => (errorMessage.value = error);
const resetErrorMessage = () => (errorMessage.value = undefined);

const withStudentCountLabel = (items: Subgroup[]) =>
    items.map((item) => ({
        ...item,
        name:
            item.student_amount != null
                ? `${item.name} (${item.student_amount})`
                : item.name,
    }));

const fetchSubgroups = () =>
    ((): void => {
        console.debug('SubgroupListManager.fetchSubgroups called with groupId=', props.groupId)
        if (!props.groupId) {
            subgroups.value = undefined
            return
        }
        subgroupService
            .getSubgroups(props.groupId)
            .then(
                (returnedSubgroups: Subgroup[]) => {
                    console.debug('SubgroupListManager: received subgroups count=', returnedSubgroups?.length)
                    // If the backend returns no subgroups for this group, show default A/B
                    if (!returnedSubgroups || returnedSubgroups.length === 0) {
                        subgroups.value = [
                            { id: -1, name: 'A' },
                            { id: -2, name: 'B' },
                        ] as any;
                    } else {
                        subgroups.value = withStudentCountLabel(returnedSubgroups)
                    }
                }
            )
            .catch((err) => {
                // Log the error for debugging but fallback to default A/B subgroups
                // so the UI remains usable when the API fails or returns an error.
                console.error('SubgroupListManager.fetchSubgroups error', err)
                subgroups.value = [
                    { id: -1, name: 'A' },
                    { id: -2, name: 'B' },
                ] as any;
            })
    })();

const handleSelect = (item: number) => {
    emit("select", item);
};

const handleAdd = () => {
    showAddSubgroupPopup();
};

const handleSuccessfullyAdded = () => {
    hideAddSubgroupPopup();
    fetchSubgroups();
    emit("successfullyAdded");
};

const handleSuccessfullyEdited = (subgroup: Subgroup) => {
    hideEditSubgroupPopup();
    fetchSubgroups();
    emit("successfullyEdited", subgroup);
};

const handleSuccessfullyDeleted = (id: number) => {
    hideEditSubgroupPopup();
    fetchSubgroups();
    emit("successfullyDeleted", id);
};
</script>

<template>
    <div>
        <ListManager
            :title="title"
            hasAdd
            :canAdd="!!props.groupId"
            :items="subgroups"
            @select="handleSelect"
            @edit="showEditSubgroupPopup"
            @add="handleAdd"
        />
        <AddSubgroupPopup
            v-if="isAddSubgroupPopupVisible"
            :groupId="props.groupId!"
            @successfullyAdded="handleSuccessfullyAdded"
            @cancel="hideAddSubgroupPopup"
        />
        <EditSubgroupPopup
            v-if="subgroupToEditId"
            :subgroupId="subgroupToEditId"
            @successfullyEdited="handleSuccessfullyEdited"
            @successfullyDeleted="handleSuccessfullyDeleted"
            @cancel="hideEditSubgroupPopup"
        />
        <ErrorPopup
            v-if="errorMessage"
            :message="errorMessage"
            @close="resetErrorMessage"
        />
    </div>
</template>
