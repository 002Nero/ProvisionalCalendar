<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useEdtStore } from '@/stores/edtStore'

const isGenerating = ref(false)
const generationStatus = ref<'idle' | 'processing' | 'done' | 'error'>('idle')
let pollingInterval: number | null = null

async function handleGenerate() {
  if (!edtStore.year || !currentWeek.value) {
    alert('Année ou semaine non sélectionnée')
    return
  }
  let yearId: number | null = null
  if (typeof edtStore.year === 'number') yearId = edtStore.year
  else if (typeof edtStore.year === 'string' && /^[0-9]+$/.test(edtStore.year)) yearId = parseInt(edtStore.year as string, 10)
  
  if (!yearId) {
    alert('Année non valide')
    return
  }
  
  const weekId = currentWeek.value
  isGenerating.value = true
  generationStatus.value = 'processing'
  try {
    // Appel API POST pour lancer la génération
    await axios.post(
      `${import.meta.env.VITE_API_BASE_URL}/generate`,
      { 
        year_id: yearId,
        week_id: weekId 
      },
      {
        headers: {
          Authorization: import.meta.env.VITE_API_AUTHORIZATION || ''
        }
      }
    )
    // Lancer le polling du statut
    startPollingStatus(yearId, weekId)
  } catch (e) {
    isGenerating.value = false
    generationStatus.value = 'error'
    alert('Erreur lors du lancement de la génération')
  }
}

function startPollingStatus(yearId: number, weekId: number) {
  stopPollingStatus()
  pollingInterval = window.setInterval(async () => {
    try {
      const res = await axios.get(
        `${import.meta.env.VITE_API_BASE_URL}/generate/status/${yearId}/${weekId}`,
        {
          headers: {
            Authorization: import.meta.env.VITE_API_AUTHORIZATION || ''
          }
        }
      )
      if (res.data && res.data.status) {
        if (res.data.status === 'done') {
          isGenerating.value = false
          generationStatus.value = 'done'
          stopPollingStatus()
          // Optionnel : recharger l'EDT après génération
          await loadEdtSlotsForCurrent()
        } else if (res.data.status === 'processing') {
          generationStatus.value = 'processing'
        } else {
          isGenerating.value = false
          generationStatus.value = 'error'
          stopPollingStatus()
        }
      }
    } catch (e) {
      isGenerating.value = false
      generationStatus.value = 'error'
      stopPollingStatus()
    }
  }, 10000) // toutes les 10 secondes
}

function stopPollingStatus() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }
}



import AdminLayout from '@/Layouts/AdminLayout.vue'
import IconButton from '@/Components/IconButton.vue'

// promotions/groups loaded from API (dependent on year)
const promotions = ref<{ id: number; name: string }[]>([])
const groups = ref<{ id: number; name: string }[]>([])
const groupsWithSubgroups = ref<{ id: number; name: string; subgroups: string[]; displayName: string }[]>([])

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
  isExam?: boolean
  type: string  
  groupId?: number  
  subgroup?: string 
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
      if (edtStore.promotionId && promotions.value.some(p => p.id === edtStore.promotionId)) {
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
    groupsWithSubgroups.value = []
    return
  }
  try {
    const res = await axios.get(`/api/groups/${promoId}`)
    groups.value = Array.isArray(res.data) ? res.data : []
    const withSubs = groups.value.map(group => ({
      id: group.id,
      name: group.name,
      subgroups: ['A', 'B'], 
      displayName: group.name
    }))
    groupsWithSubgroups.value = withSubs
    if (groups.value.length > 0) {
      if (edtStore.groupId && groups.value.some(g => g.id === edtStore.groupId)) {
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
  const urlParams = new URLSearchParams(window.location.search)
  
  const weekParam = urlParams.get('week')
  if (weekParam && /^\d+$/.test(weekParam)) {
    const weekNum = parseInt(weekParam, 10)
    if (weekNum > 0) {
      currentWeek.value = weekNum
    }
  }
  
  if (currentWeek.value === 1 && typeof edtStore.week === 'number' && edtStore.week > 0) {
    currentWeek.value = edtStore.week
  }
  
  const promotionParam = urlParams.get('promotion')
  if (promotionParam && /^\d+$/.test(promotionParam)) {
    const promoId = parseInt(promotionParam, 10)
    selectedPromotion.value = promoId
  }
  
  const groupParam = urlParams.get('group')
  if (groupParam && /^\d+$/.test(groupParam)) {
    const groupId = parseInt(groupParam, 10)
    selectedGroup.value = groupId
  }
  
  const subgroupParam = urlParams.get('subgroup')
  if (subgroupParam) {
    selectedSubgroup.value = subgroupParam
  }
  
  await ensureDataLoaded()
  // After promotions/groups are loaded, load EDT slots with filters
  if (edtStore.year && currentWeek.value) {
    await loadEdtSlotsForCurrent()
  }
})

onUnmounted(() => {
  stopPollingStatus()
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
    if (selectedGroup.value && selectedSubgroup.value) params.append('subgroup', selectedSubgroup.value)
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
      
      // Teacher - display all teachers, comma-separated
      const teachers = Array.isArray(r.teachers) ? r.teachers : []
      const teacher = teachers.map((t: any) => t.name || t.code || '').filter(Boolean).join(', ')
      
      // room_name or room_id
      const room = r.room_name ?? (r.room_id ? `Salle ${r.room_id}` : '')
      
      // prefer backend-provided slot type color; otherwise fallback per acronym
      const typeAcr = (r.type_acronym ?? r.teaching_type ?? r.type ?? '').toString().toUpperCase()
      const fallbackColors: Record<string,string> = { CM: '#fde74c', TD: '#fddd2d', TP: '#809bce', SAE: '#20bf55', EX: '#a26769' }
      const color = (r as { type_color?: string }).type_color || fallbackColors[typeAcr] || '#fef3c7'
      const isExam = (r as { is_exam?: boolean | number }).is_exam === 1 || (r as { is_exam?: boolean | number }).is_exam === true

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
        isExam,
        type: typeAcr,
        groupId: r.group_id ? Number(r.group_id) : undefined,
        subgroup: r.subgroup ? String(r.subgroup) : undefined,
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

function shouldDisplayLessonInCell(lesson: Lesson, groupIdx: number, subIdx: number, currentGroupsWithSubgroups: any[]): boolean {
  if (lesson.type === 'CM') {
    return groupIdx === 0 && subIdx === 0
  } else if (lesson.type === 'TD') {
    const group = currentGroupsWithSubgroups[groupIdx]
    return group && lesson.groupId === group.id && subIdx === 0
  } else if (lesson.type === 'TP') {
    const group = currentGroupsWithSubgroups[groupIdx]
    return group && lesson.groupId === group.id && lesson.subgroup === group.subgroups[subIdx]
  }
  return groupIdx === 0 && subIdx === 0
}

function getRowSpan(lesson: Lesson, currentGroupsWithSubgroups: any[], groupIdx: number, subIdx: number): number {
  if (lesson.type === 'CM') {
    return currentGroupsWithSubgroups.reduce((sum, g) => sum + g.subgroups.length, 0)
  } else if (lesson.type === 'TD' && lesson.groupId) {
    const group = currentGroupsWithSubgroups.find(g => g.id === lesson.groupId)
    return group ? group.subgroups.length : 1
  }

  return 1
}

// check if cell is covered by a lesson (for graying out covered slots)
function isCovered(dayIndex: number, minute: number, groupId?: number, subgroup?: string) {
  return lessons.value.some(l => {
    if (l.day !== dayIndex || l.start_min > minute || minute >= l.start_min + l.span * SLOT_STEP) {
      return false
    }
    if (groupId !== undefined && subgroup !== undefined) {
      if (l.type === 'CM') return true 
      if (l.type === 'TD') return l.groupId === groupId
      if (l.type === 'TP') return l.groupId === groupId && l.subgroup === subgroup 
      return false
    }
    return true
  })
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

// Détermine la pause déjeuner pour un jour donné
// 2 créneaux possibles: 12h-13h30 (défaut) ou 12h30-14h (si cours après 12h)
// Peut filtrer par groupe/sous-groupe pour la vue horizontale
function getLunchBreakForDay(dayIndex: number, groupId?: number, subgroup?: string): { start: number; end: number } {
  // Filtrer les cours du jour qui se terminent avant ou à 12h30
  let morningLessons = lessons.value.filter(
    l => l.day === dayIndex && (l.start_min + l.duration_min) <= 12 * 60 + 30
  )
  
  // Si un groupe est spécifié, ne considérer que les cours de ce groupe/sous-groupe
  if (groupId !== undefined) {
    morningLessons = morningLessons.filter(l => {
      // CM : visible pour tous
      if (l.type === 'CM') return true
      // TD : filtrer par groupe
      if (l.type === 'TD') return l.groupId === groupId
      // TP : filtrer par groupe ET sous-groupe
      if (l.type === 'TP') return l.groupId === groupId && l.subgroup === subgroup
      // Autres : visible pour tous
      return true
    })
  }
  
  if (morningLessons.length === 0) {
    // Pas de cours le matin, utiliser la pause par défaut 12h-13h30
    return { start: 12 * 60, end: 13 * 60 + 30 }
  }
  
  // Trouver l'heure de fin du dernier cours du matin
  const lastMorningEnd = Math.max(
    ...morningLessons.map(l => l.start_min + l.duration_min)
  )
  
  // Si un cours se termine strictement après 12h, décaler la pause à 12h30-14h
  if (lastMorningEnd > 12 * 60) {
    return { start: 12 * 60 + 30, end: 14 * 60 }
  }
  
  // Sinon, pause normale 12h-13h30
  return { start: 12 * 60, end: 13 * 60 + 30 }
}

function isBlocked(dayIndex: number, mins: number, groupId?: number, subgroup?: string) {
  const lunchBreak = getLunchBreakForDay(dayIndex, groupId, subgroup)
  return mins >= lunchBreak.start && mins < lunchBreak.end
}

function prevWeek() {
  if (currentWeek.value > 1) currentWeek.value -= 1
}
function nextWeek() {
  currentWeek.value += 1
}

function generatePDF() {
  try {
    // Chercher d'abord la vue verticale, sinon la vue horizontale
    const calendarArea = document.querySelector('.calendar-area') || document.querySelector('.calendar-area-horizontal')
    if (!calendarArea) {
      alert('Impossible de trouver l\'emploi du temps à exporter')
      return
    }

    // Détecter le type de vue
    const isHorizontalView = calendarArea.classList.contains('calendar-area-horizontal')
    const selectorClass = isHorizontalView ? '.calendar-area-horizontal' : '.calendar-area'

    // Charger html2canvas
    const script1 = document.createElement('script')
    script1.src = 'https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js'
    script1.onload = function() {
      // Charger jsPDF
      const script2 = document.createElement('script')
      script2.src = 'https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js'
      script2.onload = function() {
        // @ts-ignore
        const jsPDF = window.jspdf.jsPDF
        // @ts-ignore  
        const html2canvas = window.html2canvas

        // Pour la vue horizontale, trouver le conteneur scrollable
        let captureElement = calendarArea
        if (isHorizontalView) {
          const horizontalWrapper = calendarArea.querySelector('.horizontal-wrapper')
          if (horizontalWrapper) {
            captureElement = horizontalWrapper as HTMLElement
          }
        }

        // Capturer le calendrier avec couleurs
        // Important: capturer tout l'élément, même ce qui n'est pas visible à l'écran
        html2canvas(captureElement, {
          scale: 2.5,  // Augmenté pour meilleure qualité
          backgroundColor: '#ffffff',
          useCORS: true,
          logging: false,
          allowTaint: true,
          // Capturer toute la zone, pas seulement la partie visible
          scrollY: -window.scrollY,
          scrollX: -window.scrollX,
          windowHeight: captureElement.scrollHeight + 100,
          windowWidth: captureElement.scrollWidth + 100,
          height: captureElement.scrollHeight,
          width: captureElement.scrollWidth,
          onclone: function(doc: Document) {
            // Forcer l'affichage complet dans le clone
            const clonedElement = doc.querySelector(isHorizontalView ? '.horizontal-wrapper' : selectorClass) as HTMLElement
            if (clonedElement) {
              clonedElement.style.overflow = 'visible'
              clonedElement.style.height = 'auto'
              clonedElement.style.maxHeight = 'none'
              clonedElement.style.width = 'auto'
              clonedElement.style.maxWidth = 'none'
              clonedElement.style.transform = 'none'
            }
            // Forcer tous les éléments parents à être visibles
            const wrapper = doc.querySelector('.horizontal-wrapper') as HTMLElement
            if (wrapper) {
              wrapper.style.overflow = 'visible'
              wrapper.style.height = 'auto'
              wrapper.style.maxHeight = 'none'
              wrapper.style.flexDirection = 'column'
              wrapper.style.display = 'flex'
            }
            const scrollableContent = doc.querySelector('.scrollable-content') as HTMLElement
            if (scrollableContent) {
              scrollableContent.style.overflow = 'visible'
              scrollableContent.style.height = 'auto'
              scrollableContent.style.maxHeight = 'none'
            }
            const areaHorizontal = doc.querySelector('.calendar-area-horizontal') as HTMLElement
            if (areaHorizontal) {
              areaHorizontal.style.overflow = 'visible'
              areaHorizontal.style.height = 'auto'
            }
            const clonedMain = doc.querySelector('.edt-main') as HTMLElement
            if (clonedMain) {
              clonedMain.style.height = 'auto'
              clonedMain.style.overflow = 'visible'
            }
            
            // Ajuster les blocs de cours pour une meilleure lisibilité dans le PDF
            const lessonBlocks = doc.querySelectorAll('.lesson-block-horizontal')
            lessonBlocks.forEach((block: Element) => {
              const htmlBlock = block as HTMLElement
              htmlBlock.style.overflow = 'visible'
              htmlBlock.style.padding = '3px'
              
              // Ajuster le contenu du cours
              const content = htmlBlock.querySelector('.lesson-content-h') as HTMLElement
              if (content) {
                content.style.overflow = 'visible'
                content.style.gap = '1px'
                content.style.display = 'flex'
                content.style.flexDirection = 'column'
                content.style.justifyContent = 'flex-start'
              }
              
              // Ajuster les titres
              const titles = htmlBlock.querySelectorAll('.lesson-title-h')
              titles.forEach((title: Element) => {
                const htmlTitle = title as HTMLElement
                htmlTitle.style.fontSize = '12px'
                htmlTitle.style.lineHeight = '1.2'
                htmlTitle.style.whiteSpace = 'normal'
                htmlTitle.style.overflow = 'visible'
                htmlTitle.style.textOverflow = 'clip'
                htmlTitle.style.fontWeight = '700'
                htmlTitle.style.wordWrap = 'break-word'
              })
              
              // Ajuster les métadonnées (prof, salle)
              const metas = htmlBlock.querySelectorAll('.lesson-meta-h')
              metas.forEach((meta: Element) => {
                const htmlMeta = meta as HTMLElement
                htmlMeta.style.fontSize = '11px'
                htmlMeta.style.lineHeight = '1.2'
                htmlMeta.style.whiteSpace = 'normal'
                htmlMeta.style.overflow = 'visible'
                htmlMeta.style.textOverflow = 'clip'
                htmlMeta.style.wordWrap = 'break-word'
              })
            })
            
            // Ajuster aussi les blocs de cours pour la vue verticale
            const lessonBlocksVertical = doc.querySelectorAll('.lesson-block')
            lessonBlocksVertical.forEach((block: Element) => {
              const htmlBlock = block as HTMLElement
              htmlBlock.style.overflow = 'visible'
              htmlBlock.style.padding = '3px'
              
              const titles = htmlBlock.querySelectorAll('.lesson-title')
              titles.forEach((title: Element) => {
                const htmlTitle = title as HTMLElement
                htmlTitle.style.fontSize = '13px'
                htmlTitle.style.lineHeight = '1.3'
                htmlTitle.style.whiteSpace = 'normal'
                htmlTitle.style.overflow = 'visible'
                htmlTitle.style.textOverflow = 'clip'
                htmlTitle.style.fontWeight = '700'
                htmlTitle.style.wordWrap = 'break-word'
              })
              
              const metas = htmlBlock.querySelectorAll('.lesson-meta')
              metas.forEach((meta: Element) => {
                const htmlMeta = meta as HTMLElement
                htmlMeta.style.fontSize = '12px'
                htmlMeta.style.lineHeight = '1.3'
                htmlMeta.style.whiteSpace = 'normal'
                htmlMeta.style.overflow = 'visible'
                htmlMeta.style.textOverflow = 'clip'
                htmlMeta.style.wordWrap = 'break-word'
              })
            })
            
            // Ajuster les horaires de la vue horizontale
            const hourHeaders = doc.querySelectorAll('.hour-header')
            hourHeaders.forEach((header: Element) => {
              const htmlHeader = header as HTMLElement
              htmlHeader.style.fontSize = '9px'
              htmlHeader.style.fontWeight = '700'
              htmlHeader.style.color = '#000'
              htmlHeader.style.visibility = 'visible'
              htmlHeader.style.display = 'flex'
            })
            
            // Ajuster les horaires de la vue verticale
            const timeCells = doc.querySelectorAll('.cell.time')
            timeCells.forEach((cell: Element) => {
              const htmlCell = cell as HTMLElement
              htmlCell.style.fontSize = '11px'
              htmlCell.style.fontWeight = '600'
              htmlCell.style.color = '#374151'
              htmlCell.style.visibility = 'visible'
              htmlCell.style.display = 'block'
            })
            
            // S'assurer que le header row est visible (vue horizontale)
            const headerRow = doc.querySelector('.header-row') as HTMLElement
            if (headerRow) {
              headerRow.style.position = 'relative'
              headerRow.style.display = 'flex'
              headerRow.style.visibility = 'visible'
            }
            
            // S'assurer que le calendar-header est visible (vue verticale)
            const calendarHeader = doc.querySelector('.calendar-header') as HTMLElement
            if (calendarHeader) {
              calendarHeader.style.position = 'relative'
              calendarHeader.style.display = 'grid'
              calendarHeader.style.visibility = 'visible'
            }
          }
        }).then((canvas: HTMLCanvasElement) => {
          try {
            // Utiliser A3 paysage pour les grandes vues horizontales, A4 pour les vues verticales
            const format = isHorizontalView ? 'a3' : 'a4'
            const pdf = new jsPDF('l', 'mm', format)
            
            // Dimensions selon le format
            const pageWidth = isHorizontalView ? 420 : 297   // A3: 420mm, A4: 297mm
            const pageHeight = isHorizontalView ? 297 : 210   // A3: 297mm, A4: 210mm
            const sideMargin = 3
            const topTitleSpace = 15
            const bottomMargin = 3
            const maxWidth = pageWidth - (2 * sideMargin)
            const maxHeight = pageHeight - topTitleSpace - bottomMargin
            
            // Calculer les dimensions pour l'image
            const canvasRatio = canvas.width / canvas.height
            let imgWidth = maxWidth
            let imgHeight = imgWidth / canvasRatio
            
            // Si l'image est trop haute, ajuster
            if (imgHeight > maxHeight) {
              imgHeight = maxHeight
              imgWidth = imgHeight * canvasRatio
            }
            
            // Positionner l'image: centrée horizontalement, sous le titre
            const x = (pageWidth - imgWidth) / 2
            const y = topTitleSpace
            
            // Convertir en image PNG pour préserver la qualité
            const imgData = canvas.toDataURL('image/png', 1.0)
            pdf.addImage(imgData, 'PNG', x, y, imgWidth, imgHeight)
            
            const title = 'EDT Semaine ' + currentWeek.value
            const promo = selectedPromotion.value ? (promotions.value.find(p => p.id === selectedPromotion.value)?.name || '') : ''
            const grp = selectedGroup.value && selectedGroup.value !== 0 ? (groups.value.find(g => g.id === selectedGroup.value)?.name || '') : ''
            const subgrp = (selectedGroup.value && selectedGroup.value !== 0 && selectedSubgroup.value) ? ' - ' + selectedSubgroup.value : ''
            const fullTitle = title + (promo ? ' - ' + promo : '') + (grp ? ' - ' + grp : '') + subgrp
            
            pdf.setFontSize(14)
            pdf.text(fullTitle, pageWidth / 2, 8, { align: 'center' })
            
            pdf.save(fullTitle + '.pdf')
          } catch (err) {
            console.error('Erreur PDF:', err)
            alert('Erreur lors de la création du PDF')
          }
        }).catch((error: Error) => {
          console.error('Erreur capture:', error)
          alert('Erreur: ' + error.message)
        })
      }
      script2.onerror = function() {
        alert('Erreur: Impossible de charger jsPDF')
      }
      document.head.appendChild(script2)
    }
    script1.onerror = function() {
      alert('Erreur: Impossible de charger html2canvas')
    }
    document.head.appendChild(script1)
  } catch (error) {
    console.error('Erreur:', error)
    alert('Erreur lors de la génération du PDF')
  }
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
                <option :value="0">Tous</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
            </label>
            <label v-if="selectedGroup && selectedGroup !== 0" style="display:flex; align-items:center; gap:0.4rem;">
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
          <button class="btn primary" @click="handleGenerate" :disabled="isGenerating">Générer</button>
          <button v-if="selectedGroup && selectedGroup !== 0" class="btn primary" @click="$inertia.visit('/calendrier-previsionnel/edt/modifier')">Modifier</button>
          <button class="btn primary" @click="generatePDF">PDF</button>

        </div>
      </header>

      <main class="edt-main">
        <div v-if="isGenerating" style="flex:1; display:flex; align-items:center; justify-content:center; min-height:300px;">
          <div class="spinner-container">
            <svg class="spinner" width="60" height="60" viewBox="0 0 50 50">
              <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
            </svg>
            <div style="margin-top:1rem; font-size:1.1rem; color:#92400e;">Génération en cours...</div>
          </div>
        </div>
        <template v-else>
          <!-- Layout Vertical (groupe spécifique sélectionné) -->
          <section v-if="selectedGroup !== null && selectedGroup !== 0" class="calendar-area">
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
                  :class="{ blocked: isBlocked(d, t) }"
                >
                  <div
                    v-for="lesson in lessonsStartingAt(d, t)"
                    :key="lesson.id || lesson.start_min + '-' + lesson.room"
                    class="lesson-block"
                    :class="{ 'lesson-exam': lesson.isExam }"
                    :style="{ height: `${lesson.span * 40 + (lesson.span - 1) * 4}px`, background: lesson.color || 'linear-gradient(180deg,#fef3c7,#fde68a)', borderColor: lesson.color || '#f59e0b' }"
                  >
                    <div class="lesson-title">{{ lesson.title }}</div>
                    <div class="lesson-meta" v-if="lesson.type !== 'TP' && lesson.type !== 'TD'">{{ lesson.teacher }} · {{ lesson.room }}</div>
                    <div class="lesson-meta" v-if="lesson.type === 'TP' || lesson.type === 'TD'">{{ lesson.teacher }}</div>
                    <div class="lesson-meta" v-if="lesson.type === 'TP' || lesson.type === 'TD'">{{ lesson.room }}</div>
                  </div>
                  <div v-if="isCovered(d, t) && lessonsStartingAt(d, t).length === 0" class="covered-slot"></div>
                </div>
              </div>
            </div>
          </section>

          <section v-else class="calendar-area-horizontal">
            <div class="horizontal-wrapper">
              <div class="header-row">
                <div class="hour-header-placeholder" style="width:80px;"></div>
                <div class="hour-header-placeholder" style="width:80px;"></div>
                <div class="hour-header-placeholder" style="width:50px;"></div>
                <div class="hours-container">
                  <div class="hour-header" v-for="t in timeSlots" :key="t">{{ formatTime(t) }}</div>
                </div>
              </div>

              <div class="content-wrapper">
                <div class="scrollable-content">
                  <div class="day-section" v-for="dayIdx in 6" :key="dayIdx">
                    <div class="day-label-column">
                      <div class="day-name-cell" v-if="dayIdx === 1">Lundi</div>
                      <div class="day-name-cell" v-else-if="dayIdx === 2">Mardi</div>
                      <div class="day-name-cell" v-else-if="dayIdx === 3">Mercredi</div>
                      <div class="day-name-cell" v-else-if="dayIdx === 4">Jeudi</div>
                      <div class="day-name-cell" v-else-if="dayIdx === 5">Vendredi</div>
                      <div class="day-name-cell" v-else>Samedi</div>
                    </div>

                    <div class="groups-label-column">
                      <template v-for="(groupItem, gIdx) in groupsWithSubgroups" :key="gIdx">
                        <div 
                          class="group-label-item" 
                          v-if="groupItem.subgroups.length > 0"
                          :style="{ 
                            height: `${groupItem.subgroups.length * 30}px`,
                            borderBottom: '1px dashed #e5e7eb'
                          }"
                        >
                          {{ groupItem.name }}
                        </div>
                      </template>
                    </div>

                    <div class="subgroups-label-column">
                      <template v-for="(groupItem, gIdx) in groupsWithSubgroups" :key="gIdx">
                        <div 
                          class="subgroup-label-item" 
                          v-for="(sub, subIdx) in groupItem.subgroups" 
                          :key="sub"
                          :style="{ borderBottom: '1px dashed #e5e7eb' }"
                        >
                          {{ sub }}
                        </div>
                      </template>
                    </div>

                    <div class="day-grid-column">
                      <template v-for="(groupItem, gIdx) in groupsWithSubgroups" :key="gIdx">
                        <div 
                          class="subgroup-row" 
                          v-for="(sub, subIdx) in groupItem.subgroups" 
                          :key="sub"
                          :style="{ borderBottom: '1px dashed #e5e7eb' }"
                        >
                          <div
                            class="cell-slot"
                            v-for="t in timeSlots"
                            :key="t"
                            :class="{ blocked: isBlocked(dayIdx, t, groupItem.id, sub) }"
                          >
                            <div
                              v-for="lesson in lessonsStartingAt(dayIdx, t).filter(l => shouldDisplayLessonInCell(l, gIdx, subIdx, groupsWithSubgroups))"
                              :key="lesson.id || lesson.start_min + '-' + lesson.room"
                              class="lesson-block-horizontal"
                              :class="{ 'lesson-exam': lesson.isExam }"
                              :style="{ 
                                width: `calc(${lesson.span} * 60px - 4px)`,
                                height: `${Math.min(getRowSpan(lesson, groupsWithSubgroups, gIdx, subIdx), groupsWithSubgroups.reduce((sum, g, idx) => idx >= gIdx ? sum + g.subgroups.length : sum, 0)) * 30 - 4}px`,
                                background: lesson.color || 'linear-gradient(180deg,#fef3c7,#fde68a)', 
                                borderColor: lesson.color || '#f59e0b' 
                              }"
                            >
                              <div class="lesson-content-h" :class="{ 'lesson-tp': lesson.type === 'TP', 'lesson-td': lesson.type === 'TD' }">
                                <div class="lesson-title-h" v-if="lesson.type !== 'TP' && lesson.type !== 'TD'">{{ lesson.title }}</div>
                                <div class="lesson-meta-h lesson-inline" v-if="lesson.type === 'TP'"><span class="lesson-title-h">{{ lesson.title }}</span> · {{ lesson.teacher }} · {{ lesson.room }}</div>
                                <div class="lesson-title-h" v-if="lesson.type === 'TD'">{{ lesson.title }}</div>
                                <div class="lesson-meta-h" v-if="lesson.type !== 'TP' && lesson.type !== 'TD'">{{ lesson.teacher }}</div>
                                <div class="lesson-meta-h" v-if="lesson.type !== 'TP' && lesson.type !== 'TD'">{{ lesson.room }}</div>
                                <div class="lesson-meta-h lesson-inline" v-if="lesson.type === 'TD'">{{ lesson.teacher }} · {{ lesson.room }}</div>
                              </div>
                            </div>
                            <div v-if="isCovered(dayIdx, t, groupItem.id, sub) && lessonsStartingAt(dayIdx, t).filter(l => shouldDisplayLessonInCell(l, gIdx, subIdx, groupsWithSubgroups)).length === 0" class="covered-slot"></div>
                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </template>
      </main>
    </div>
  </AdminLayout>
</template>

<style scoped>
.edt-container { padding: 0.5rem; }
.edt-toolbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:0.5rem; flex-wrap:wrap; }
.edt-toolbar .title { margin:0; font-size:1.25rem }
.controls { display:flex; gap:0.75rem; margin-left:1rem; align-items:center; flex-wrap:wrap; }
.controls select { margin-left:0.4rem }
.right { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; }
.arrow.button { display: flex; justify-content: center; align-items: center; cursor: pointer; background:#FFD8E4; color: black; filter: brightness(100%); transition: filter 0.3s; padding: 0.5rem; border-radius: 50%; }
.arrow.button:hover { filter: brightness(75%); }
.btn { padding:0.4rem 0.6rem; border:1px solid #d1d5db; background:#fff; border-radius:6px; cursor:pointer; font-size:0.9rem; }
.btn.primary { background:#FFD8E4; color:#000000; border-color:transparent }
.edt-main { display:flex; gap:1rem; height: calc(100vh - 230px); }
.filters { width:200px; border:1px solid #e5e7eb; padding:0.75rem; border-radius:6px; }
.calendar-area { flex:1; overflow:auto; padding-bottom:3rem; display:flex; flex-direction:column; }
.calendar-area-horizontal { flex:1; display:flex; flex-direction:column; overflow:hidden; padding-bottom:3rem; }
.horizontal-wrapper { display:flex; flex-direction:column; height:100%; overflow:auto; }
.header-row { display:flex; position:sticky; top:0; z-index:30; background:white; border-bottom:1px dashed #e5e7eb; }
.hour-header-placeholder { min-width:auto; background:#f3f4f6; border-right:1px dashed #e5e7eb; flex-shrink:0; }
.hours-container { display:flex; flex:1; min-width:max-content; overflow:hidden; }
.hour-header { min-width:60px; width:60px; height:50px; background:#f3f4f6; border-right:1px dashed #e5e7eb; display:flex; align-items:center; justify-content:flex-start; padding-left:4px; font-size:0.7rem; font-weight:600; flex-shrink:0; }
.content-wrapper { flex:1; width:100%; min-width:max-content; }
.scrollable-content { display:flex; flex-direction:column; gap:0; min-width:max-content; }
.day-section { display:flex; gap:0; border-bottom:1px dashed #e5e7eb; padding-bottom:4px; }
.day-label-column { width:80px; min-width:80px; display:flex; flex-direction:column; border-right:1px dashed #e5e7eb; flex-shrink:0; }
.day-name-cell { height:30px; background:#f3f4f6; font-weight:700; font-size:0.9rem; display:flex; align-items:center; justify-content:center; border-bottom:none; border-radius:4px; }
.groups-label-column { width:80px; min-width:80px; display:flex; flex-direction:column; border-right:1px dashed #e5e7eb; flex-shrink:0; }
.group-label-item { background:#f5f5f5; font-weight:600; font-size:0.8rem; display:flex; align-items:center; justify-content:center; }
.subgroups-label-column { width:50px; min-width:50px; display:flex; flex-direction:column; border-right:1px dashed #e5e7eb; flex-shrink:0; }
.subgroup-label-item { height:30px; background:#fafafa; font-size:0.75rem; display:flex; align-items:center; justify-content:center; font-weight:600; }
.day-grid-column { display:flex; flex-direction:column; flex-shrink:0; }
.group-row-main { display:flex; height:30px; border-bottom:1px solid #ddd; }
.subgroup-row { display:flex; height:30px; position:relative; }
.cell-slot { min-width:60px; width:60px; border-right:1px dashed #e5e7eb; position:relative; background:#fff; flex-shrink:0; }
.cell-slot.blocked {
  background: #ff9d9d;
  border-style: solid;
  border-color: #ff6262;
  opacity: 0.7;
  pointer-events: none;
}
.lesson-block-horizontal { position:absolute; left:2px; top:2px; box-sizing:border-box; padding:0.25rem; font-size:0.85rem; border:1px solid; border-radius:6px; overflow:hidden; z-index:5; display:flex; flex-direction:column; gap:0.15rem; }
.lesson-block-horizontal.lesson-exam { border-width:2px; box-shadow:0 0 6px rgba(0, 0, 0, 0.2); }
.lesson-content-h { display:flex; flex-direction:column; gap:0.1rem; overflow:hidden; }
.lesson-content-h.lesson-tp,
.lesson-content-h.lesson-td { gap:0.05rem; }
.lesson-title-h { font-weight:600; font-size:0.8rem; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#92400e; }
.lesson-meta-h { font-size:0.75rem; font-weight:400; opacity:0.95; line-height:1.2; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#7c2d12; }
.lesson-meta-h.lesson-inline { font-size:0.7rem; line-height:1.1; }
.calendar-header { display:grid; grid-template-columns: 80px repeat(6,minmax(0,1fr)); gap:0.5rem; margin-bottom:0.5rem; position:sticky; top:0; z-index:20; background:transparent; width:100%; }
.calendar-header .time-header { height: 100%; }
.calendar-header .day {
  text-align: center;
  background: transparent; /* enlever le fond gris */
  border-radius: 0;        /* enlever les coins arrondis */
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 32px;
  padding: 0;
  font-weight: 600;
}
.calendar-grid { display:grid; gap:0.25rem; width:100%; }
.calendar-grid .row { display:grid; grid-template-columns:80px repeat(6,minmax(0,1fr)); align-items:start; width:100%; }
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
.lesson-block.lesson-exam {
  border-width: 2px;
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
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