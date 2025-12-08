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

// lessons loaded from edt_slot API
type RawRow = Record<string, unknown>
interface Lesson { 
  id: number | null
  day: number
  start_min: number
  duration_min: number
  span: number  // number of 30-min slots
  title: string
  teacher: string
  room: string
  color?: string | null
  raw: RawRow
}
const lessons = ref<Lesson[]>([])

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
  if (yearId) {
    // persist chosen year in store so other pages/components can use it
    edtStore.setYear(yearId)
    await loadPromotionsForYear(yearId)
  }
  if (selectedPromotion.value) await loadGroupsForPromotion(selectedPromotion.value)
  // DON'T call loadEdtSlotsForCurrent here - will be called from onMounted after this completes
}

onMounted(async () => { 
  // sync week from store if already set
  if (typeof edtStore.week === 'number' && edtStore.week > 0) {
    currentWeek.value = edtStore.week
  }
  await ensureDataLoaded()
  // After promotions/groups are loaded, load EDT slots with filters
  if (edtStore.year && currentWeek.value) {
    await loadEdtSlotsForCurrent()
  }
})

// load edt_slot lessons for current year/week
async function loadEdtSlotsForCurrent() {
  let yearId: number | null = null
  if (typeof edtStore.year === 'number') yearId = edtStore.year
  else if (typeof edtStore.year === 'string' && /^[0-9]+$/.test(edtStore.year)) yearId = parseInt(edtStore.year as string, 10)
  if (!yearId) return
  
  const weekNumber = currentWeek.value
  edtStore.setWeek(weekNumber)
  
  try {
    // Build URL with filters
    const params = new URLSearchParams()
    if (selectedPromotion.value) params.append('promotion_id', String(selectedPromotion.value))
    if (selectedGroup.value) params.append('group_id', String(selectedGroup.value))
    if (selectedSubgroup.value) params.append('subgroup', selectedSubgroup.value)
    const url = `/api/edt/${yearId}/${weekNumber}${params.toString() ? '?' + params.toString() : ''}`
    lastFetch.value.url = url
    lastFetch.value.status = null
    lastFetch.value.error = null
    lastFetch.value.response = null
    console.debug('[EDT] fetching edt slots', { url, yearId, weekNumber, filters: { promotion: selectedPromotion.value, group: selectedGroup.value, subgroup: selectedSubgroup.value } })
    const res = await axios.get(url)
    lastFetch.value.status = res.status ?? null
    lastFetch.value.response = res.data ?? null
    const arr = Array.isArray(res.data) ? res.data as RawRow[] : []
    console.debug('[EDT] fetched', arr.length, 'rows', arr)
    lessons.value = arr.map((r: RawRow) => {
      // Map exactly like editor does
      const dayRaw = r.day_of_week ?? r.day
      const day = parseDay(dayRaw)
      
      // start_hour is HH:MM string
      const startRaw = r.start_hour ?? r.start_time
      let startMin = 0
      if (typeof startRaw === 'string' && /^\d{1,2}:\d{2}/.test(startRaw)) {
        const parts = startRaw.split(':')
        startMin = Number(parts[0]) * 60 + Number(parts[1])
      }
      
      // duration is in hours (decimal), convert to minutes
      const durationHours = Number(r.duration ?? 0)
      const durationMin = Math.round(durationHours * 60)
      const span = Math.max(1, Math.ceil(durationMin / SLOT_STEP))
      
      // Course title with Apogee code (no label, just code)
      const teachingCode = r.teaching_code ?? ''
      const title = teachingCode || `Enseignement ${r.teaching_id ?? ''}`
      
      // Teacher - display full name if available, otherwise acronym, otherwise empty
      const teacher = r.teacher_name ?? r.teacher_code ?? ''
      
      // room_name or room_id
      const room = r.room_name ?? (r.room_id ? `Salle ${r.room_id}` : '')
      
      // prefer backend-provided slot type color; otherwise fallback per acronym
      const typeAcr = (r.type_acronym ?? r.teaching_type ?? r.type ?? '').toString().toUpperCase()
      const fallbackColors: Record<string,string> = { CM: '#fde74c', TD: '#fddd2d', TP: '#809bce', SAE: '#20bf55', EX: '#a26769' }
      const color = (r as { type_color?: string }).type_color || fallbackColors[typeAcr] || '#fef3c7'

      return {
        id: Number(r.id ?? 0),
        day: day as number,
        start_min: startMin,
        duration_min: durationMin,
        span,
        title,
        teacher,
        room,
        color,
        raw: r,
      } as Lesson
    }).filter((l) => l.day && l.start_min != null && l.duration_min > 0)
  } catch (e) {
      console.warn('Could not load edt slots', e)
      try {
        const err = e as unknown as { response?: { status?: number; data?: unknown } }
        if (err.response) {
          lastFetch.value.status = err.response.status ?? null
          lastFetch.value.response = err.response.data ?? null
          lastFetch.value.error = typeof err.response.data === 'string' ? err.response.data : JSON.stringify(err.response.data)
        } else {
          lastFetch.value.error = String(e)
        }
      } catch {
        lastFetch.value.error = String(e)
      }
    lessons.value = []
  }
}

// reload when week, year, or filters change
watch(currentWeek, () => { void loadEdtSlotsForCurrent() })
watch(() => edtStore.year, async () => { 
  await ensureDataLoaded()
  void loadEdtSlotsForCurrent()
})
watch(selectedPromotion, async (val) => { 
  edtStore.setPromotion(val)
  await loadGroupsForPromotion(val)
  void loadEdtSlotsForCurrent()
})
watch(selectedGroup, (val) => { 
  edtStore.setGroup(val)
  void loadEdtSlotsForCurrent()
})
watch(selectedSubgroup, (val) => { 
  edtStore.setSubgroup(val)
  void loadEdtSlotsForCurrent()
})

// debug state for diagnostics
const lastFetch = ref<{ url: string | null; status: number | null; error: string | null; response: unknown | null }>({ url: null, status: null, error: null, response: null })

// helper to get lessons that start at a specific day and minute (same as editor)
function lessonsStartingAt(dayIndex: number, minute: number) {
  return lessons.value.filter(l => l.day === dayIndex && l.start_min === minute)
}

// check if cell is covered by a lesson (for graying out covered slots)
function isCovered(dayIndex: number, minute: number) {
  return lessons.value.some(l => l.day === dayIndex && l.start_min <= minute && minute < l.start_min + l.span * SLOT_STEP)
}

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

// parse various day representations (numbers, French names, English names)
function parseDay(val: unknown): number | null {
  if (val == null) return null
  if (typeof val === 'number' && Number.isFinite(val)) {
    const n = Number(val)
    if (n >= 1 && n <= 6) return n
    // sometimes day is 0-6 (0=Sunday) — convert Monday..Saturday to 1..6
    if (n === 0) return null
    if (n >= 1 && n <= 7) return n <= 6 ? n : null
  }
  if (typeof val === 'string') {
    const s = val.trim().toLowerCase()
    // numeric string
    if (/^[0-9]+$/.test(s)) {
      const n = parseInt(s, 10)
      if (n >= 1 && n <= 6) return n
    }
    // remove accents for french matching
    const accentsMap: Record<string, string> = { 'à':'a','â':'a','ä':'a','é':'e','è':'e','ê':'e','ë':'e','î':'i','ï':'i','ô':'o','ö':'o','ù':'u','û':'u','ü':'u','ç':'c' }
    const normalized = s.replace(/[^a-z0-9]/g, ch => accentsMap[ch] ?? '')
    const m: Record<string, number> = {
      'lundi': 1, 'lun': 1, 'mardi': 2, 'mar': 2, 'mercredi': 3, 'mer': 3,
      'jeudi': 4, 'jeu': 4, 'vendredi': 5, 'ven': 5, 'samedi': 6, 'sam': 6,
      'monday': 1, 'tuesday': 2, 'wednesday': 3, 'thursday': 4, 'friday': 5, 'saturday': 6,
      'mon': 1, 'tue': 2, 'wed': 3, 'thu': 4, 'fri': 5, 'sat': 6
    }
    // try full match then prefix match
    if (normalized in m) return m[normalized]
    for (const k in m) {
      if (normalized.startsWith(k)) return m[k]
    }
  }
  return null
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
              >
                <div
                  v-for="lesson in lessonsStartingAt(d, t)"
                  :key="lesson.id || lesson.start_min + '-' + lesson.room"
                  class="lesson-block"
                  :style="{ height: `${lesson.span * 40 + (lesson.span - 1) * 4}px`, background: lesson.color || 'linear-gradient(180deg,#fef3c7,#fde68a)', borderColor: lesson.color || '#f59e0b' }"
                >
                  <div class="lesson-title">{{ lesson.title }}</div>
                  <div class="lesson-meta">{{ lesson.teacher }} · {{ lesson.room }}</div>
                </div>
                <div v-if="isCovered(d, t) && lessonsStartingAt(d, t).length === 0" class="covered-slot"></div>
              </div>
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
.calendar-grid .cell { height:40px; border:1px dashed #e5e7eb; background:#fff; position:relative; overflow:visible; }
.calendar-grid .cell.time { padding:0.12rem 0.2rem; font-size:0.85rem; color:#6b7280; background:transparent; border:none; position:sticky; left:0; z-index:15; }

.calendar-grid .cell.blocked {
  background: #ff9d9d;
  border-style: solid;
  border-color: #ff6262;
  opacity: 0.7;
  pointer-events: none;
}
.lesson-block {
  box-sizing: border-box;
  padding: 0.25rem;
  margin: 2px;
  background: linear-gradient(180deg,#fef3c7,#fde68a);
  border-radius: 6px;
  border: 1px solid #f59e0b;
  font-size: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  overflow: hidden;
  position: absolute;
  width: calc(100% - 4px);
  z-index: 5;
}
.lesson-title { font-weight:600; color:#92400e }
.lesson-meta { font-size:0.75rem; color:#7c2d12 }
.covered-slot {
  position: absolute;
  inset: 0;
  background: rgba(251, 191, 36, 0.1);
  pointer-events: none;
}
</style>