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

// Détermine la pause déjeuner pour un jour donné
// 2 créneaux possibles: 12h-13h30 (défaut) ou 12h30-14h (si cours après 12h)
// Peut accepter un tableau de placements simulé pour tester un placement hypothétique
function getLunchBreakForDay(dayIndex: number, simulatedPlacements?: Placement[]): { start: number; end: number } {
  // Utiliser les placements simulés ou les placements réels
  const placementsToCheck = simulatedPlacements || placements.value
  
  // Trouver les cours qui se terminent avant ou à 12h30 (= cours du matin)
  const morningPlacements = placementsToCheck.filter(
    p => p.day === dayIndex && (p.time + p.duration) <= 12 * 60 + 30
  )
  
  if (morningPlacements.length === 0) {
    // Pas de cours le matin, utiliser la pause par défaut 12h-13h30
    return { start: 12 * 60, end: 13 * 60 + 30 }
  }
  
  // Trouver l'heure de fin du dernier cours du matin
  const lastMorningEnd = Math.max(
    ...morningPlacements.map(p => p.time + p.duration)
  )
  
  // Si un cours se termine strictement après 12h, décaler la pause à 12h30-14h
  if (lastMorningEnd > 12 * 60) {
    return { start: 12 * 60 + 30, end: 14 * 60 }
  }
  
  // Sinon, pause normale 12h-13h30
  return { start: 12 * 60, end: 13 * 60 + 30 }
}

function isBlocked(dayIndex: number, mins: number, simulatedPlacements?: Placement[]) {
  const lunchBreak = getLunchBreakForDay(dayIndex, simulatedPlacements)
  return mins >= lunchBreak.start && mins < lunchBreak.end
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

        // compute remaining minutes: total semester quota across types (TP+TD+CM)
        const tpContHours = Number(t.tp_hours_continued ?? 0)
        const tdContHours = Number(t.td_hours_continued ?? 0)
        const totalHours = tpHours + tpContHours + tdHours + tdContHours + cmHours
        let remaining = Math.max(0, totalHours * 60)
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
      
      // Utiliser directement le tableau teachers de l'API
      const teachers = Array.isArray(r.teachers) ? r.teachers : []
      console.log('Slot', slotId, 'teachers:', teachers)
      
      placements.value.push({ 
        id: slotId, 
        courseId, 
        day, 
        time, 
        span, 
        duration: durationMinutes, 
        teachers: teachers,
        roomId: r.room_id ?? null, 
        fromDb: true, 
        locked: isLocked, 
        constraintId, 
        editing: false 
      })
      // reduce remaining minutes for this course if present
      const c = courses.value.find(x => x.id === courseId)
      if (c && typeof c.remainingMinutes === 'number') c.remainingMinutes = Math.max(0, (c.remainingMinutes || 0) - (durationMinutes || 0))
    })
    const maxId = placements.value.reduce((max, p) => Math.max(max, p.id), 0)
    nextPlacementId = Math.max(nextPlacementId, maxId + 1)

    // Load all slots (no group filter) to know teacher/room busy times
    busySlots.value = []
    try {
      const allRes = await axios.get(`/api/edt/${yearId}/${weekNumber}`)
      const allData = Array.isArray(allRes.data) ? allRes.data : []
      busySlots.value = allData
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        .map((r: any) => {
          const day = dayNameToIndex(r.day_of_week)
          const start = minutesFromTimeString(r.start_hour)
          const durationMinutes = Number(r.duration) * 60
          const end = start + durationMinutes
          const teachers = Array.isArray(r.teachers) ? r.teachers : []
          return { 
            day, 
            start, 
            end, 
            teachers: teachers,
            roomId: r.room_id ?? null, 
            sourceId: Number(r.id) 
          }
        })
        .filter(s => s.day && s.start >= 0 && s.end > s.start)
    } catch (e) {
      console.warn('Could not load global busy slots', e)
    }
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

type Placement = { id: number; courseId: number; day: number; time: number; span: number; duration: number; teachers?: Array<{id: number; name: string; code?: string}>; roomId?: number | null; fromDb?: boolean; locked?: boolean; constraintId?: number; editing?: boolean; tempTeacherId?: number | null }
const placements = ref<Placement[]>([])
// Keep a lightweight list of busy slots across all groups to detect teacher/room conflicts
type BusySlot = { day: number; start: number; end: number; teachers?: Array<{id: number; name: string}>; roomId?: number | null; sourceId?: number }
const busySlots = ref<BusySlot[]>([])
let nextPlacementId = 1

const currentDrop = ref<{ day: number | null; time: number | null }>({ day: null, time: null })

// Track the course/placement being dragged for highlighting available slots
const draggingCourse = ref<{ courseId?: number; placementId?: number; span: number; teacherId?: number | null; roomId?: number | null } | null>(null)

// Generic overlap detector for a given time range
function hasPlacementOverlap(day: number, startTime: number, span: number, excludePlacementId?: number) {
  const newStart = startTime
  const newEnd = startTime + span * SLOT_STEP
  return placements.value.some(p => {
    if (excludePlacementId !== undefined && p.id === excludePlacementId) return false
    if (p.day !== day) return false
    const existingStart = p.time
    const existingEnd = p.time + p.span * SLOT_STEP
    return newStart < existingEnd && newEnd > existingStart
  })
}

// Check if a slot is available for the course being dragged
function isSlotAvailable(day: number, time: number): boolean {
  if (!draggingCourse.value) return false
  
  const { span, teacherId, roomId, placementId } = draggingCourse.value
  
  // Check if placement would exceed time bounds
  const endTime = time + span * SLOT_STEP
  if (endTime > SLOT_END) return false
  
  // Créer un placement simulé pour tester l'impact sur la pause déjeuner
  const simulatedPlacement: Placement = {
    id: -1, // ID temporaire
    courseId: draggingCourse.value.courseId || 0,
    day,
    time,
    span,
    duration: span * SLOT_STEP
  }
  
  // Filtrer les placements existants (exclure celui qu'on déplace)
  const existingPlacements = placements.value.filter(p => p.id !== placementId)
  const simulatedPlacements = [...existingPlacements, simulatedPlacement]
  
  // Calculer la nouvelle pause APRÈS le placement simulé
  const newLunchBreak = getLunchBreakForDay(day, simulatedPlacements)
  
  // Vérifier que le cours lui-même ne chevauche PAS la nouvelle pause
  const courseStart = time
  const courseEnd = time + span * SLOT_STEP
  if (courseStart < newLunchBreak.end && courseEnd > newLunchBreak.start) {
    return false
  }
  
  // Vérifier que la nouvelle pause ne chevauche pas d'autres cours existants
  const hasLunchConflict = existingPlacements.some(p => {
    if (p.day !== day) return false
    const pStart = p.time
    const pEnd = p.time + p.duration
    // Vérifier si le cours chevauche la nouvelle pause
    return pStart < newLunchBreak.end && pEnd > newLunchBreak.start
  })
  
  if (hasLunchConflict) return false
  
  if (hasPlacementOverlap(day, time, span, placementId)) return false
  
  // Check teacher conflict for the entire span at once
  if (teacherId && hasTeacherConflict(teacherId, day, time, span, placementId)) return false
  
  // Check room conflict for the entire span at once
  if (roomId && hasRoomConflict(roomId, day, time, span, placementId)) return false
  
  return true
}

function onCourseDragStart(e: DragEvent, courseId: number) {
  e.dataTransfer?.setData('text/course-id', String(courseId))
  
  // Set up highlighting for available slots
  const course = courses.value.find(c => c.id === courseId)
  if (course) {
    const selectedDuration = course?.selectedDuration ?? course?.duration ?? SLOT_STEP
    const span = Math.max(1, Math.ceil(selectedDuration / SLOT_STEP))
    const teacherId = typeof course.teacher === 'number' ? course.teacher : (typeof course.teacher === 'string' && /^[0-9]+$/.test(course.teacher) ? parseInt(course.teacher, 10) : null)
    const roomId = typeof course.room === 'number' ? course.room : (typeof course.room === 'string' && /^[0-9]+$/.test(course.room) ? parseInt(course.room, 10) : null)
    
    draggingCourse.value = { courseId, span, teacherId, roomId }
  }
}

function onPlacementDragStart(e: DragEvent, placementId: number) {
  const placement = placements.value.find(p => p.id === placementId)
  if (placement?.locked) {
    e.preventDefault()
    return
  }
  e.dataTransfer?.setData('text/placement-id', String(placementId))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
  
  // Set up highlighting for available slots when moving a placement
  if (placement) {
    // Use first teacher ID for conflict checking during drag
    const firstTeacherId = (placement.teachers && placement.teachers.length > 0) ? placement.teachers[0].id : null
    draggingCourse.value = {
      placementId,
      span: placement.span,
      teacherId: firstTeacherId,
      roomId: placement.roomId
    }
  }
}

function onDragEnd() {
  draggingCourse.value = null
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
function hasTeacherConflict(teacherIdOrIds: number | number[] | null | undefined, day: number, time: number, span: number, excludePlacementId?: number): boolean {
  // Normalize to array
  const teacherIds = Array.isArray(teacherIdOrIds) ? teacherIdOrIds : (teacherIdOrIds ? [teacherIdOrIds] : [])
  if (teacherIds.length === 0) return false
  
  const newStart = time
  const newEnd = time + span * SLOT_STEP

  // Check visible placements
  for (const teacherId of teacherIds) {
    const conflictVisible = placements.value.some(p => {
      if (excludePlacementId !== undefined && p.id === excludePlacementId) return false
      const pTeacherIds = (p.teachers || []).map(t => t.id)
      if (!pTeacherIds.includes(teacherId)) return false
      if (p.day !== day) return false
      const placementEndTime = p.time + p.span * SLOT_STEP
      return newStart < placementEndTime && newEnd > p.time
    })
    if (conflictVisible) return true

    // Check global busy slots (other groups)
    const conflictBusy = busySlots.value.some(s => {
      const sTeacherIds = (s.teachers || []).map(t => t.id)
      if (!sTeacherIds.includes(teacherId)) return false
      if (excludePlacementId !== undefined && s.sourceId === excludePlacementId) return false
      if (s.day !== day) return false
      return newStart < s.end && newEnd > s.start
    })
    if (conflictBusy) return true
  }

  return false
}

// Vérifie si une salle est déjà occupée au même créneau horaire
function hasRoomConflict(roomId: number | null | undefined, day: number, time: number, span: number, excludePlacementId?: number): boolean {
  if (!roomId) return false // Si pas de salle assignée, pas de conflit
  const newStart = time
  const newEnd = time + span * SLOT_STEP

  const conflictVisible = placements.value.some(p => {
    if (excludePlacementId !== undefined && p.id === excludePlacementId) return false
    if (p.roomId !== roomId) return false
    if (p.day !== day) return false
    const placementEndTime = p.time + p.span * SLOT_STEP
    return newStart < placementEndTime && newEnd > p.time
  })
  if (conflictVisible) return true

  const conflictBusy = busySlots.value.some(s => {
    if (!s.roomId || s.roomId !== roomId) return false
    if (excludePlacementId !== undefined && s.sourceId === excludePlacementId) return false
    if (s.day !== day) return false
    return newStart < s.end && newEnd > s.start
  })

  return conflictBusy
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
    const endTime = time + span * SLOT_STEP
    if (endTime > SLOT_END) {
      placements.value.splice(idx, 0, old)
      alert('Placement impossible : dépasse la plage horaire')
      currentDrop.value = { day: null, time: null }
      return
    }
    
    // Créer un placement simulé pour tester l'impact sur la pause déjeuner
    const simulatedPlacement: Placement = {
      ...old,
      day,
      time,
      duration: span * SLOT_STEP
    }
    
    // Filtrer les placements existants (exclure celui qu'on déplace)
    const existingPlacements = placements.value // old est déjà retiré à ce stade
    const simulatedPlacements = [...existingPlacements, simulatedPlacement]
    
    // Calculer la nouvelle pause APRÈS le placement simulé
    const newLunchBreak = getLunchBreakForDay(day, simulatedPlacements)
    
    // Vérifier que le cours lui-même ne chevauche PAS la nouvelle pause
    const courseStart = time
    const courseEnd = time + span * SLOT_STEP
    if (courseStart < newLunchBreak.end && courseEnd > newLunchBreak.start) {
      placements.value.splice(idx, 0, old)
      alert('Le cours chevaucherait la pause déjeuner')
      currentDrop.value = { day: null, time: null }
      return
    }
    
    // Vérifier que la nouvelle pause (après placement) ne chevauche pas d'autres cours
    const hasLunchConflict = existingPlacements.some(p => {
      if (p.day !== day) return false
      const pStart = p.time
      const pEnd = p.time + p.duration
      // Vérifier si le cours chevauche la nouvelle pause
      return pStart < newLunchBreak.end && pEnd > newLunchBreak.start
    })
    
    if (hasLunchConflict) {
      placements.value.splice(idx, 0, old)
      alert('Ce placement décalerait la pause déjeuner sur un autre cours')
      currentDrop.value = { day: null, time: null }
      return
    }
    
    // Vérifier les chevauchements avec d'autres cours
    if (hasPlacementOverlap(day, time, span, old.id)) {
      placements.value.splice(idx, 0, old)
      alert('Chevauchement avec un cours existant — emplacement inchangé')
      currentDrop.value = { day: null, time: null }
      return
    }
    // Vérifier les conflits d'enseignants (pour tous les profs assignés)
    const teacherIdsToCheck = (old.teachers || []).map(t => t.id)
    if (teacherIdsToCheck.length > 0 && hasTeacherConflict(teacherIdsToCheck as number[] | number, day, time, span, old.id)) {
      placements.value.splice(idx, 0, old)
      const teacherNames = (old.teachers || []).map(t => t.name).join(', ')
      alert(`Un ou plusieurs enseignants (${teacherNames}) ont déjà un cours à ce créneau horaire`)
      currentDrop.value = { day: null, time: null }
      return
    }
    // Vérifier les conflits de salle
    if (hasRoomConflict(old.roomId, day, time, span, old.id)) {
      placements.value.splice(idx, 0, old)
      const roomName = rooms.value.find(r => r.id === old.roomId)?.name || 'Cette salle'
      alert(`${roomName} est déjà occupée à ce créneau horaire`)
      currentDrop.value = { day: null, time: null }
      return
    }
    old.day = day
    old.time = time
    placements.value.push(old)
    
    // Update busySlots to reflect the new position
    const busyIdx = busySlots.value.findIndex(s => s.sourceId === old.id)
    if (busyIdx !== -1) {
      busySlots.value[busyIdx].day = day
      busySlots.value[busyIdx].start = time
      busySlots.value[busyIdx].end = time + old.span * SLOT_STEP
    }
    
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
  const endTime = time + span * SLOT_STEP
  if (endTime > SLOT_END) {
    alert('Placement impossible : dépasse la plage horaire')
    currentDrop.value = { day: null, time: null }
    return
  }
  
  // Créer un placement simulé pour tester l'impact sur la pause déjeuner
  const simulatedPlacement: Placement = {
    id: -1, // ID temporaire
    courseId,
    day,
    time,
    span,
    duration: selectedDuration
  }
  
  const existingPlacements = placements.value
  const simulatedPlacements = [...existingPlacements, simulatedPlacement]
  
  // Calculer la nouvelle pause APRÈS le placement simulé
  const newLunchBreak = getLunchBreakForDay(day, simulatedPlacements)
  
  // Vérifier que le cours lui-même ne chevauche PAS la nouvelle pause
  const courseStart = time
  const courseEnd = time + span * SLOT_STEP
  if (courseStart < newLunchBreak.end && courseEnd > newLunchBreak.start) {
    alert('Le cours chevaucherait la pause déjeuner')
    currentDrop.value = { day: null, time: null }
    return
  }
  
  // Vérifier que la nouvelle pause (après placement) ne chevauche pas d'autres cours
  const hasLunchConflict = existingPlacements.some(p => {
    if (p.day !== day) return false
    const pStart = p.time
    const pEnd = p.time + p.duration
    // Vérifier si le cours chevauche la nouvelle pause
    return pStart < newLunchBreak.end && pEnd > newLunchBreak.start
  })
  
  if (hasLunchConflict) {
    alert('Ce placement décalerait la pause déjeuner sur un autre cours')
    currentDrop.value = { day: null, time: null }
    return
  }
  
  if (hasPlacementOverlap(day, time, span)) {
    alert('Chevauchement avec un cours existant — choisissez un autre créneau')
    currentDrop.value = { day: null, time: null }
    return
  }
  const duration = selectedDuration
  // If the course has a selected teacher/room in the left panel, propagate them to the placement
  const placementTeacherRaw = course?.teacher ?? null
  const placementRoomRaw = course?.room ?? null
  const placementTeacherId = typeof placementTeacherRaw === 'number' ? placementTeacherRaw : (typeof placementTeacherRaw === 'string' && /^[0-9]+$/.test(placementTeacherRaw) ? parseInt(placementTeacherRaw, 10) : null)
  const placementRoomId = typeof placementRoomRaw === 'number' ? placementRoomRaw : (typeof placementRoomRaw === 'string' && /^[0-9]+$/.test(placementRoomRaw) ? parseInt(placementRoomRaw, 10) : null)
  
  // Vérifier les conflits d'enseignant
  if (placementTeacherId && hasTeacherConflict(placementTeacherId, day, time, span)) {
    const teacherName = teachers.value.find(t => t.id === placementTeacherId)?.name || 'Cet enseignant'
    alert(`${teacherName} a déjà un cours à ce créneau horaire`)
    currentDrop.value = { day: null, time: null }
    return
  }
  // Vérifier les conflits de salle
  if (hasRoomConflict(placementRoomId, day, time, span)) {
    const roomName = rooms.value.find(r => r.id === placementRoomId)?.name || 'Cette salle'
    alert(`${roomName} est déjà occupée à ce créneau horaire`)
    currentDrop.value = { day: null, time: null }
    return
  }
  
  // Build teachers array for the new placement
  const placementTeachers = placementTeacherId ? [{ id: placementTeacherId, name: teachers.value.find(t => t.id === placementTeacherId)?.name || '' }] : []
  
  // Insert directly in database and capture returned id if possible
  const dbId = await insertPlacementInDb(courseId, day, time, duration, placementTeacherId, placementRoomId)

  const newPlacementId = dbId ?? nextPlacementId++
  placements.value.push({ id: newPlacementId, courseId, day, time, span, duration, teachers: placementTeachers, roomId: placementRoomId, fromDb: dbId !== null, editing: false })
  
  // Add to busySlots if we got a DB id
  if (dbId !== null) {
    busySlots.value.push({
      day,
      start: time,
      end: time + span * SLOT_STEP,
      teachers: placementTeachers,
      roomId: placementRoomId,
      sourceId: dbId
    })
  }
  
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
        
        // Remove from busySlots
        const busyIdx = busySlots.value.findIndex(s => s.sourceId === id)
        if (busyIdx !== -1) {
          busySlots.value.splice(busyIdx, 1)
        }
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

function isTeacherAvailableForPlacement(teacherId: number | null, placement: Placement): boolean {
  if (!teacherId) return true
  // Exclude the teacher IDs already assigned to this placement from conflict check
  const currentTeacherIds = (placement.teachers || []).map(t => t.id)
  if (currentTeacherIds.includes(teacherId)) return true // Already assigned, so available for this placement
  return !hasTeacherConflict(teacherId, placement.day, placement.time, placement.span, placement.id)
}

function isRoomAvailableForPlacement(roomId: number | null, placement: Placement): boolean {
  if (!roomId) return true
  return !hasRoomConflict(roomId, placement.day, placement.time, placement.span, placement.id)
}

const availableTeachers = computed(() => {
  return (p: Placement) => {
    return teachers.value.filter(t => isTeacherAvailableForPlacement(t.id, p))
  }
})

const availableRooms = computed(() => {
  return (p: Placement) => {
    return rooms.value.filter(r => isRoomAvailableForPlacement(r.id, p))
  }
})

function togglePlacementEdit(placementId: number) {
  const p = placements.value.find(x => x.id === placementId)
  if (p) {
    p.editing = !p.editing
    // Initialize teachers if not present
    if (!p.teachers) {
      p.teachers = []
    }
    // Initialize tempTeacherId for the add teacher dropdown
    if (!p.tempTeacherId) {
      p.tempTeacherId = null
    }
  }
}

function addTeacherToPlacement(placementId: number, teacherId: number | null) {
  if (!teacherId) return
  const p = placements.value.find(x => x.id === placementId)
  if (!p) return
  
  if (!p.teachers) p.teachers = []
  if (!p.teachers.find(t => t.id === teacherId)) {
    const teacher = teachers.value.find(t => t.id === teacherId)
    if (teacher) {
      p.teachers.push({ id: teacher.id, name: teacher.name })
    }
  }
  // Reset temp selection
  p.tempTeacherId = null
}

function removeTeacherFromPlacement(placementId: number, teacherId: number) {
  const p = placements.value.find(x => x.id === placementId)
  if (!p || !p.teachers) return
  
  const index = p.teachers.findIndex(t => t.id === teacherId)
  if (index > -1) {
    p.teachers.splice(index, 1)
  }
}

async function savePlacementChanges(placementId: number) {
  const p = placements.value.find(x => x.id === placementId)
  if (!p || !p.fromDb) return

  try {
    const payload = {
      edt_slot_id: p.id,
      teacher_ids: (p.teachers || []).map(t => t.id),
      room_id: p.roomId
    }
    
    await axios.put(`/api/edt/${p.id}`, payload)
    
    p.editing = false
    alert('Modifications enregistrées')
    
    // Recharger les données depuis l'API pour avoir la version à jour avec tous les professeurs
    const id = await resolveYearId()
    const wk = edtStore.week
    if (id && wk) {
      await loadEdtSlots(id, wk)
    }
  } catch (err: any) {
    console.error('Erreur sauvegarde placement', err)
    const msg = err?.response?.data?.error || err?.response?.data?.message || err?.message || 'Erreur serveur'
    alert('Erreur lors de la sauvegarde: ' + msg)
  }
}

function cancelEdit() {
  const params = new URLSearchParams()
  if (edtStore.week) params.append('week', String(edtStore.week))
  if (edtStore.promotionId) params.append('promotion', String(edtStore.promotionId))
  if (edtStore.groupId) params.append('group', String(edtStore.groupId))
  if (edtStore.subgroup) params.append('subgroup', edtStore.subgroup)
  const redirectUrl = '/calendrier-previsionnel/edt' + (params.toString() ? '?' + params.toString() : '')
  window.location.href = redirectUrl
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
      const params = new URLSearchParams()
      if (edtStore.week) params.append('week', String(edtStore.week))
      if (edtStore.promotionId) params.append('promotion', String(edtStore.promotionId))
      if (edtStore.groupId) params.append('group', String(edtStore.groupId))
      if (edtStore.subgroup) params.append('subgroup', edtStore.subgroup)
      const redirectUrl = '/calendrier-previsionnel/edt' + (params.toString() ? '?' + params.toString() : '')
      window.location.href = redirectUrl
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
    
    // On success, navigate back to main EDT page
    window.location.href = '/calendrier-previsionnel/edt'
    return
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
          <button class="btn primary" @click="cancelEdit">Annuler</button>

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
                    @dragend="onDragEnd"
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
                :class="{ 
                  blocked: isBlocked(d, t), 
                  droptarget: currentDrop.day === d && currentDrop.time === t,
                  available: isSlotAvailable(d, t)
                }"
                @dragover.prevent="onCellDragOver"
                @dragenter.prevent="(e) => onCellDragEnter(e, d, t)"
                @dragleave="onCellDragLeave"
                @drop.prevent="(e) => onCellDrop(e, d, t)"
              >
                <div v-for="p in placementsStartingAt(d, t)" :key="p.id" :class="['placed-course', courseKindClass(p.courseId), { locked: p.locked, editing: p.editing }]" :style="{ height: computePlacedHeight(p.span) }" :draggable="!p.editing" @dragstart="(e) => onPlacementDragStart(e, p.id)" @dragend="onDragEnd" @click="() => !p.editing && onPlacementClick(p.id)">
                  <button v-if="!p.locked && !p.editing" class="placed-lock" @click.stop="() => blockSlot(p.id)" title="Bloquer" aria-label="Bloquer le cours">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <rect x="5" y="11" width="14" height="8" rx="2" stroke="currentColor" stroke-width="1.6"/>
                      <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <button v-if="!p.editing" class="placed-edit" @click.stop="() => togglePlacementEdit(p.id)" title="Modifier" aria-label="Modifier le cours">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                  <button v-if="!p.editing" class="placed-trash" @click.stop="() => removePlacementById(p.id)" title="Supprimer" aria-label="Supprimer le cours">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M3 6h18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M10 11v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M14 11v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M9 3h6l-1 3H10L9 3z" fill="currentColor"/>
                    </svg>
                  </button>
                  <div v-if="!p.editing" class="placed-title">{{ courses.find(c => c.id === p.courseId)?.title || 'Cours' }}</div>
                  <div v-if="!p.editing" class="placed-meta">
                    {{ formatDuration(p.duration) }} • {{ (p.roomId ? (rooms.find(r => r.id === p.roomId)?.name) : (courses.find(c => c.id === p.courseId)?.room)) || '-' }}
                  </div>
                  <div v-if="!p.editing && p.teachers && p.teachers.length > 0" class="placed-teachers">
                    <span v-for="teacher in p.teachers.slice(0, 3)" :key="teacher.id" class="teacher-badge">
                      {{ teacher.name }}
                    </span>
                    <span v-if="p.teachers.length > 3" class="teacher-badge teacher-more" :title="`${p.teachers.length - 3} autre(s) professeur(s)`">
                      +{{ p.teachers.length - 3 }}
                    </span>
                  </div>
                  <div v-if="p.editing" class="placed-edit-form">
                    <div class="placed-title">{{ courses.find(c => c.id === p.courseId)?.title || 'Cours' }}</div>
                    <select v-model.number="p.roomId" class="edit-select" @click.stop>
                      <option v-for="r in availableRooms(p)" :key="r.id" :value="r.id">{{ r.name }}</option>
                      <optgroup label="Occupées" v-if="rooms.filter(r => !isRoomAvailableForPlacement(r.id, p)).length > 0">
                        <option v-for="r in rooms.filter(r => !isRoomAvailableForPlacement(r.id, p))" :key="r.id" :value="r.id" disabled class="option-unavailable">{{ r.name }} (occupée)</option>
                      </optgroup>
                    </select>
                    
                    <!-- Liste des professeurs assignés -->
                    <div class="teachers-edit-section">
                      <label class="teachers-label">Professeurs :</label>
                      <div v-if="p.teachers && p.teachers.length > 0" class="teachers-list">
                        <div v-for="teacher in p.teachers" :key="teacher.id" class="teacher-item">
                          <span class="teacher-name">{{ teacher.name }}</span>
                          <button class="btn-remove-teacher" @click.stop="() => removeTeacherFromPlacement(p.id, teacher.id)" title="Retirer ce professeur">×</button>
                        </div>
                      </div>
                      <div v-else class="no-teachers">Aucun professeur assigné</div>
                      
                      <!-- Sélecteur pour ajouter un professeur -->
                      <div class="add-teacher-section">
                        <select v-model.number="p.tempTeacherId" class="edit-select" @click.stop>
                          <option :value="null">+ Ajouter un professeur</option>
                          <option v-for="t in availableTeachers(p).filter(t => !(p.teachers || []).find(pt => pt.id === t.id))" :key="t.id" :value="t.id">{{ t.name }}</option>
                          <optgroup label="Indisponibles" v-if="teachers.filter(t => !isTeacherAvailableForPlacement(t.id, p) && !(p.teachers || []).find(pt => pt.id === t.id)).length > 0">
                            <option v-for="t in teachers.filter(t => !isTeacherAvailableForPlacement(t.id, p) && !(p.teachers || []).find(pt => pt.id === t.id))" :key="t.id" :value="t.id" disabled class="option-unavailable">{{ t.name }} (occupé)</option>
                          </optgroup>
                        </select>
                        <button v-if="p.tempTeacherId" class="btn-add-teacher" @click.stop="() => addTeacherToPlacement(p.id, p.tempTeacherId ?? null)">Ajouter</button>
                      </div>
                    </div>
                    
                    <div class="edit-actions">
                      <button class="btn-edit-save" @click.stop="() => savePlacementChanges(p.id)">✓</button>
                      <button class="btn-edit-cancel" @click.stop="() => togglePlacementEdit(p.id)">✗</button>
                    </div>
                  </div>
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

.calendar-grid .cell.available {
  background: #d1fae5;
  border: 2px solid #34d399;
  box-shadow: inset 0 0 8px rgba(52, 211, 153, 0.3);
  animation: pulse-available 2s ease-in-out infinite;
  z-index: 5;
}

@keyframes pulse-available {
  0%, 100% {
    background: #d1fae5;
  }
  50% {
    background: #a7f3d0;
  }
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
.placed-course.editing { background:#fff7ed; border:2px solid #fb923c; z-index:20; padding:0.25rem 0.4rem }
.placed-title { font-weight:600; color:#0f172a; font-size:0.9rem }
.placed-meta { font-size:0.8rem; color:#4b5563; margin-top:0.1rem }
.placed-teachers { display:flex; flex-wrap:wrap; gap:0.25rem; margin-top:0.3rem; align-items:center }
.teacher-badge { font-size:0.7rem; padding:0.12rem 0.35rem; background:#dbeafe; color:#1e40af; border-radius:3px; border:1px solid #bfdbfe; font-weight:500; white-space:nowrap; line-height:1.2 }
.teacher-badge.teacher-more { background:#fef3c7; color:#92400e; border-color:#fde68a; font-weight:600; cursor:help }
.covered-slot { position:absolute; inset:0; background: rgba(99,102,241,0.06); border-radius:4px; z-index:8 }
.placed-lock { position:absolute; top:4px; left:6px; background:transparent; border:none; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; width:26px; height:26px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px solid rgba(15,23,42,0.04); color:#374151 }
.placed-lock:hover { background:rgba(0,0,0,0.06); transform:translateY(-1px) }
.placed-lock svg { display:block }
.placed-edit { position:absolute; top:4px; right:38px; background:transparent; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; width:26px; height:26px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px solid rgba(15,23,42,0.04); color:#374151 }
.placed-edit:hover { background:rgba(0,0,0,0.06); transform:translateY(-1px) }
.placed-edit svg { display:block }
.placed-trash { position:absolute; top:4px; right:6px; background:transparent; border:none; cursor:pointer; font-size:0.9rem }
.placed-trash { display:flex; align-items:center; justify-content:center; width:26px; height:26px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px solid rgba(15,23,42,0.04); color:#374151 }
.placed-trash:hover { background:rgba(0,0,0,0.06); transform:translateY(-1px) }
.placed-trash svg { display:block }
.placed-edit-form { display:flex; flex-direction:column; gap:0.3rem; padding-top:0.2rem }
.edit-select { padding:0.25rem 0.3rem; border:1px solid #e5e7eb; border-radius:4px; font-size:0.85rem; background:#fff }
.option-unavailable { color:#9ca3af; font-style:italic }
.edit-actions { display:flex; gap:0.3rem; margin-top:0.2rem }
.btn-edit-save, .btn-edit-cancel { padding:0.3rem 0.5rem; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size:0.85rem }
.btn-edit-save { background:#34d399; color:#fff }
.btn-edit-save:hover { background:#10b981 }
.btn-edit-cancel { background:#f87171; color:#fff }
.btn-edit-cancel:hover { background:#ef4444 }

/* Multi-teachers edit interface */
.teachers-edit-section { margin-top:0.5rem; padding:0.5rem; background:#f9fafb; border-radius:6px; border:1px solid #e5e7eb }
.teachers-label { display:block; font-size:0.85rem; font-weight:600; color:#374151; margin-bottom:0.3rem }
.teachers-list { display:flex; flex-direction:column; gap:0.25rem; margin-bottom:0.4rem }
.teacher-item { display:flex; justify-content:space-between; align-items:center; padding:0.3rem 0.4rem; background:#fff; border:1px solid #e5e7eb; border-radius:4px }
.teacher-name { font-size:0.85rem; color:#374151 }
.btn-remove-teacher { background:transparent; border:none; color:#ef4444; font-size:1.2rem; font-weight:bold; cursor:pointer; padding:0 0.3rem; border-radius:4px }
.btn-remove-teacher:hover { background:#fee2e2 }
.no-teachers { font-size:0.85rem; color:#9ca3af; font-style:italic; padding:0.3rem 0 }
.add-teacher-section { display:flex; gap:0.3rem; align-items:center }
.btn-add-teacher { padding:0.3rem 0.6rem; background:#3b82f6; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:0.85rem; font-weight:600 }
.btn-add-teacher:hover { background:#2563eb }

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
