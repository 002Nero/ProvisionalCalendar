<script setup lang="ts">
import ListManager from "@/Components/ListManager/ListManager.vue";
import { Teacher } from "@/types/models/teachers";
import { defineProps, defineEmits, computed, onMounted } from "vue";
import { ref } from "vue";
import { useLabelsStore } from "@/stores/labelsStore";
import { useTeacherService } from "@/services/teacherService";
import AddTeacherPopup from "@/Features/Popups/Teachers/AddTeacherPopup.vue";
import EditTeacherPopup from "@/Features/Popups/Teachers/EditTeacherPopup.vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";

const labelsStore = useLabelsStore();

const props = defineProps<{
    yearId: number;
    selectedTeacherIds: number[];
}>();

const teacherService = useTeacherService();

const teachers = ref<Teacher[] | undefined>();
const errorMessage = ref<string>();
const teacherToEditId = ref<number | undefined>();

const isAddTeacherPopupVisible = ref<boolean>(false);

const showAddTeacherPopup = () => (isAddTeacherPopupVisible.value = true);
const hideAddTeacherPopup = () => (isAddTeacherPopupVisible.value = false);

const showEditTeacherPopup = (id: number) => (teacherToEditId.value = id);
const hideEditTeacherPopup = () => (teacherToEditId.value = undefined);

const emit = defineEmits([
    "select",
    "successfullyAdded",
    "successfullyEdited",
    "successfullyDeleted",
]);

const fetchTeachers = () => {
    console.log("fetchTeachers");
    teacherService
        .getTeachers(props.yearId)
        .then((response) => (teachers.value = response))
        .catch((error) => (errorMessage.value = error));
};

onMounted(() => {
    console.log("test");
    fetchTeachers();
});

const title = computed(() => {
    return labelsStore.getLabel("Enseignants");
});

const handleSelect = (payload: unknown) => {
    // accept either a Teacher object or a numeric id
    let id: number | null = null;
    if (payload == null) return;
    if (typeof payload === 'number') id = payload;
    else if (typeof payload === 'object') {
        const p = payload as any;
        if (p.id != null) id = p.id;
        else if (p.teacher && p.teacher.id != null) id = p.teacher.id;
        else if (p.value != null) id = p.value;
    }
    // eslint-disable-next-line no-console
    console.log('TeachersListManager handleSelect payload -> id', payload, id);
    if (id != null) emit('select', id);
};

const handleSuccessfullyAdded = (teacher: Teacher) => {
    hideAddTeacherPopup();
    emit("successfullyAdded", teacher);
    fetchTeachers();
};

const handleSuccessfullyEdited = (teacher: Teacher) => {
    hideEditTeacherPopup();
    emit("successfullyEdited", teacher);
    fetchTeachers();
};

const handleSuccessfullyDeleted = (teacher: Teacher) => {
    hideEditTeacherPopup();
    emit("successfullyDeleted", teacher);
    fetchTeachers();
};

const resetErrorMessage = () => (errorMessage.value = undefined);
</script>

<template>
    <ListManager
        :title="title"
        hasAdd
        canAdd
        :items="teachers"
        :selectedItemsId="selectedTeacherIds"
        @select="handleSelect"
        @add="showAddTeacherPopup"
        @edit="showEditTeacherPopup"
    />
    <AddTeacherPopup
        v-if="isAddTeacherPopupVisible"
        :teacherId="undefined"
        :yearId
        @successfullyAdded="handleSuccessfullyAdded"
        @cancel="hideAddTeacherPopup"
    />
    <EditTeacherPopup
        v-if="teacherToEditId != undefined"
        :teacherId="teacherToEditId"
        :yearId
        @successfullyEdited="handleSuccessfullyEdited"
        @successfullyDeleted="handleSuccessfullyDeleted"
        @cancel="hideEditTeacherPopup"
    />

    <ErrorPopup
        v-if="errorMessage"
        :message="errorMessage!"
        @close="resetErrorMessage"
    />
</template>

<!-- <script setup lang="ts">
import ListManager from "@/Components/ListManager/ListManager.vue";
import { Teaching } from "@/types/models/teachings";
import { defineProps, computed, onMounted } from "vue";
import AddTeachingPopup from "@/Features/Popups/Teachings/AddTeachingPopup.vue";
import EditTeachingPopup from "@/Features/Popups/Teachings/EditTeachingPopup.vue";
import { ref } from "vue";
import { useLabelsStore } from "@/stores/labelsStore";
import { useTeachingService } from "@/services/teachingService";
import { Period } from "@/types/models/periods";
import ErrorPopup from "../Popups/ErrorPopup.vue";
import { usePeriodService } from "@/services/periodsService";

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
const periodsService = usePeriodService();

const teachings = ref<Teaching[] | undefined>();
const periods = ref<Period[] | undefined>();

const isAddTeachingPopupVisible = ref<boolean>(false);

const teachingToEditId = ref<number | undefined>();

const errorMessage = ref<string>();

const fetchTeachings = () => {
    teachingService
        .getTeachings(props.yearId, periods.value!)
        .then((response) => (teachings.value = response))
        .catch((error) => (errorMessage.value = error));
};

const fetchPeriodsAndTeachings = () => {
    periodsService
        .getPeriods(props.yearId)
        .then((response) => (periods.value = response))
        .then(fetchTeachings)
        .catch((error) => (errorMessage.value = error));
};

onMounted(() => {
    fetchPeriodsAndTeachings();
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
            :periods
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
</template> -->
