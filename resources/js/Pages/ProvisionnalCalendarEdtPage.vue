<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { useEdtStore } from '@/stores/edtStore'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import IconButton from '@/Components/IconButton.vue'

// promotions/groups loaded from API (dependent on year)
const promotions = ref<{ id: number; name: string }[]>([])
const groups = ref<{ id: number; name: string }[]>([])

const selectedPromotion = ref<number | null>(null)
const selectedGroup = ref<number | null>(null)
const selectedSubgroup = ref<string>('A')

const currentWeek = ref(1)

const edtStore = useEdtStore()

async function loadPromotionsForYear(yearId: number) {
  try {
    const res = await axios.get(`/api/promotions/${yearId}`)
    promotions.value = Array.isArray(res.data) ? res.data : []
    if (promotions.value.length > 0) {
      // preserve previously selected promotion from store if compatible
      if (edtStore.promotionId && promotions.value.find(p => p.id === edtStore.promotionId)) {
        selectedPromotion.value = edtStore.promotionId
      } else {
        selectedPromotion.value = promotions.value[0].id
      }
    } else {
      selectedPromotion.value = null
      groups.value = []
      selectedGroup.value = null
    }
  } catch (e) {
    console.warn('Could not load promotions', e)
  }
}

async function loadGroupsForPromotion(promoId: number | null) {
  if (!promoId) {
    groups.value = []
    selectedGroup.value = null
    return
  }
  try {
    const res = await axios.get(`/api/groups/${promoId}`)
    groups.value = Array.isArray(res.data) ? res.data : []
    if (groups.value.length > 0) {
      if (edtStore.groupId && groups.value.find(g => g.id === edtStore.groupId)) {
        selectedGroup.value = edtStore.groupId
      } else {
        selectedGroup.value = groups.value[0].id
      }
    } else {
      selectedGroup.value = null
    }
  } catch (e) {
    console.warn('Could not load groups', e)
  }
}

// Resolve year then load promotions/groups (mirrors other pages' logic)
async function ensureDataLoaded() {
  let yearId: number | null = null
  if (typeof edtStore.year === 'number') yearId = edtStore.year
  else if (typeof edtStore.year === 'string' && /^[0-9]+$/.test(edtStore.year)) yearId = parseInt(edtStore.year as string, 10)
  if (!yearId) {
    try {
      const yrs = await axios.get('/api/years')
      const arr = Array.isArray(yrs.data) ? yrs.data : []
      if (arr.length > 0) yearId = arr[0].id
    } catch (e) {
      console.warn('Could not resolve year', e)
    }
  }

  if (yearId) await loadPromotionsForYear(yearId)
  if (selectedPromotion.value) await loadGroupsForPromotion(selectedPromotion.value)
}

onMounted(() => { ensureDataLoaded() })

// keep store in sync
watch(selectedPromotion, (val) => {
  edtStore.setPromotion(val)
  void loadGroupsForPromotion(val)
})
watch(selectedGroup, (val) => edtStore.setGroup(val))
watch(selectedSubgroup, (val) => edtStore.setSubgroup(val))
watch(currentWeek, (val) => edtStore.setWeek(val))

// initialize store with current values (if any)
edtStore.setPromotion(selectedPromotion.value)
edtStore.setGroup(selectedGroup.value)
edtStore.setWeek(currentWeek.value)
edtStore.setSubgroup(selectedSubgroup.value)

const SLOT_START = 8 * 60
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
            <label style="display:flex; align-items:center; gap:0.4rem;">
              Sous-groupe
              <select v-model="selectedSubgroup" style="width:64px;">
                <option value="A">A</option>
                <option value="B">B</option>
              </select>
            </label>
          </div>
        </div>

          <div class="right">
          <IconButton iconClass="ChevronLeft" bgColor="#FFD8E4" small @click="prevWeek" />
          <span class="week-indicator"> Semaine {{ currentWeek }}</span>
          <IconButton iconClass="ChevronRight" bgColor="#FFD8E4" small @click="nextWeek" />
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