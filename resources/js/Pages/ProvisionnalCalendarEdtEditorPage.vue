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

type Course = { id: number; code?: string; title: string; type?: string; duration?: number; semester?: number | null; room?: number | string; teacher?: number | string; editing?: boolean; remainingMinutes?: number; selectedDuration?: number }
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
  // load rooms and teachers for the year
  await loadRooms()
  if (id) await loadTeachers(id)
  // if week is set as well, load existing edt placements
  const wk = edtStore.week
  if (id && wk) await loadEdtSlots(id, wk)
})

watch(() => edtStore.year, async () => {
  const id = await resolveYearId()
  if (id) await loadTeachingsForYear(id)
  await loadRooms()
  if (id) await loadTeachers(id)
  const wk = edtStore.week
  if (id && wk) await loadEdtSlots(id, wk)
})

watch(() => edtStore.week, async () => {
  const id = await resolveYearId()
  const wk = edtStore.week
  if (id && wk) await loadEdtSlots(id, wk)
})

// map day name to index used in editor (1 = Lundi)
function dayNameToIndex(name: string | null | undefined): number {
  if (!name) return 1
  const n = name.toString().toLowerCase()
  if (n.startsWith('lun')) return 1
  if (n.startsWith('mar')) return 2
  if (n.startsWith('mer')) return 3
  if (n.startsWith('jeu')) return 4
  if (n.startsWith('ven')) return 5
  if (n.startsWith('sam')) return 6
  return 1
}

function minutesFromTimeString(s: string | null | undefined): number {
  if (!s) return 0
  const parts = (s || '').split(':')
  const h = Number(parts[0] || 0)
  const m = Number(parts[1] || 0)
  return h * 60 + m
}

async function loadEdtSlots(yearId: number, weekNumber: number) {
  try {
    // Build URL with filters to show only courses for selected promotion/group/subgroup
    const params = new URLSearchParams()
    if (edtStore.promotionId) params.append('promotion_id', String(edtStore.promotionId))
    if (edtStore.groupId) params.append('group_id', String(edtStore.groupId))
    if (edtStore.subgroup) params.append('subgroup', String(edtStore.subgroup))
    const url = `/api/edt/${yearId}/${weekNumber}${params.toString() ? '?' + params.toString() : ''}`
    
    const res = await axios.get(url)
    const data = Array.isArray(res.data) ? res.data : []
    
    let slotConstraints: Map<number, number> = new Map() // Map slot_id -> constraint_id
    try {
      const constraintsRes = await axios.get('/api/slot-constraints')
      const constraints = Array.isArray(constraintsRes.data) ? constraintsRes.data : []
      const weekId = edtStore.week
      constraints
        .filter((c: any) => c.constraint_type === 'blocked' && c.week_id === weekId)
        .forEach((c: any) => {
          slotConstraints.set(Number(c.slot_id), Number(c.id))
        })
    } catch (e) {
      console.warn('Could not load slot constraints', e)
    }
    
    placements.value = []
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    data.forEach((r: any) => {
      const courseId = Number(r.teaching_id)
      const day = dayNameToIndex(r.day_of_week)
      const time = minutesFromTimeString(r.start_hour)
      const durationMinutes = Number(r.duration) * 60 // duration stored in hours
      const span = Math.max(1, Math.ceil((durationMinutes) / SLOT_STEP))
      // edt_slot id is r.id
      if(!courseId || !day || !time || !durationMinutes) return 
      const slotId = Number(r.id)
      const isLocked = slotConstraints.has(slotId)
      const constraintId = isLocked ? slotConstraints.get(slotId) : undefined
      placements.value.push({ id: slotId, courseId, day, time, span, duration: durationMinutes, teacherId: r.teacher_id ?? null, roomId: r.room_id ?? null, fromDb: true, locked: isLocked, constraintId })
      // reduce remaining minutes for this course if present
      const c = courses.value.find(x => x.id === courseId)
      if (c && typeof c.remainingMinutes === 'number') c.remainingMinutes = Math.max(0, (c.remainingMinutes || 0) - (durationMinutes || 0))
    })
    const maxId = placements.value.reduce((max, p) => Math.max(max, p.id), 0)
    nextPlacementId = Math.max(nextPlacementId, maxId + 1)
  } catch (e) {
    console.warn('Could not load edt slots', e)
  }
}

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

const rooms = ref<{id:number;name:string}[]>([])
const teachers = ref<{id:number;name:string}[]>([])

async function loadRooms() {
  try {
    const res = await axios.get('/api/rooms')
    const data = Array.isArray(res.data) ? res.data : []
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    rooms.value = data.map((r: any) => ({ id: r.id, name: r.name }))
  } catch (e) {
    console.warn('Could not load rooms', e)
  }
}

async function loadTeachers(yearId: number) {
  try {
    const res = await axios.get(`/api/teachers/${yearId}`)
    const data = Array.isArray(res.data) ? res.data : []
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    teachers.value = data.map((t: any) => ({ id: t.id, name: (`${t.last_name ?? ''} ${t.first_name ?? ''}`.trim() || t.acronym || `T${t.id}`) }))
  } catch (e) {
    console.warn('Could not load teachers', e)
  }
}

type Placement = { id: number; courseId: number; day: number; time: number; span: number; duration: number; teacherId?: number | null; roomId?: number | null; fromDb?: boolean; locked?: boolean; constraintId?: number }
const placements = ref<Placement[]>([])
let nextPlacementId = 1

const currentDrop = ref<{ day: number | null; time: number | null }>({ day: null, time: null })

function onCourseDragStart(e: DragEvent, courseId: number) {
  e.dataTransfer?.setData('text/course-id', String(courseId))
}

function onPlacementDragStart(e: DragEvent, placementId: number) {
  const placement = placements.value.find(p => p.id === placementId)
  if (placement?.locked) {
    e.preventDefault()
    return
  }
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

// Vérifie si un enseignant a déjà un cours au même créneau horaire
function hasTeacherConflict(teacherId: number | null | undefined, day: number, time: number, span: number, excludePlacementId?: number): boolean {
  if (!teacherId) return false // Si pas d'enseignant assigné, pas de conflit
  
  // Parcourir tous les créneaux que le nouveau placement occuperait
  for (let i = 0; i < span; i++) {
    const t = time + i * SLOT_STEP
    
    // Vérifier si l'enseignant a déjà un cours qui chevauche ce créneau
    const conflict = placements.value.some(p => {
      // Ignorer le placement qu'on est en train de déplacer
      if (excludePlacementId !== undefined && p.id === excludePlacementId) return false
      
      // Vérifier si c'est le même enseignant et le même jour
      if (p.teacherId === teacherId && p.day === day) {
        // Vérifier si les créneaux se chevauchent
        const placementEndTime = p.time + p.span * SLOT_STEP
        return t >= p.time && t < placementEndTime
      }
      return false
    })
    
    if (conflict) return true
  }
  
  return false
}

async function onCellDrop(e: DragEvent, day: number, time: number) {
  e.preventDefault()
  const placementIdStr = e.dataTransfer?.getData('text/placement-id')
  if (placementIdStr) {
    const placementId = Number(placementIdStr)
    const idx = placements.value.findIndex(p => p.id === placementId)
    if (idx === -1) return
    const old = placements.value[idx]
    
    // Prevent moving locked placements
    if (old.locked) {
      alert('Impossible de déplacer un cours bloqué')
      currentDrop.value = { day: null, time: null }
      return
    }
    
    placements.value.splice(idx, 1)
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
    // Vérifier les conflits d'enseignant
    if (hasTeacherConflict(old.teacherId, day, time, span, old.id)) {
      placements.value.splice(idx, 0, old)
      const teacherName = teachers.value.find(t => t.id === old.teacherId)?.name || 'Cet enseignant'
      alert(`${teacherName} a déjà un cours à ce créneau horaire`)
      currentDrop.value = { day: null, time: null }
      return
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
  // If the course has a selected teacher/room in the left panel, propagate them to the placement
  const placementTeacherRaw = course?.teacher ?? null
  const placementRoomRaw = course?.room ?? null
  const placementTeacherId = typeof placementTeacherRaw === 'number' ? placementTeacherRaw : (typeof placementTeacherRaw === 'string' && /^[0-9]+$/.test(placementTeacherRaw) ? parseInt(placementTeacherRaw, 10) : null)
  const placementRoomId = typeof placementRoomRaw === 'number' ? placementRoomRaw : (typeof placementRoomRaw === 'string' && /^[0-9]+$/.test(placementRoomRaw) ? parseInt(placementRoomRaw, 10) : null)
  
  // Vérifier les conflits d'enseignant
  if (hasTeacherConflict(placementTeacherId, day, time, span)) {
    const teacherName = teachers.value.find(t => t.id === placementTeacherId)?.name || 'Cet enseignant'
    alert(`${teacherName} a déjà un cours à ce créneau horaire`)
    currentDrop.value = { day: null, time: null }
    return
  }
  
  // Insert directly in database and capture returned id if possible
  const dbId = await insertPlacementInDb(courseId, day, time, duration, placementTeacherId, placementRoomId)

  const newPlacementId = dbId ?? nextPlacementId++
  placements.value.push({ id: newPlacementId, courseId, day, time, span, duration, teacherId: placementTeacherId, roomId: placementRoomId, fromDb: dbId !== null })
  // subtract remaining minutes for that course
  if (course) {
    if (typeof course.remainingMinutes === 'number') course.remainingMinutes = Math.max(0, course.remainingMinutes - duration)
  }
  currentDrop.value = { day: null, time: null }
}

async function insertPlacementInDb(courseId: number, day: number, time: number, duration: number, teacherId: number | null, roomId: number | null): Promise<number | null> {
  try {
    const dayNames = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi']
    const dayName = dayNames[day - 1] || 'Lundi'
    const startHour = formatTime(time)
    const durationHours = Number(((duration || SLOT_STEP) / 60).toFixed(1))
    
    // Get default room if not provided
    let finalRoomId = roomId
    if (!finalRoomId) {
      try {
        const roomsRes = await axios.get('/api/rooms')
        const arr = Array.isArray(roomsRes.data) ? roomsRes.data : []
        if (arr.length > 0) finalRoomId = arr[0].id
      } catch (e) {
        console.warn('Could not load rooms', e)
      }
    }
    
    if (!finalRoomId) {
      alert('Aucune salle disponible')
      return null
    }

    const course = courses.value.find(c => c.id === courseId)
    const type = course?.type || 'TD'
    
    const parseOrNull = (v: unknown): number | null => {
      if (typeof v === 'number') return v
      if (typeof v === 'string' && /^[0-9]+$/.test(v)) return parseInt(v, 10)
      return null
    }

    const payload = {
      year_id: edtStore.year,
      week_number: edtStore.week,
      teaching_id: courseId,
      duration: durationHours,
      type: type,
      promotion_id: parseOrNull(edtStore.promotionId),
      group_id: parseOrNull(edtStore.groupId),
      subgroup_id: parseOrNull(edtStore.subgroup),
      day_of_week: dayName,
      start_hour: startHour,
      room_id: finalRoomId,
      teacher_id: teacherId
    }
    
    const res = await axios.post('/api/edt/create', payload)
    console.log('Placement créé:', res.data)
    const newId = res?.data?.id ?? res?.data?.edt_slot_id ?? res?.data?.slot_id ?? null
    return typeof newId === 'number' ? newId : null
  } catch (err) {
    console.error('Erreur insertion placement:', err)
    alert('Erreur lors de la sauvegarde du placement')
    return null
  }
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
  if (confirm('Supprimer ce placement ?')) {
    removePlacementById(id)
  }
}

async function removePlacementById(id: number) {
  const idx = placements.value.findIndex(p => p.id === id)
  if (idx !== -1) {
    const p = placements.value.splice(idx, 1)[0]
    
    if (p.locked && p.constraintId) {
      try {
        await axios.delete(`/api/slot-constraints/${p.constraintId}`)
      } catch (err) {
        console.error('Erreur suppression contrainte slot', err)
      }
    }
    
    // If it's from DB, delete it immediately
    if (p.fromDb) {
      try {
        await axios.delete(`/api/edt/${id}`)
      } catch (err) {
        console.error('Erreur suppression placement', err)
        alert('Erreur lors de la suppression du placement')
        // Re-add to placements if delete failed
        placements.value.splice(idx, 0, p)
        return
      }
    }
    
    // restore remaining minutes to the course
    const c = courses.value.find(x => x.id === p.courseId)
    if (c && typeof c.remainingMinutes === 'number') {
      c.remainingMinutes = Math.max(0, (c.remainingMinutes || 0) + (p.duration || 0))
    }
  }
}

async function blockSlot(placementId: number) {
  const placement = placements.value.find(p => p.id === placementId)
  if (!placement) return

  try {
    const dayNames = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi']
    const dayName = dayNames[placement.day - 1] || 'Lundi'
    const startHour = formatTime(placement.time)
    const endTime = placement.time + (placement.span - 1) * SLOT_STEP + SLOT_STEP
    const endHour = formatTime(endTime)
    const durationMinutes = placement.duration
    const durationHours = durationMinutes / 60

    const weekId = edtStore.week

    const payload = {
      slot_id: placement.id,
      constraint_type: 'blocked',
      day_of_week: dayName,
      start_time: startHour,
      end_time: endHour,
      reason: '',
      priority: 'hard',
      week_id: weekId ? Number(weekId) : null,
      active: true
    }

    const res = await axios.post('/api/slot-constraints', payload)
    console.log('Contrainte de slot créée:', res.data)
    
    placement.locked = true
    placement.constraintId = res.data.id
    
    alert('Slot bloqué avec succès')
  } catch (err: any) {
    console.error('Erreur création contrainte slot', err)
    alert('Erreur lors du blocage du slot: ' + (err?.response?.data?.message || err?.message || 'Erreur serveur'))
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

  // Client-side validation: ensure each placement has required fields to avoid DB errors
  const clientErrors: string[] = []
  placements.value.forEach((p, idx) => {
    if (!p.roomId) clientErrors.push(`Placement ${idx + 1}: aucune salle sélectionnée`)
    if (!p.day) clientErrors.push(`Placement ${idx + 1}: jour manquant`)
    if (p.time === null || typeof p.time === 'undefined') clientErrors.push(`Placement ${idx + 1}: heure de début manquante`)
  })
  if (clientErrors.length) {
    alert(`Impossible de sauvegarder :\n${clientErrors.join('\n')}`)
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

  

  try {
    // Ensure we have a valid room id to use for edt_slot entries. Use first room as fallback.
    let defaultRoomId: number | null = null
    try {
      const roomsRes = await axios.get('/api/rooms')
      const arr = Array.isArray(roomsRes.data) ? roomsRes.data : []
      if (arr.length > 0) defaultRoomId = arr[0].id
    } catch (e) {
      console.warn('Could not load rooms for default room id', e)
    }
    if (!defaultRoomId) {
      alert('Aucune salle disponible pour enregistrer l\'EDT (room_id manquant)')
      return
    }

    // Build placements with position info for edt_slot API
    const dayNames = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi']
    
    // Only updates for placements that already exist in DB
    const modifiedPlacements = placements.value.filter(p => p.fromDb)
    
    if (modifiedPlacements.length === 0) {
      // No modifications, just redirect
      window.location.href = '/calendrier-previsionnel/edt'
      return
    }
    
    // Send updates
    const edtPayload = {
      updates: modifiedPlacements.map(p => {
        const startHour = formatTime(p.time)
        const dayName = dayNames[(p.day || 1) - 1] || 'Lundi'

        return {
          edt_slot_id: p.id,
          day_of_week: dayName,
          start_hour: startHour,
          room_id: p.roomId ?? defaultRoomId
        }
      })
    }
    const saveRes = await axios.post('/api/edt/bulk', edtPayload)
    
    // Check if there were any errors in the response (status 207 = multi-status)
    if (saveRes.status === 207 && saveRes.data?.errors && saveRes.data.errors.length > 0) {
      const errorMessages = saveRes.data.errors.join('\n')
      alert(`Certains placements n'ont pas pu être sauvegardés :\n${errorMessages}`)
      return
    }

    // On success, navigate back to main EDT page
    window.location.href = '/calendrier-previsionnel/edt'
    return
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
      } else if (e?.response?.status === 422 && e?.response?.data?.error) {
        // Single error message
        msg = e.response.data.error
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
                      <div class="meta">Salle: <strong>{{ (typeof c.room === 'number' ? (rooms.find(r => r.id === c.room)?.name) : c.room) || '-' }}</strong></div>
                      <div class="meta">Prof: <strong>{{ (typeof c.teacher === 'number' ? (teachers.find(t => t.id === c.teacher)?.name) : c.teacher) || '-' }}</strong></div>
                      <div class="meta">Restant: <strong>{{ formatDuration(c.remainingMinutes ?? 0) }}</strong></div>
                    </div>

                    <div v-else class="course-edit">
                      <select v-model="c.room" class="input-small">
                        <option value="" disabled>Choisir salle</option>
                        <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                      </select>
                      <select v-model="c.teacher" class="input-small">
                        <option value="" disabled>Choisir professeur</option>
                        <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
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
                <div v-for="p in placementsStartingAt(d, t)" :key="p.id" :class="['placed-course', courseKindClass(p.courseId), { locked: p.locked }]" :style="{ height: computePlacedHeight(p.span) }" draggable="true" @dragstart="(e) => onPlacementDragStart(e, p.id)" @click="() => onPlacementClick(p.id)">
                  <button v-if="!p.locked" class="placed-lock" @click.stop="() => blockSlot(p.id)" title="Bloquer" aria-label="Bloquer le cours">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <rect x="5" y="11" width="14" height="8" rx="2" stroke="currentColor" stroke-width="1.6"/>
                      <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
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
                  <div class="placed-meta">{{ formatDuration(p.duration) }} • {{ (p.roomId ? (rooms.find(r => r.id === p.roomId)?.name) : (courses.find(c => c.id === p.courseId)?.room)) || '-' }}</div>
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
.placed-course.locked { background:#fef2f2; border:1px solid #fecaca; opacity:0.8; cursor:not-allowed }
.placed-title { font-weight:600; color:#0f172a }
.placed-meta { font-size:0.8rem; color:#4b5563 }
.covered-slot { position:absolute; inset:0; background: rgba(99,102,241,0.06); border-radius:4px; z-index:8 }
.placed-lock { position:absolute; top:4px; left:6px; background:transparent; border:none; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; width:26px; height:26px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px solid rgba(15,23,42,0.04); color:#374151 }
.placed-lock:hover { background:rgba(0,0,0,0.06); transform:translateY(-1px) }
.placed-lock svg { display:block }
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
