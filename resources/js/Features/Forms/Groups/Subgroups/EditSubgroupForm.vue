<script setup lang="ts">
/**
 * A script setup for a component that allows the user to edit a subgroup.
 *
 * It emits a "successfullyEdited" event with the edited subgroup as argument when the
 * edition is successful.
 *
 * It emits a "successfullyDeleted" event with the deleted subgroup as argument when the
 * deletion is successful.
 *
 * It emits an "edited" event when the user has changed the subgroup's name.
 *
 * It uses the yearId and subgroup props to determine the year and subgroup to edit.
 */
import FormInput from "@/Components/FormInput.vue";
import FormButton from "@/Components/FormButton.vue";
import { Subgroup } from "@/types/models/groups";
import { MESSAGES } from "@/constants";
import { ref } from "vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";
import DeleteConfirmationPopup from "@/Features/Popups/DeleteConfirmationPopup.vue";
import { useSubgroupService } from "@/services/groups/subgroupService";
import { FormInputType } from "@/types/models/utils";

const props = defineProps<{ subgroup: Subgroup }>();

const emit = defineEmits([
    "successfullyEdited",
    "successfullyDeleted",
    "edited",
]);

const subgroupService = useSubgroupService();
const editedSubgroup = ref<Subgroup>(props.subgroup);
const nameError = ref<string | undefined>();
const studentCountError = ref<string | undefined>();

if (editedSubgroup.value.student_amount == null) {
    editedSubgroup.value.student_amount = 0;
}

const errorMessage = ref<string | undefined>();

const isDeleteConfirmationPopupVisible = ref<boolean>(false);

const showDeleteConfirmationPopup = () =>
    (isDeleteConfirmationPopupVisible.value = true);
const hideDeleteConfirmationPopup = () =>
    (isDeleteConfirmationPopupVisible.value = false);

const updateName = (value: string) => {
    nameError.value = undefined;
    editedSubgroup.value.name = value;
    emit("edited");
};

const updateStudentCount = (value: string) => {
    const parsed = Number(value);
    if (Number.isNaN(parsed) || parsed < 0) {
        studentCountError.value = "Le nombre d'élèves doit être un entier positif.";
        return;
    }
    studentCountError.value = undefined;
    editedSubgroup.value.student_amount = Math.floor(parsed);
    emit("edited");
};

const showErrorPopup = (error: string) => (errorMessage.value = error);

const resetErrorMessage = () => {
    errorMessage.value = undefined;
};

const handleEdit = () => {
    if (editedSubgroup.value.name.trim() === "") {
        nameError.value = MESSAGES.EMPTY_SUBGROUP_NAME_ERROR_MESSAGE;
        return;
    }
    if (
        editedSubgroup.value.student_amount == null ||
        Number.isNaN(editedSubgroup.value.student_amount) ||
        editedSubgroup.value.student_amount < 0
    ) {
        studentCountError.value = "Le nombre d'élèves doit être un entier positif.";
        return;
    }
    subgroupService
        .updateSubgroup(editedSubgroup.value)
        .then((returnedSubgroup) =>
            emit("successfullyEdited", returnedSubgroup)
        )
        .catch(showErrorPopup);
};

const handleDelete = () => {
    subgroupService
        .deleteSubgroup(props.subgroup.id!)
        .then(() => emit("successfullyDeleted", props.subgroup.id))
        .catch(showErrorPopup);
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <FormInput
            label="Nom du sous-groupe"
            :value="editedSubgroup.name"
            :error="nameError"
            @input="updateName($event.target.value)"
        />
        <FormInput
            label="Nombre d'élèves"
            :value="editedSubgroup.student_amount"
            :error="studentCountError"
            :type="FormInputType.NUMBER"
            @input="updateStudentCount($event.target.value)"
        />
        <div class="flex gap-4 w-full">
            <FormButton
                class="bg-green-500 text-white w-full"
                @click="handleEdit"
                >Sauvegarder</FormButton
            >
            <FormButton
                class="bg-red-500 text-white w-full"
                @click="showDeleteConfirmationPopup"
                >Supprimer</FormButton
            >
        </div>
        <DeleteConfirmationPopup
            v-if="isDeleteConfirmationPopupVisible"
            @delete="handleDelete"
            @cancel="hideDeleteConfirmationPopup"
        />
        <ErrorPopup
            v-if="errorMessage"
            :message="errorMessage"
            @close="resetErrorMessage"
        />
    </div>
</template>
