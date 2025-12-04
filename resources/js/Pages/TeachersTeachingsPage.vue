<script setup lang="ts">
import { ref, onMounted } from "vue";
import axios from "axios";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import TeachersListManager from "../Features/ListManagers/TeachersListManager.vue";
import TeachingsListManager from "../Features/ListManagers/TeachingsListManager.vue";
import { Teacher } from "@/types/models/teachers";
import { EditedItem, EditedItemStatus } from "@/types/models/utils";
import { Teaching } from "@/types/models/teachings";
import Button from "@/Components/FormButton.vue";
import ErrorPopup from "@/Features/Popups/ErrorPopup.vue";
import SaveConfirmationPopup from "@/Features/Popups/SaveConfirmationPopup.vue";
import SuccessPopup from "@/Features/Popups/SuccessPopup.vue";
import { useTeacherService } from "@/services/teacherService";

const teachers = ref<Teacher[]>([]);
const yearId = ref<number>(1);

const teacherService = useTeacherService();

const fetchTeachersForPage = () => {
    teacherService
        .getTeachers(yearId.value)
        .then((response) => {
            teachers.value = response as Teacher[];
        })
        .catch((error) => {
            // conserve l'ancien comportement: afficher une erreur basique dans la console et garder la popup d'erreur côté UI
            console.error('Erreur lors de la récupération des enseignants pour la page :', error);
        });
};

onMounted(() => {
    fetchTeachersForPage();
});

const selectedTeacherIds = ref<number[]>([]);
const selectedTeachingIds = ref<number[]>([]);
const errorMessage = ref("");
const isErrorPopupVisible = ref(false);
const modifications = ref<EditedItem[] | undefined>();
const isSaveConfirmationPopupVisible = ref(false);
const isSuccessPopupVisible = ref(false);
const isLoadingTeacherTeachings = ref<boolean>(false);

const fetchModifications = () => {
    // deleted: teachings currently assigned to the selected teacher but removed from selection
    const deleted = (
        teachers.value.find((teacher) =>
            selectedTeacherIds.value.includes(teacher.id as number)
        )?.teachings ?? []
    )
        .filter((teaching) => !selectedTeachingIds.value.includes(teaching.id as number))
        .map((teaching) => ({
            ...teaching,
            editStatus: EditedItemStatus.DELETED,
        }));

    // added: selectedTeachingIds that are not already present on the selected teacher
    const existingTeachingIds = new Set<number>(
        (teachers.value.find((teacher) =>
            selectedTeacherIds.value.includes(teacher.id as number)
        )?.teachings ?? []).map((t) => t.id as number).filter((id): id is number => id != null)
    );

    const added = selectedTeachingIds.value
        .filter((id) => !existingTeachingIds.has(id))
        .map((id) => ({ id, editStatus: EditedItemStatus.ADDED } as EditedItem));

    modifications.value = [...deleted, ...added];
};

const handleTeacherSelect = async (payload: unknown) => {
    // Normalize payload safely to extract an id
    let selectedItemId: number | null = null;
    if (payload == null) {
        console.log("handleTeacherSelect: payload is null or undefined", payload);
        return;
    }
    if (typeof payload === "number") {
        selectedItemId = payload;
    } else if (typeof payload === "string" && payload.trim() !== "") {
        const n = Number(payload);
        if (!Number.isNaN(n)) selectedItemId = n;
    } else if (typeof payload === "object") {
        const p = payload as Record<string, unknown>;
        if ("id" in p && typeof p.id === "number") {
            selectedItemId = p.id as number;
        } else if ("teacher" in p && typeof p.teacher === "object" && p.teacher !== null) {
            const t = p.teacher as Record<string, unknown>;
            if ("id" in t && typeof t.id === "number") selectedItemId = t.id as number;
        } else if ("value" in p && typeof p.value === "number") {
            selectedItemId = p.value as number;
        }
    }
    console.log("handleTeacherSelect payload:", payload, "-> id:", selectedItemId);
    if (selectedItemId == null) return;

    // If teacher already selected, toggle off
    if (selectedTeacherIds.value.includes(selectedItemId)) {
        // deselect teacher
        selectedTeacherIds.value = selectedTeacherIds.value.filter((id) => id !== selectedItemId);
        selectedTeachingIds.value = [];
        fetchModifications();
        return;
    }

    // If there are unsaved modifications for current selection, ask for confirmation
    if (selectedTeacherIds.value.length && modifications.value?.length) {
        showSaveConfirmationPopup();
        return;
    }

    // Otherwise select the new teacher and load his teachings if not cached
    selectedTeacherIds.value = [selectedItemId];

    const existing = teachers.value.find((t) => t.id === selectedItemId);
    if (!existing || !existing.teachings || existing.teachings.length === 0) {
        // load teachings from API
        isLoadingTeacherTeachings.value = true;
        try {
            const teachings = await teacherService.getTeachingsByTeacher(selectedItemId);
            // cache into teachers array
            if (existing) {
                existing.teachings = teachings as Teaching[];
            } else {
                // push a minimal teacher object with teachings
                teachers.value.push({ id: selectedItemId, name: "", teachings: teachings as Teaching[] } as Teacher);
            }
            selectedTeachingIds.value = (teachings || []).map((t) => t.id as number).filter((id): id is number => id != null);
        } catch (e) {
            errorMessage.value = (e as string) || "Erreur lors de la récupération des enseignements de l'enseignant";
            isErrorPopupVisible.value = true;
            selectedTeachingIds.value = [];
        } finally {
            isLoadingTeacherTeachings.value = false;
        }
    } else {
        selectedTeachingIds.value = (existing.teachings || []).map((t) => t.id as number).filter((id): id is number => id != null);
    }

    // update modifications so UI (Save button) can react immediately
    fetchModifications();
};

const handleTeachingSelect = (payload: number | { id?: number }) => {
    const selectedItemId = typeof payload === "number" ? payload : payload.id;
    if (selectedItemId == null) return;

    if (selectedTeacherIds.value.length) {
        if (selectedTeachingIds.value.includes(selectedItemId)) {
            selectedTeachingIds.value = selectedTeachingIds.value.filter((id) => id !== selectedItemId);
        } else {
            selectedTeachingIds.value = [...selectedTeachingIds.value, selectedItemId];
        }
        // update modifications so Save button updates
        fetchModifications();
    }
};

const showSaveConfirmationPopup = () => {
    fetchModifications();
    isSaveConfirmationPopupVisible.value = true;
};

const getSelectedTeacherId = (): number | null => {
    return selectedTeacherIds.value && selectedTeacherIds.value.length > 0
        ? selectedTeacherIds.value[0]
        : null;
};

const handleSave = async () => {
    fetchModifications();

    const teacherId = getSelectedTeacherId();
    if (!teacherId) {
        errorMessage.value = "Aucun enseignant sélectionné. Veuillez en sélectionner un avant de sauvegarder.";
        isErrorPopupVisible.value = true;
        return;
    }

    if (!modifications.value || modifications.value.length === 0) {
        // rien à faire
        return;
    }

    for (const modification of modifications.value) {
        try {
            if (modification.editStatus === EditedItemStatus.ADDED) {
                await axios.post(`/api/teacher/teaching/${teacherId}/${modification.id}`);
            } else if (modification.editStatus === EditedItemStatus.DELETED) {
                await axios.delete(`/api/teacher/teaching/${teacherId}/${modification.id}`);
            }
        } catch (e) {
            errorMessage.value = `Erreur lors de la mise à jour: ${(e as Error).message}`;
            isErrorPopupVisible.value = true;
            return;
        }
    }

    // après succès, fermer la popup de confirmation
    isSaveConfirmationPopupVisible.value = false;

    // Mettre à jour l'objet teachers en local pour refléter les changements
    const selectedId = teacherId;
    const teacher = teachers.value.find((t) => t.id === selectedId);
    if (teacher) {
        try {
            const refreshed = await teacherService.getTeachingsByTeacher(selectedId);
            teacher.teachings = refreshed as Teaching[];
            // keep selectedTeachingIds in sync with refreshed data
            selectedTeachingIds.value = (refreshed || []).map((t) => t.id as number).filter((id): id is number => id != null);
        } catch (e) {
            // si l'API de rafraîchissement échoue, on conserve l'état local (selectedTeachingIds déjà à jour)
            console.warn('Impossible de rafraîchir les enseignements après sauvegarde', e);
        }
    }

    // vider les modifications
    modifications.value = [];

    // afficher la popup de succès
    isSuccessPopupVisible.value = true;
    // fermer automatiquement après 1.8s
    setTimeout(() => {
        isSuccessPopupVisible.value = false;
    }, 1800);
};

const hideErrorPopup = () => {
    isErrorPopupVisible.value = false;
};

const handleCancel = () => {
    isSaveConfirmationPopupVisible.value = false;
};

const handleQuitWithoutSave = () => {
    selectedTeachingIds.value = (
        teachers.value.find((teacher) => teacher.id === selectedTeacherIds.value[0])
            ?.teachings ?? []
    )
        .map((teaching) => teaching.id as number)
        .filter((id): id is number => id != null);
    isSaveConfirmationPopupVisible.value = false;
    modifications.value = [];
};
</script>

<template>
    <AdminLayout>
        <div class="flex flex-col gap-8 items-end h-full">
            <div class="flex gap-8 flex-1 w-full h-full">
                <TeachersListManager
                    class="h-[700px] w-1/6"
                    :yearId="yearId"
                    :selectedTeacherIds="selectedTeacherIds"
                    @select="handleTeacherSelect"
                />
                <TeachingsListManager
                    class="h-[700px] w-5/6"
                    :yearId="yearId"
                    :selectedTeachingIds="selectedTeachingIds"
                    @select="handleTeachingSelect"
                />
            </div>
            <div class="flex gap-4 h-min">
                <Button class="text-gray-500 underline" @click="handleCancel">
                    Réinitialiser les modifications
                </Button>
                <Button
                     :class="{
                        'bg-green-400 text-white': true,
                        'opacity-50 cursor-not-allowed': !getSelectedTeacherId() || !(modifications && modifications.length > 0)
                    }"
                    @click="handleSave"
                    :disabled="!getSelectedTeacherId() || !(modifications && modifications.length > 0)"
                >
                    Valider
                </Button>

             </div>
         </div>
     </AdminLayout>
     <SaveConfirmationPopup
         v-if="isSaveConfirmationPopupVisible"
         :modifications="modifications"
         @save="handleSave"
         @quitWithoutSave="handleQuitWithoutSave"
         @cancel="handleCancel"
     />
     <ErrorPopup
         :message="errorMessage"
         v-if="isErrorPopupVisible"
         @close="hideErrorPopup"
     />
     <SuccessPopup
         v-if="isSuccessPopupVisible"
         @close="() => { isSuccessPopupVisible = false }"
         :message="'Modifications sauvegardées'"
     />
</template>
