<script setup lang="ts">
import CloseWithoutSaveConfirmationPopup from "@/Components/CloseWithoutSaveConfirmationPopup.vue";
import { onMounted, ref } from "vue";
import Popup from "@/Components/Popup/PopupComponent.vue";
import { User } from "@/types/models/teachers";
import { useUserService } from "@/services/userService";
import EditTeacherForm from "@/Features/Forms/Teachers/EditTeacherForm.vue";
import { Teacher } from "@/types/models/teachers";
import { useTeacherService } from "@/services/teacherService";

const props = defineProps<{ yearId: number; teacherId: number }>();

const emit = defineEmits([
    "cancel",
    "successfullyEdited",
]);

const userService = useUserService();
const teacherService = useTeacherService();

const isCloseWithoutSaveConfirmationPopupVisible = ref(false);
const hasBeenEdited = ref<boolean>(false);
const teacher = ref<Teacher | undefined>();
const users = ref<User[] | undefined>();

onMounted(async () => {
    await fetchUsers();
    console.log(teacher.value);
});

const fetchUsers = async () => {
    await userService
        .getUsers()
        .then((res) => {
            users.value = res;
            return res;
        })
        .then(fetchTeacher);
};

const fetchTeacher = async (users: User[]) => {
    await teacherService
        .getTeacher(props.teacherId, users)
        .then((res) => (teacher.value = res));
};

const showCloseWithoutSaveConfirmationPopup = () => {
    isCloseWithoutSaveConfirmationPopupVisible.value = true;
};

const hideCloseWithoutSaveConfirmationPopup = () => {
    isCloseWithoutSaveConfirmationPopupVisible.value = false;
};

const handleCancel = () =>
    hasBeenEdited.value
        ? showCloseWithoutSaveConfirmationPopup()
        : emit("cancel");

const handleCloseWithoutSaving = () => {
    hideCloseWithoutSaveConfirmationPopup();
    emit("cancel");
};

const handleHasBeenEdited = () => {
    hasBeenEdited.value = true;
};
</script>

<template>
    <Popup title="Modifier un enseignement" @close="handleCancel">
        <EditTeacherForm
            v-if="teacher && users"
            :users
            :teacher
            @successfullyEdited="$emit('successfullyEdited')"
            @edited="handleHasBeenEdited"
        />
        <div v-else class="w-full flex justify-center">
            <div>Chargement...</div>
        </div>
    </Popup>
    <CloseWithoutSaveConfirmationPopup
        v-if="isCloseWithoutSaveConfirmationPopupVisible"
        @close="handleCloseWithoutSaving"
        @cancel="hideCloseWithoutSaveConfirmationPopup"
    />
</template>
