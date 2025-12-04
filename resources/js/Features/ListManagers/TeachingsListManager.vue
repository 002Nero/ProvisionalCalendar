<script setup lang="ts">
import ListManager from "@/Components/ListManager/ListManager.vue";
import { Teaching } from "@/types/models/teachings";
import { defineProps, computed, onMounted } from "vue";
import AddTeachingPopup from "@/Features/Popups/Teachings/AddTeachingPopup.vue";
import EditTeachingPopup from "@/Features/Popups/Teachings/EditTeachingPopup.vue";
import { ref } from "vue";
import { useLabelsStore } from "@/stores/labelsStore";
import { useTeachingService } from "@/services/teachingService";
import ErrorPopup from "../Popups/ErrorPopup.vue";

const labelsStore = useLabelsStore();

const props = defineProps<{
    yearId: number;
    selectedTeachingIds: number[];
}>();

const title = computed(() => {
    return labelsStore.getLabel("Enseignements");
});

const emit = defineEmits([
    "select",
    "successfullyAdded",
    "successfullyEdited",
    "successfullyDeleted",
]);

const teachingService = useTeachingService();
const teachings = ref<Teaching[] | undefined>();

const isAddTeachingPopupVisible = ref<boolean>(false);

const teachingToEditId = ref<number | undefined>();

const errorMessage = ref<string>();

const fetchTeachings = () => {
    teachingService
        .getTeachings(props.yearId)
        .then((response) => (teachings.value = response))
        .catch((error) => (errorMessage.value = error));
};


onMounted(() => {
    fetchTeachings();
});

const showAddTeachingPopup = () => (isAddTeachingPopupVisible.value = true);
const hideAddTeachingPopup = () => (isAddTeachingPopupVisible.value = false);

const showEditTeachingPopup = (teachingId: number) =>
    (teachingToEditId.value = teachingId);
const hideEditTeachingPopup = () => (teachingToEditId.value = undefined);

const handleEdit = (id: number) => {
    showEditTeachingPopup(id);
};

const handleSelect = (teaching: Teaching) => {
    emit("select", teaching);
};

const handleSuccessfullyAdded = (teaching: Teaching) => {
    fetchTeachings();
    hideAddTeachingPopup();
    emit("successfullyAdded", teaching);
};

const handleSuccessfullyEdited = (teaching: Teaching) => {
    fetchTeachings();
    hideEditTeachingPopup();
    emit("successfullyEdited", teaching);
};

const handleSuccessfullyDeleted = (id: number) => {
    fetchTeachings();
    hideEditTeachingPopup();
    emit("successfullyDeleted", id);
};

const resetErrorMessage = () => (errorMessage.value = undefined);
</script>

<template>
    <div class="teachings-list-manager h-full w-full">
        <ListManager
            :title="title"
            hasAdd
            hasImport
            canAdd
            :items="teachings"
            :selectedItemsId="selectedTeachingIds"
            @add="showAddTeachingPopup"
            @edit="handleEdit"
            @select="handleSelect"
        />

        <AddTeachingPopup
            v-if="isAddTeachingPopupVisible"
            :teachingId="undefined"
            :yearId
            @successfullyAdded="handleSuccessfullyAdded"
            @cancel="hideAddTeachingPopup"
        />
        <EditTeachingPopup
            v-if="teachingToEditId"
            :teachingId="teachingToEditId"
            :yearId
            @successfullyEdited="handleSuccessfullyEdited"
            @successfullyDeleted="handleSuccessfullyDeleted"
            @cancel="hideEditTeachingPopup"
        />

        <ErrorPopup
            v-if="errorMessage"
            :message="errorMessage!"
            @close="resetErrorMessage"
        />
    </div>
</template>
