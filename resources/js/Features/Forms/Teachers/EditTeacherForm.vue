<script setup lang="ts">
import FormInput from "@/Components/FormInput.vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";
import { FormInputType } from "@/types/models/utils";
import FormButton from "@/Components/FormButton.vue";
import { ref, computed } from "vue";
import DeleteConfirmationPopup from "@/Features/Popups/DeleteConfirmationPopup.vue";
import { Teacher } from "@/types/models/teachers";
import { User } from "@/types/models/teachers";
import { useTeacherService } from "@/services/teacherService";

const props = defineProps<{
    teacher: Teacher;
    users: User[];
}>();

const emit = defineEmits([
    "successfullyEdited",
    "edited",
]);

const teacherService = useTeacherService();

const editedTeacher = ref<Teacher>(props.teacher);

const codeError = ref<string | undefined>();
const userError = ref<string | undefined>();

const isDeleteConfirmationPopupVisible = ref<boolean>(false);

const showDeleteConfirmationPopup = () =>
    (isDeleteConfirmationPopupVisible.value = true);
const hideDeleteConfirmationPopup = () =>
    (isDeleteConfirmationPopupVisible.value = false);

const userOptions = computed(() => {
    return props.users?.map((u) => ({ label: u.username, value: u.id })) || [];
});

const errorMessage = ref<string | undefined>();

const updateCode = (value: string) => {
    codeError.value = undefined;
    editedTeacher.value.code = value;
    emit("edited");
};

const updateUser = (value: string) => {
    userError.value = undefined;
    editedTeacher.value.user = props.users?.find(
        (u) => u.id === parseInt(value)
    );
    emit("edited");
};

const handleEdit = async () => {
    teacherService
        .updateTeacher(editedTeacher.value)
        .then(() => emit("successfullyEdited", editedTeacher.value))
        .catch((error) => (errorMessage.value = error));
};


const resetErrorMessage = () => (errorMessage.value = undefined);
</script>

<template>
    <div class="flex flex-col gap-6">
        <FormInput
            label="Initiaux"
            placeholder="ex : CO"
            :value="teacher?.code"
            :error="codeError"
            @input="updateCode($event.target.value)"
        />
        <FormInput
            label="Utilisateur"
            :type="FormInputType.SELECT"
            :options="userOptions"
            :value="teacher?.user?.id"
            placeholder="ex : TIN13A1S"
            :error="userError"
            @input="updateUser($event.target.value)"
        />
        <div class="flex gap-4 w-full">
            <FormButton
                class="bg-green-500 text-white w-full"
                @click="handleEdit"
                >Sauvegarder</FormButton
            >
        </div>
        <ErrorPopup
            v-if="errorMessage"
            :message="errorMessage!"
            @close="resetErrorMessage"
        />
    </div>
</template>
