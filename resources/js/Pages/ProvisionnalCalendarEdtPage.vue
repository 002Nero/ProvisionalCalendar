<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  edit?: boolean
}>();
import AdminLayout from '@/Layouts/AdminLayout.vue'

const promotions = ref([
  { id: 1, name: 'A1' },
  { id: 2, name: 'A2' },
  { id: 3, name: 'A3' }
])

const groupsByPromotion: Record<number, { id: number; name: string }[]> = {
  1: [
    { id: 1, name: 'G1' },
    { id: 2, name: 'G2' },
    { id: 3, name: 'G3' },
  ],
  2: [
    { id: 4, name: 'G4' },
    { id: 5, name: 'G5' },
    { id: 6, name: 'G6' },
  ],
  3: [
    { id: 7, name: 'G7' },
    { id: 8, name: 'G8' },
  ],
}

const selectedPromotion = ref(promotions.value[0].id)
const groups = ref(groupsByPromotion[selectedPromotion.value] || [])
const selectedGroup = ref<number | null>(groups.value[0]?.id ?? null)

watch(selectedPromotion, (val) => {
  groups.value = groupsByPromotion[val] || []
  selectedGroup.value = groups.value[0]?.id ?? null
})
const currentWeek = ref(1)

const SLOT_START = 7 * 60
const SLOT_END = 19 * 60 + 30
const SLOT_STEP = 30
const timeSlots: number[] = []
for (let t = SLOT_START; t <= SLOT_END; t += SLOT_STEP) {
  timeSlots.push(t)
}

function formatTime(mins: number) {
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function isBlocked(mins: number) {
  return mins >= 12 * 60 && mins < 13 * 60 + 30
}

function prevWeek() {
  if (currentWeek.value > 1) currentWeek.value -= 1
}
function nextWeek() {
  currentWeek.value += 1
}

</script>

<template>
    <AdminLayout>
    <div class="edt-container">
      <header class="edt-toolbar">
        <div class="left">
          <h1 class="title">Emploi du temps (EDT)</h1>
          <div class="controls">
            <label>
              Promotion
              <select v-model="selectedPromotion">
                <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </label>

            <label>
              Groupe
              <select v-model="selectedGroup">
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </label>
          </div>
        </div>

          <div class="right">
          <button class="arrow button" @click="prevWeek"> <</button>
          <span class="week-indicator"> Semaine {{ currentWeek }}</span>
          <button class="arrow button" @click="nextWeek">></button>
          <button class="btn primary">Générer</button>
          <button class="btn primary" @click="$inertia.visit('/calendrier-previsionnel/edt/modifier')">Modifier</button>
          <button class="btn primary">PDF</button>

        </div>
      </header>

      <main class="edt-main">
        <section class="calendar-area">
          <div class="calendar-header">
            <div class="time-header"></div>
            <div class="day" v-for="d in ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi']" :key="d">{{ d }}</div>
          </div>

          <div class="calendar-grid">
            <div class="row" v-for="t in timeSlots" :key="t">
              <div class="cell time">{{ formatTime(t) }}</div>
              <div
                class="cell"
                v-for="d in 6"
                :key="d"
                :class="{ blocked: isBlocked(t) }"
              ></div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </AdminLayout>
</template>

<style scoped>
.edt-container { padding: 1rem; }
.edt-toolbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1rem }
.edt-toolbar .title { margin:0; font-size:1.25rem }
.controls { display:flex; gap:0.75rem; margin-left:1rem; align-items:center }
.controls select { margin-left:0.4rem }
.right { display:flex; gap:0.5rem; align-items:center }
.arrow.button { display: flex; justify-content: center; align-items: center; cursor: pointer; background:#FFD8E4; color: black; filter: brightness(100%); transition: filter 0.3s; padding: 0.5rem; border-radius: 50%; }
.arrow.button:hover { filter: brightness(75%); }
.btn { padding:0.4rem 0.6rem; border:1px solid #d1d5db; background:#fff; border-radius:6px; cursor:pointer }
.btn.primary { background:#FFD8E4; color:#000000; border-color:transparent }
.edt-main { display:flex; gap:1rem }
.filters { width:200px; border:1px solid #e5e7eb; padding:0.75rem; border-radius:6px; }
.calendar-area { flex:1; overflow:auto; max-height: calc(100vh - 220px); }
.calendar-header { display:grid; grid-template-columns: 80px repeat(6,1fr); gap:0.5rem; margin-bottom:0.5rem; position:sticky; top:0; z-index:20; background:transparent }
.calendar-header .time-header { height: 100%; }
.calendar-header .day { text-align:center; padding:0.25rem 0.5rem; background:#f3f4f6; border-radius:4px; display:flex; align-items:center; justify-content:center; }
.calendar-grid { display:grid; gap:0.25rem }
.calendar-grid .row { display:grid; grid-template-columns:80px repeat(6,1fr); align-items:start }
.calendar-grid .cell { min-height:40px; border:1px dashed #e5e7eb; background:#fff; }
.calendar-grid .cell.time { padding:0.12rem 0.2rem; font-size:0.85rem; color:#6b7280; background:transparent; border:none; position:sticky; left:0; z-index:15; transform: translateY(-10px); }

.calendar-grid .cell.blocked {
  background: #ff9d9d;
  border-style: solid;
  border-color: #ff6262;
  opacity: 0.7;
  pointer-events: none;
}
</style>