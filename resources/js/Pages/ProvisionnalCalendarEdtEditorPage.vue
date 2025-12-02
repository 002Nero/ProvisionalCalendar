<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
// IconButton import removed (unused)
import { useEdtStore } from '@/stores/edtStore'

const edtStore = useEdtStore()

const title = computed(() => {
  const wk = edtStore.week ?? '-'
  const promo = edtStore.promotionId ? `Promo A${edtStore.promotionId}` : '-'
  const subgroup = edtStore.subgroup ? String(edtStore.subgroup) : ''
  const grp = edtStore.groupId ? `G${edtStore.groupId}${subgroup}` : '-'
  const yr = edtStore.year ?? '-'
  return `Modification Emploi du temps — Semaine ${wk}, ${promo} - ${grp} Année ${yr}`
})

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

type Course = { id: number; code?: string; title: string; type?: string; duration?: number; semester?: number | null; room?: string; teacher?: string; editing?: boolean; remainingMinutes?: number; selectedDuration?: number }
const courses = ref<Course[]>([])

async function loadTeachingsForYear(yearId: number) {
  try {
      const res = await axios.get(`/api/teachings/${yearId}`)
      const data = Array.isArray(res.data) ? res.data : []
      console.debug('teachings API response count', data.length, data.slice ? data.slice(0,5) : data)
      // Map backend teaching structure to Course used by the UI.
      // Be permissive: the API has changed names in places (cm_hours vs cm_hours_initial,
      // td_hours_intial typo, title vs name). We normalize here without touching the DB.
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      courses.value = data.map((t: any) => {
        const title = t.name ?? t.title ?? `Enseignement ${t.id}`
        const code = t.apogee_code ?? t.code ?? undefined

        const tpHours = Number(t.tp_hours_initial ?? t.tp_hours ?? 0)
        // backend had a typo "td_hours_intial" in one method; accept several forms
        const tdHours = Number(t.td_hours_initial ?? t.td_hours_intial ?? t.td_hours ?? 0)
        const cmHours = Number(t.cm_hours_initial ?? t.cm_hours ?? 0)

        // prefer cm > td > tp for default type
        let type = 'Autre'
        if (cmHours > 0) type = 'CM'
        else if (tdHours > 0) type = 'TD'
        else if (tpHours > 0) type = 'TP'

        // determine a sensible default duration in minutes: use the first non-zero hours * 60, fallback 60
        const hours = tdHours || tpHours || cmHours || 1
        const durationMinutes = Math.max(1, Number(hours)) * 60

        // compute remaining minutes depending on type
        let remaining = 0
        if (type === 'CM') remaining = cmHours * 60
        else if (type === 'TD') remaining = tdHours * 60
        else if (type === 'TP') remaining = tpHours * 60
        if (!remaining || remaining <= 0) remaining = durationMinutes

        return {
          id: Number(t.id),
          code,
          title,
          type,
          duration: durationMinutes,
          // Default semester to 1 when missing so filters don't hide the course
          semester: t.semester ?? t.semester_id ?? 1,
          editing: false,
          remainingMinutes: remaining,
          selectedDuration: Math.min(SLOT_STEP, remaining) // default min step or remaining
        }
      })
  } catch (e) {
    console.error('Erreur chargement enseignements', e)
  }
}

async function resolveYearId(): Promise<number | null> {
  const y = edtStore.year
  if (!y) return null
  if (typeof y === 'number') return y
  if (typeof y === 'string' && /^[0-9]+$/.test(y)) return parseInt(y, 10)
  // else try to find by name
  try {
    const res = await axios.get('/api/years')
    const found = (res.data || []).find((it: { id: number; name: string }) => it.name === y)
    return found ? found.id : null
  } catch (err) {
    console.warn('Impossible de résoudre l\'année', err)
    return null
  }
}

// Load teachings when year is known
onMounted(async () => {
  const id = await resolveYearId()
  if (id) await loadTeachingsForYear(id)
})

watch(() => edtStore.year, async () => {
  const id = await resolveYearId()
  if (id) await loadTeachingsForYear(id)
})

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

watch(selectedPromotionFilter, () => {
  if (selectedSemesterFilter.value !== 'all' && !availableSemesters.value.includes(selectedSemesterFilter.value as number)) {
    selectedSemesterFilter.value = 'all'
  }
})

const filteredCourses = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return courses.value.filter(c => {
    if (selectedSemesterFilter.value !== 'all' && (c.semester ?? -1) !== selectedSemesterFilter.value) return false
    const allowed = semestersByPromotion[selectedPromotionFilter.value] || semesters
    if (!allowed.includes((c.semester ?? -1) as number)) return false
    if (!q) return true
    return (c.title || '').toLowerCase().includes(q) || (c.code || '').toLowerCase().includes(q)
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

// durationSlotsForCourse removed; we now compute span from selectedDuration where needed

function durationOptionsForCourse(c: Course) {
  const max = Math.max(c.remainingMinutes ?? (c.duration ?? SLOT_STEP), SLOT_STEP)
  const steps = Math.max(1, Math.ceil(max / SLOT_STEP))
  const arr: number[] = []
  for (let i = 1; i <= steps; i++) arr.push(i * SLOT_STEP)
  return arr
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
    const lastSlotStart = SLOT_END
    const endTime = time + (span - 1) * SLOT_STEP
    if (endTime > lastSlotStart) {
      placements.value.splice(idx, 0, old)
      alert('Placement impossible : dépasse la plage horaire')
      currentDrop.value = { day: null, time: null }
      return
    }
    for (let i = 0; i < span; i++) {
      const t = time + i * SLOT_STEP
      if (isBlocked(t) || placements.value.some(p => p.day === day && p.time <= t && t < p.time + p.span * SLOT_STEP)) {
        placements.value.splice(idx, 0, old)
        alert('Chevauchement ou zone bloquée lors du déplacement — emplacement inchangé')
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
  const course = courses.value.find(x => x.id === courseId)
  // determine selected duration (minutes)
  const selectedDuration = course?.selectedDuration ?? course?.duration ?? SLOT_STEP
  // check remaining minutes
  if (course && typeof course.remainingMinutes === 'number' && course.remainingMinutes < selectedDuration) {
    alert('Impossible : pas assez d\'heures restantes pour cet enseignement')
    currentDrop.value = { day: null, time: null }
    return
  }
  const span = Math.max(1, Math.ceil(selectedDuration / SLOT_STEP))
  const lastSlotStart = SLOT_END
  const endTime = time + (span - 1) * SLOT_STEP
  if (endTime > lastSlotStart) {
    alert('Placement impossible : dépasse la plage horaire')
    currentDrop.value = { day: null, time: null }
    return
  }
  for (let i = 0; i < span; i++) {
    const t = time + i * SLOT_STEP
    if (isBlocked(t) || placements.value.some(p => p.day === day && p.time <= t && t < p.time + p.span * SLOT_STEP)) {
      alert('Chevauchement avec un cours existant ou zone bloquée — choisissez un autre créneau')
      currentDrop.value = { day: null, time: null }
      return
    }
  }
  const duration = selectedDuration
  placements.value.push({ id: nextPlacementId++, courseId, day, time, span, duration })
  // subtract remaining minutes for that course
  if (course) {
    if (typeof course.remainingMinutes === 'number') course.remainingMinutes = Math.max(0, course.remainingMinutes - duration)
  }
  currentDrop.value = { day: null, time: null }
}

// placementsFor removed (unused) — use placementsStartingAt instead

function placementsStartingAt(day: number, time: number) {
  return placements.value.filter(p => p.day === day && p.time === time)
}

function courseKindClass(courseId: number) {
  const c = courses.value.find(x => x.id === courseId)
  const t = (c?.type || 'Autre').toString().toLowerCase()
  const map: Record<string,string> = { sae: 'sae', tp: 'tp', td: 'td', controle: 'controle', cm: 'autre' }
  const key = map[t] ?? (t.match(/sae|tp|td|controle/) ? t : 'autre')
  return `placed-${key}`
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

function onPlacementClick(id: number) {
  if (confirm('Supprimer ce placement ?')) removePlacementById(id)
}

function removePlacementById(id: number) {
  const idx = placements.value.findIndex(p => p.id === id)
  if (idx !== -1) {
    const p = placements.value.splice(idx, 1)[0]
    // restore remaining minutes to the course
    const c = courses.value.find(x => x.id === p.courseId)
    if (c && typeof c.remainingMinutes === 'number') {
      c.remainingMinutes = Math.max(0, (c.remainingMinutes || 0) + (p.duration || 0))
    }
  }
}

async function saveEdt() {
  if (!edtStore.year || !edtStore.week) {
    alert('Veuillez sélectionner une année et une semaine avant de sauvegarder.')
    return
  }

  if (placements.value.length === 0) {
    alert('Aucun placement à sauvegarder.')
    return
  }

  // Resolve year_id: edtStore.year can be an id (number), numeric string, or a name.
  let yearId: number | null = null
  if (typeof edtStore.year === 'number') yearId = edtStore.year
  else if (typeof edtStore.year === 'string' && /^[0-9]+$/.test(edtStore.year)) yearId = parseInt(edtStore.year, 10)
  else if (typeof edtStore.year === 'string') {
    // try to resolve by name via API
    try {
      const yearsRes = await axios.get('/api/years')
      const found = (yearsRes.data || []).find((y: { id: number; name: string }) => y.name === edtStore.year)
      if (found) yearId = found.id
    } catch (e) {
      console.warn("Impossible de récupérer les années pour résoudre l'ID", e)
    }
  }

  if (!yearId) {
    alert("Impossible de résoudre l'ID de l'année sélectionnée. Vérifiez la sélection.")
    return
  }

  const payload = {
    year_id: yearId,
    week_number: edtStore.week,
    placements: placements.value.map(p => {
      const teachingId = p.courseId
      const durationHours = Number((p.duration / 60).toFixed(1))
      const course = courses.value.find(c => c.id === p.courseId)
      const rawType = (course?.type || '').toString().toUpperCase()
      let type = 'TD'
      if (rawType.includes('CM')) type = 'CM'
      else if (rawType.includes('TP')) type = 'TP'
      else if (rawType.includes('TD')) type = 'TD'

      return {
        teaching_id: teachingId,
        duration: durationHours,
        type,
        promotion_id: edtStore.promotionId ?? null,
        group_id: edtStore.groupId ?? null,
        subgroup_id: edtStore.subgroup ?? null,
        is_neutralized: false
      }
    })
  }

  try {
    const res = await axios.post('/api/calendrier/bulk', payload)
    if (res.status === 201 || res.status === 200 || res.status === 207) {
      const msg = res.data?.message || 'Sauvegarde terminée'
      alert(msg)
    } else {
      alert('Réponse inattendue du serveur')
      console.warn(res)
    }
  } catch (err: unknown) {
    console.error(err)
    let msg = 'Erreur lors de la sauvegarde'
    if (err && typeof err === 'object') {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const e = err as any
      // If validation errors (422) include detailed messages
      if (e?.response?.status === 422 && e?.response?.data?.messages) {
        const messages = e.response.data.messages
        // Flatten messages object into a string
        msg = Object.keys(messages).map(k => `${k}: ${messages[k].join ? messages[k].join(', ') : messages[k]}`).join('\n')
      } else {
        msg = e?.response?.data?.message || e?.message || msg
      }
    }
    alert(msg)
  }
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
          <button class="btn primary" @click="saveEdt">Sauvegarder</button>
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
                                    <span class="course-badge" :class="courseKindClass(c.id)" aria-hidden="true"></span>
                                                                      <div class="course-code">{{ c.code }}</div>
                                                                      <div class="course-duration">{{ formatDuration(c.duration ?? 0) }}</div>
                                  </div>
                    <div class="course-title">{{ c.title }}</div>

                    <div v-if="!c.editing" class="course-meta">
                      <div class="meta">Salle: <strong>{{ c.room || '-' }}</strong></div>
                      <div class="meta">Prof: <strong>{{ c.teacher || '-' }}</strong></div>
                      <div class="meta">Restant: <strong>{{ formatDuration(c.remainingMinutes ?? 0) }}</strong></div>
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
                      <select v-model.number="c.selectedDuration" class="input-small">
                        <option v-for="m in durationOptionsForCourse(c)" :key="m" :value="m">{{ formatDuration(m) }}</option>
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
                <div v-for="p in placementsStartingAt(d, t)" :key="p.id" :class="['placed-course', courseKindClass(p.courseId)]" :style="{ height: computePlacedHeight(p.span) }" draggable="true" @dragstart="(e) => onPlacementDragStart(e, p.id)" @click="() => onPlacementClick(p.id)">
                  <button class="placed-trash" @click.stop="() => removePlacementById(p.id)" title="Supprimer" aria-label="Supprimer le cours">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M3 6h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M10 11v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M14 11v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M9 3h6l-1 3H10L9 3z" fill="currentColor"/>
                    </svg>
                  </button>
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
.course-top { align-items: center }
.course-badge { width:12px; height:12px; border-radius:50%; display:inline-block; margin-right:0.5rem; flex:0 0 auto }
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
.placed-trash { position:absolute; top:4px; right:6px; background:transparent; border:none; cursor:pointer; font-size:0.9rem }
.placed-trash { display:flex; align-items:center; justify-content:center; width:26px; height:26px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px solid rgba(15,23,42,0.04); color:#374151 }
.placed-trash:hover { background:rgba(0,0,0,0.06); transform:translateY(-1px) }
.placed-trash svg { display:block }

/* Course badge colors (also used by placed blocks via class) */
.course-badge.placed-sae { background: #34d399; box-shadow: 0 0 0 3px rgba(52,211,153,0.08) }
.course-badge.placed-tp { background: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,0.08) }
.course-badge.placed-td { background: #fb7185; box-shadow: 0 0 0 3px rgba(251,113,133,0.08) }
.course-badge.placed-controle { background: #a78bfa; box-shadow: 0 0 0 3px rgba(167,139,250,0.08) }
.course-badge.placed-autre { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.06) }

/* Color by course type */
.placed-sae { background: #e6ffed; border-color: #34d399; }
.placed-tp { background: #e6f2ff; border-color: #60a5fa; }
.placed-td { background: #fff0f6; border-color: #fb7185; }
.placed-controle { background: #f3e8ff; border-color: #a78bfa; }
.placed-autre { background: #fffaf0; border-color: #f59e0b; }
</style>
