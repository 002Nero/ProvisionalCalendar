<script setup lang="ts">
import SelectionnableButton from "./SelectionnableButton.vue";
import IconButton from "@/Components/IconButton.vue";
import { Item } from "@/types/models/utils";

const props = defineProps<{
    item: Item;
    selected?: boolean;
}>();

const emit = defineEmits(["select", "edit"]);

const handleEdit = () => {
    emit("edit", props.item.id);
};

const handleSelect = () => {
    console.log("select");
    emit("select", props.item.id);
};

const handleDrag = (event: DragEvent) => {
    //console.log("test")
    console.log("testHandle ?", props.item.id)
    if (event.dataTransfer) {
        event.dataTransfer.setData("text/plain", JSON.stringify(props.item));
        event.dataTransfer.dropEffect = "move";
    }
};
</script>

<template>
    <div class="selectionnable-button-editable flex items-center gap-2">
        <SelectionnableButton
            class="w-full"
            :name="item.name"
            :selected
            @click="handleSelect"
            @dragstart="handleDrag"
        />
        <IconButton
            iconClass="Pencil"
            bgColor="#E8DEF8"
            @click="handleEdit"
            hasShadow
        />
    </div>
</template>
