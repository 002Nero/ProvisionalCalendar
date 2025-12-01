<script setup lang="ts">
import { computed } from 'vue';
import * as icons from "lucide-vue-next";
import ConstraintIcon from "@/Components/Icons/Constraint.vue";

const props = withDefaults(defineProps<{
  name: string,
  size?: number,
  color?: string,
  strokeWidth?: number
}>(), {
  size: 24,
  color: 'black',
  strokeWidth: 2,
})

const customIcons: Record<string, any> = {
  Constraint: ConstraintIcon,
}

const icon = computed(() => {
  const lucide = (icons as any)[props.name as keyof typeof icons]
  if (lucide) return lucide
  return customIcons[props.name]
})
</script>

<template>
  <component
    v-if="icon"
    :is="icon"
    :size="props.size"
    :color="props.color"
    :strokeWidth="props.strokeWidth"
  />
  <span v-else style="display:inline-block;width:1em;height:1em"></span>
</template>