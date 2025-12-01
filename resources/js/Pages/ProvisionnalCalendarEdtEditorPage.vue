<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import IconButton from '@/Components/IconButton.vue'
import { useEdtStore } from '@/stores/edtStore'

const edtStore = useEdtStore()

const title = computed(() => {
  const wk = edtStore.week ?? '-'
  const promo = edtStore.promotionId ? `Promo A${edtStore.promotionId}` : '-'
  const grp = edtStore.groupId ? `G${edtStore.groupId}` : '-'
  const yr = edtStore.year ?? '-'
  return `Modification Emploi du temps — Semaine ${wk}, ${promo} - ${grp} Année ${yr}`
})

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

type Course = { id: number; code: string; title: string; duration: number; semester: number; room?: string; teacher?: string; editing?: boolean }
const courses = ref<Course[]>([
  { id: 1, code: 'MATH101', title: 'Algèbre linéaire', duration: 90, semester: 1, room: 'B101', teacher: 'Dr. Laurent', editing: false },
  { id: 2, code: 'INFO202', title: 'Programmation JS', duration: 120, semester: 2, room: 'T203', teacher: 'Mme. Dupont', editing: false },
  { id: 3, code: 'PHYS150', title: 'Physique générale', duration: 60, semester: 2, room: 'L12', teacher: 'M. Martin', editing: false },
  { id: 4, code: 'ENG201', title: 'Anglais technique', duration: 60, semester: 3, room: 'B201', teacher: 'Ms. Smith', editing: false },
])

const searchQuery = ref('')
const promotions = ref([
  { id: 1, name: 'A1' },
  { id: 2, name: 'A2' },
  { id: 3, name: 'A3' },
])

const selectedPromotionFilter = ref<number>(edtStore.promotionId ?? promotions.value[0].id)

const selectedSemesterFilter = ref<number | 'all'>('all')
const semesters = [1,2,3,4,5,6]

const semestersByPromotion: Record<number, number[]> = {
  1: [1,2],
  2: [3,4],
  3: [5,6],
}

const availableSemesters = computed(() => semestersByPromotion[selectedPromotionFilter.value] || [])

watch(selectedPromotionFilter, (val) => {
  if (selectedSemesterFilter.value !== 'all' && !availableSemesters.value.includes(selectedSemesterFilter.value as number)) {
    selectedSemesterFilter.value = 'all'
  }
})

const filteredCourses = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return courses.value.filter(c => {
    if (selectedSemesterFilter.value !== 'all' && c.semester !== selectedSemesterFilter.value) return false
    const allowed = semestersByPromotion[selectedPromotionFilter.value] || semesters
    if (!allowed.includes(c.semester)) return false
    if (!q) return true
    return c.title.toLowerCase().includes(q) || c.code.toLowerCase().includes(q)
  })
})

const rooms = ref(['B101','B201','T203','L12','Amphi A'])
const teachers = ref(['Dr. Laurent','Mme. Dupont','M. Martin','Ms. Smith','Dr. Perez'])

type Placement = { id: number; courseId: number; day: number; time: number; span: number; duration: number }
const placements = ref<Placement[]>([])
let nextPlacementId = 1

const currentDrop = ref<{ day: number | null; time: number | null }>({ day: null, time: null })

function onCourseDragStart(e: DragEvent, courseId: number) {
  e.dataTransfer?.setData('text/course-id', String(courseId))
}

function onPlacementDragStart(e: DragEvent, placementId: number) {
  e.dataTransfer?.setData('text/placement-id', String(placementId))
  // allow move
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onCellDragOver(e: DragEvent) {
  e.preventDefault()
}

function onCellDragEnter(e: DragEvent, day: number, time: number) {
  e.preventDefault()
  currentDrop.value = { day, time }
}

function onCellDragLeave() {
  currentDrop.value = { day: null, time: null }
}

function durationSlotsForCourse(courseId: number) {
  const c = courses.value.find(x => x.id === courseId)
  if (!c) return 1
  return Math.max(1, Math.ceil(c.duration / SLOT_STEP))
}

function onCellDrop(e: DragEvent, day: number, time: number) {
  e.preventDefault()
  const placementIdStr = e.dataTransfer?.getData('text/placement-id')
  if (placementIdStr) {
    const placementId = Number(placementIdStr)
    const idx = placements.value.findIndex(p => p.id === placementId)
    if (idx === -1) return
    const old = placements.value.splice(idx, 1)[0]
    const span = old.span
    for (let i = 0; i < span; i++) {
      const t = time + i * SLOT_STEP
      if (placements.value.some(p => p.day === day && p.time <= t && t < p.time + p.span * SLOT_STEP)) {
        placements.value.splice(idx, 0, old)
        alert('Chevauchement lors du déplacement — emplacement inchangé')
        currentDrop.value = { day: null, time: null }
        return
      }
    }
    old.day = day
    old.time = time
    placements.value.push(old)
    currentDrop.value = { day: null, time: null }
    return
  }

  const idStr = e.dataTransfer?.getData('text/course-id')
  if (!idStr) return
  const courseId = Number(idStr)
  const span = durationSlotsForCourse(courseId)
  for (let i = 0; i < span; i++) {
    const t = time + i * SLOT_STEP
    if (placements.value.some(p => p.day === day && p.time <= t && t < p.time + p.span * SLOT_STEP)) {
      alert('Chevauchement avec un cours existant — choisissez un autre créneau')
      currentDrop.value = { day: null, time: null }
      return
    }
  }

  const course = courses.value.find(x => x.id === courseId)
  const duration = course?.duration ?? span * SLOT_STEP
  placements.value.push({ id: nextPlacementId++, courseId, day, time, span, duration })
  currentDrop.value = { day: null, time: null }
}

function placementsFor(day: number, time: number) {
  return placements.value.filter(p => p.day === day && p.time === time)
}

function placementsStartingAt(day: number, time: number) {
  return placements.value.filter(p => p.day === day && p.time === time)
}

function isCovered(day: number, time: number) {
  return placements.value.some(p => p.day === day && p.time <= time && time < p.time + p.span * SLOT_STEP)
}

function computePlacedHeight(span: number) {
  const rowHeight = 40 
  const gap = 4
  return `${span * rowHeight + (span - 1) * gap}px`
}

function formatDuration(mins: number) {
  const h = Math.floor(mins / 60)
  const m = mins % 60
  if (h > 0 && m > 0) return `${h}h${String(m).padStart(2, '0')}`
  if (h > 0) return `${h}h`
  return `${m}min`
}


</script>

<template>
  <AdminLayout>
    <div class="edt-container">
      <header class="edt-toolbar">
        <div class="left">
          <h1 class="title">{{ title }}</h1>
          <div class="controls">
          </div>
        </div>

        <div class="right">
          <button class="btn primary">Sauvegarder</button>
          <button class="btn primary" @click="$inertia.visit('/calendrier-previsionnel/edt')">Annuler</button>

        </div>
      </header>

      <main class="edt-main">
              <aside class="filters">
                <h3 class="mb-2">Cours disponibles</h3>
                <div class="filter-controls mb-2">
                  <input class="search" placeholder="Rechercher un cours..." v-model="searchQuery" />
                </div>
                <div class="semester-row">
                  <select v-model="selectedSemesterFilter" class="semester-select">
                    <option value="all">Tous les semestres</option>
                    <option v-for="s in availableSemesters" :key="s" :value="s">Semestre {{ s }}</option>
                  </select>
                </div>

                <div class="course-list">
                  <div
                    class="course-item"
                    v-for="c in filteredCourses"
                    :key="c.id"
                    draggable="true"
                    @dragstart="(e) => onCourseDragStart(e, c.id)"
                  >
                          <div class="course-top">
                            <div class="course-code">{{ c.code }}</div>
                            <div class="course-duration">{{ formatDuration(c.duration) }}</div>
                          </div>
                    <div class="course-title">{{ c.title }}</div>

                    <div v-if="!c.editing" class="course-meta">
                      <div class="meta">Salle: <strong>{{ c.room || '-' }}</strong></div>
                      <div class="meta">Prof: <strong>{{ c.teacher || '-' }}</strong></div>
                    </div>

                    <div v-else class="course-edit">
                      <select v-model="c.room" class="input-small">
                        <option value="" disabled>Choisir salle</option>
                        <option v-for="r in rooms" :key="r" :value="r">{{ r }}</option>
                      </select>
                      <select v-model="c.teacher" class="input-small">
                        <option value="" disabled>Choisir professeur</option>
                        <option v-for="t in teachers" :key="t" :value="t">{{ t }}</option>
                      </select>
                    </div>

                    <div class="course-actions">
                      <button class="btn small" @click.prevent="c.editing = !c.editing">{{ c.editing ? 'Terminer' : 'Modifier' }}</button>
                    </div>
                  </div>
                </div>
              </aside>
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
                :class="{ blocked: isBlocked(t), droptarget: currentDrop.day === d && currentDrop.time === t }"
                @dragover.prevent="onCellDragOver"
                @dragenter.prevent="(e) => onCellDragEnter(e, d, t)"
                @dragleave="onCellDragLeave"
                @drop.prevent="(e) => onCellDrop(e, d, t)"
              >
                <div v-for="p in placementsStartingAt(d, t)" :key="p.id" class="placed-course" :style="{ height: computePlacedHeight(p.span) }" draggable="true" @dragstart="(e) => onPlacementDragStart(e, p.id)">
                  <div class="placed-title">{{ courses.find(c => c.id === p.courseId)?.title || 'Cours' }}</div>
                  <div class="placed-meta">{{ formatDuration(p.duration) }} • {{ courses.find(c => c.id === p.courseId)?.room || '-' }}</div>
                </div>
                <div v-if="isCovered(d, t) && placementsStartingAt(d, t).length === 0" class="covered-slot"></div>
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
.calendar-area { flex:1; overflow:auto; max-height: calc(100vh - 220px); }
.calendar-header { display:grid; grid-template-columns: 80px repeat(6,1fr); gap:0.5rem; margin-bottom:0.5rem; position:sticky; top:0; z-index:20; background:transparent }
.calendar-header .time-header { height: 100%; }
.calendar-header .day { text-align:center; padding:0.25rem 0.5rem; background:#f3f4f6; border-radius:4px; display:flex; align-items:center; justify-content:center; }
.calendar-grid { display:grid; gap:0.25rem }
.calendar-grid .row { display:grid; grid-template-columns:80px repeat(6,1fr); align-items:start }
.calendar-grid .cell { height:40px; border:1px dashed #e5e7eb; background:#fff; position:relative; overflow:visible }
.calendar-grid .cell.time { padding:0.12rem 0.2rem; font-size:0.85rem; color:#6b7280; background:transparent; border:none; position:sticky; left:0; z-index:15; }

.calendar-grid .cell.blocked {
  background: #ff9d9d;
  border-style: solid;
  border-color: #ff6262;
  opacity: 0.7;
  pointer-events: none;
}

.course-list { margin-top:0.5rem; display:flex; flex-direction:column; gap:0.5rem; max-height: calc(100vh - 380px); overflow:auto }

.semester-row { margin-top:0.5rem }
.semester-select { padding:0.35rem; border-radius:6px; background:#fff; border:1px solid #e5e7eb; width:100% }
.course-item { padding:0.5rem; border-radius:8px; background: linear-gradient(180deg,#fff,#f8fafc); box-shadow:0 1px 2px rgba(0,0,0,0.04); border:1px solid #e6eef6; cursor:grab }
.course-item:active { cursor:grabbing }
.course-top { display:flex; justify-content:space-between; align-items:center; gap:0.5rem }
.course-code { font-weight:600; color:#0f172a }
.course-title { margin-top:0.25rem; color:#374151 }
.course-duration { font-size:0.85rem; color:#6b7280 }
.course-meta { display:flex; gap:0.5rem; margin-top:0.4rem; font-size:0.85rem; color:#374151 }
.course-edit { display:flex; gap:0.4rem; margin-top:0.4rem }
.input-small { padding:0.25rem 0.4rem; border:1px solid #e5e7eb; border-radius:6px }
.course-actions { margin-top:0.5rem; display:flex; gap:0.4rem }
.btn.small { padding:0.25rem 0.4rem; font-size:0.85rem }

.droptarget { outline: 2px dashed #60a5fa; background: #eef8ff }
.placed-course { background:#f0f9ff; border:1px solid #bfdbfe; padding:0.15rem 0.3rem; border-radius:6px; font-size:0.9rem; display:flex; flex-direction:column; justify-content:center; gap:0.12rem; position:absolute; top:0; left:0; right:0; z-index:12 }
.placed-title { font-weight:600; color:#0f172a }
.placed-meta { font-size:0.8rem; color:#4b5563 }
.covered-slot { position:absolute; inset:0; background: rgba(99,102,241,0.06); border-radius:4px; z-index:8 }
</style>
