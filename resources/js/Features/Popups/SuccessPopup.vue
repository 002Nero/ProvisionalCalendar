<script setup lang="ts">
import Popup from '@/Components/Popup/PopupComponent.vue';
import Button from '@/Components/FormButton.vue';

const emit = defineEmits(['close']);

defineProps<{
    message?: string;
}>();
</script>

<template>
    <Popup title="Succès" @close="emit('close')">
        <div class="flex flex-col gap-4">
            <p>{{ message ?? 'Opération effectuée avec succès.' }}</p>
            <div class="flex justify-end">
                <Button class="bg-green-400 text-white" @click="emit('close')">OK</Button>
            </div>
        </div>
    </Popup>
</template>

