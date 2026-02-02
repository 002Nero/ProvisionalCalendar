<script setup lang="ts">
import ListManager from "@/Components/ListManager/ListManager.vue";
import { defineProps, defineEmits, onMounted, computed, ref, watch } from "vue";
import { useLabelsStore } from "@/stores/labelsStore";
import { Item } from "@/types/models/utils";
import { useGroupService } from "@/services/groups/groupService";
import AddGroupPopup from "@/Features/Popups/Groups/Groups/AddGroupPopup.vue";
import EditGroupPopup from "@/Features/Popups/Groups/Groups/EditGroupPopup.vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";
import { Group } from "@/types/models/groups";

const labelsStore = useLabelsStore();

const props = defineProps<{ promotionId?: number; selectedGroupId?: number }>();

const emit = defineEmits([
    "select",
    "successfullyAdded",
    "successfullyEdited",
    "successfullyDeleted",
]);

const groupService = useGroupService();

const groups = ref<Item[] | undefined>();

const groupToEditId = ref<number | undefined>();

const isAddGroupPopupVisible = ref<boolean>(false);

const errorMessage = ref<string>();

watch(
    () => props.promotionId,
    () => fetchGroups()
);

const title = computed(() => {
    return labelsStore.getLabel("Groupe");
});

onMounted(() => labelsStore.fetchLabels());

const showAddGroupPopup = () => (isAddGroupPopupVisible.value = true);
const hideAddGroupPopup = () => (isAddGroupPopupVisible.value = false);

const showEditGroupPopup = (groupId: number) => (groupToEditId.value = groupId);
const hideEditGroupPopup = () => (groupToEditId.value = undefined);

const withStudentCountLabel = (items: Group[]) =>
    items.map((item) => ({
        ...item,
        name:
            item.student_amount != null
                ? `${item.name} (${item.student_amount})`
                : item.name,
    }));

const fetchGroups = () => {
    console.debug('GroupListManager.fetchGroups called with promotionId=', props.promotionId)
    if (!props.promotionId) {
        groups.value = undefined
        return
    }
    groupService
        .getGroups(props.promotionId)
        .then((returnedGroups) => {
            console.debug('GroupListManager: received groups count=', returnedGroups?.length)
            groups.value = withStudentCountLabel(returnedGroups)
        })
        .catch((error) => {
            console.debug('GroupListManager.fetchGroups error', error)
            errorMessage.value = error
        });
};

const handleSelect = (item: number) => {
    emit("select", item);
};

const handleAdd = () => {
    showAddGroupPopup();
};

const handleSuccessfullyAdded = () => {
    hideAddGroupPopup();
    fetchGroups();
    emit("successfullyAdded");
};

const handleSuccessfullyEdited = (group: Group) => {
    hideEditGroupPopup();
    fetchGroups();
    emit("successfullyEdited", group);
};

const handleSuccessfullyDeleted = (id: number) => {
    hideEditGroupPopup();
    fetchGroups();
    emit("successfullyDeleted", id);
};

const resetErrorMessage = () => (errorMessage.value = undefined);
</script>

<template>
    <div>
        <ListManager
            :title="title"
            hasAdd
            :canAdd="!!props.promotionId"
            :items="groups"
            :selectedItemsId="props.selectedGroupId ? [props.selectedGroupId] : undefined"
            @select="handleSelect"
            @edit="showEditGroupPopup"
            @add="handleAdd"
        />
        <AddGroupPopup
            v-if="isAddGroupPopupVisible"
            :promotionId="props.promotionId!"
            @successfullyAdded="handleSuccessfullyAdded"
            @cancel="hideAddGroupPopup"
        />
        <EditGroupPopup
            v-if="groupToEditId"
            :groupId="groupToEditId"
            @successfullyEdited="handleSuccessfullyEdited"
            @successfullyDeleted="handleSuccessfullyDeleted"
            @cancel="hideEditGroupPopup"
        />
        <ErrorPopup
            v-if="errorMessage"
            :message="errorMessage"
            @close="resetErrorMessage"
        />
    </div>
</template>
