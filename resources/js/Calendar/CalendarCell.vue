<script setup lang="ts">
import CalendarSlot from "@/Calendar/CalendarSlot.vue";
import { computed, ref } from "vue";
import { SlotType } from "@/types/models/calendars"; //SlotType dans calendars
import { useCalendarStore } from "@/stores/calendar";

const calendarStore = useCalendarStore();

const props = defineProps<{
    groupData: any;
    biggestCM: number;
    biggestTD: number;
    biggestTP: number;
}>();

const isDragOver = ref<boolean>(false);

const getGroupType = (): SlotType | null => {
    // Si le groupe a des contenus, on prend le type du premier contenu
    if (props.groupData.contents && props.groupData.contents.length > 0) {
        console.log("oui"+props.groupData.contents[0].type as SlotType);
        console.log("oui"+props.groupData.contents[0].type );
        console.log("oui"+props.groupData.contents[0].type );
        return props.groupData.contents[0].type as SlotType;
    }
    return null;
};

const getId = () => {
    if (props.groupData.contents && props.groupData.contents.length > 0) {
        const content = props.groupData.contents[0];
        if (content.promotionId) return { type: SlotType.CM, id: content.promotionId };
        if (content.groupId) return { type: SlotType.TD, id: content.groupId };
        if (content.subgroupId) return { type: SlotType.TP, id: content.subgroupId };
    }
    return null;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragOver.value = false;
    
    const data = (e.dataTransfer?.getData("text/plain") || '{}');
    const teacherData = JSON.parse(data); // reconvertit en objet

    console.log('Teacher data:', teacherData);
    const groupInfo = getId();
    console.log('Group info:', groupInfo);

    if (!teacherData.id) {
        alert('Erreur: donnée enseignant invalide');
        return;
    }

    // Vérifier si on est au niveau d'un groupe TD (pas TP/sous-groupe)
    // Au niveau TD, il ne devrait pas y avoir de contenu TD/TP directement dropable
    // Car les TPs se créent au niveau des sous-groupes
    if (groupInfo && groupInfo.type === SlotType.TD) {
        alert('Pour ajouter un TP, veuillez le faire directement sur un sous-groupe (A, B, etc.), pas sur le groupe entier.');
        return;
    }

    if (teacherData.id && groupInfo) {
        console.log("Création du popup avec données valides")
        const popupData = {
            teacherId: teacherData.id,
            type: groupInfo.type,
            [groupInfo.type === SlotType.CM ? 'promotionId' : 
             groupInfo.type === SlotType.TD ? 'groupId' : 'subgroupId']: groupInfo.id
        };
        console.log('Showing popup with data:', popupData);
        calendarStore.showAddCalendarPopup(popupData);
    }
};

const cellWidth = computed(() => {
    const type = getGroupType();
    switch (type) {
        case SlotType.CM:
            return props.biggestCM * 96;
        case SlotType.TD:
            return props.biggestTD * 96;
        case SlotType.TP:
            return props.biggestTP * 96;
        default:
            return 96;
    }
});
</script>

<template>
    <div class="flex w-max h-full">
        <div
            :class="[
                'relative min-w-96 flex items-center justify-start border-r-2 bg-white border-b-2 border-gray-200 after:absolute after:top-0 after:bottom-0 after:right-0 after:left-0',
                { 'after:bg-blue-500 after:opacity-20': isDragOver},
            ]"
            :style="{ width: `${cellWidth}px` }"
            @dragover.prevent="isDragOver = true"
            @dragleave="isDragOver = false"
            @drop="handleDrop"
        >
            <CalendarSlot
                v-for="content in groupData.contents"
                :content="content"
                :contentType="getGroupType()"
            />
        </div>
        <div class="flex flex-col">
            <CalendarCell
                v-for="group in groupData.groups"
                :groupData="group"
                :biggestCM="props.biggestCM"
                :biggestTD="props.biggestTD"
                :biggestTP="props.biggestTP"
            />
        </div>
    </div>
</template>
