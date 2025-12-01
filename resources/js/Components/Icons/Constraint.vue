<script setup lang="ts">
defineProps<{
  size?: number;
  color?: string;
  strokeWidth?: number;
}>();
</script>

<template>
  <svg :width="(size ?? 24)" :height="(size ?? 24)" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" :aria-hidden="true">
    <path d="M4 6h6" :stroke="color || 'currentColor'" :stroke-width="strokeWidth || 2" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="18" cy="6" r="1.5" :fill="color || 'currentColor'" />

    <path d="M4 12h10" :stroke="color || 'currentColor'" :stroke-width="strokeWidth || 2" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="12" cy="12" r="1.5" :fill="color || 'currentColor'" />

    <path d="M4 18h4" :stroke="color || 'currentColor'" :stroke-width="strokeWidth || 2" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="10" cy="18" r="1.5" :fill="color || 'currentColor'" />
  </svg>
</template>

<style scoped>
svg { display:block }
</style>
