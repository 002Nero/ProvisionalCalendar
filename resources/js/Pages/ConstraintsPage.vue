<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, Ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { useEdtStore } from '@/stores/edtStore'

const activeTab = ref<'salles'|'profs'|'groupes'>('salles')
const filter = ref('')

const rooms = ref<{id:number;name:string}[]>([])
const teachers = ref<{id:number;name:string}[]>([])
const promotions = ref<{id:number;name:string}[]>([])
const groups = ref<{id:number;name:string;promotionId:number}[]>([])
const subgroupsByGroup: Record<number, string[]> = {
  0: ['A','B']
}
// Special ID for applying constraints to all groups in a promotion
const ALL_GROUP_ID = -1

type Constraint = { id: number; text: string; room?: string; teacher?: string; promotionId?: number; groupId?: number; subgroup?: string; week?: number; day?: string; startTime?: string; endTime?: string; repeatWeekly?: boolean }

const roomConstraints = ref<Constraint[]>([
  { id: 1, text: 'Salle indisponible', room: 'R.50', week: 42, day: 'mardi', startTime: '10:00', endTime: '12:00', repeatWeekly: false }
])
const teacherConstraints = ref<Constraint[]>([
  { id: 1, text: 'Congé', teacher: 'M. Dubreuil', week: 42, day: 'jeudi', startTime: '09:00', endTime: '17:00', repeatWeekly: false }
])
const groupConstraints = ref<Constraint[]>([
  { id: 1, text: 'Pas de cours', promotionId: 1, groupId: 1, subgroup: 'A', week: 42, day: 'vendredi', startTime: '08:00', endTime: '12:00', repeatWeekly: true }
])

let nextId = 10

const edt = useEdtStore()

async function loadRooms() {
  try {
    const res = await axios.get('/api/rooms')
    const data = Array.isArray(res.data) ? res.data : []
    rooms.value = data.map((r: any) => ({ id: r.id, name: r.name }))
    if (rooms.value.length > 0 && !newRoomSel.value) newRoomSel.value = rooms.value[0].id
  } catch (e) {
    console.warn('Could not load rooms', e)
  }
}

async function loadPromotions(yearId: number) {
  try {
    const res = await axios.get(`/api/promotions/${yearId}`)
    promotions.value = Array.isArray(res.data) ? res.data : []
    if (promotions.value.length > 0 && !newGroupPromo.value) newGroupPromo.value = promotions.value[0].id
  } catch (e) {
    console.warn('Could not load promotions', e)
  }
}

async function loadGroupsForPromotion(promoId: number | undefined) {
  if (!promoId) return
  try {
    const res = await axios.get(`/api/groups/${promoId}`)
    groups.value = Array.isArray(res.data) ? res.data.map((g: any) => ({ id: g.id, name: g.name, promotionId: promoId })) : []
    groups.value.forEach(g => { if (!subgroupsByGroup[g.id]) subgroupsByGroup[g.id] = ['A','B'] })
    if (groups.value.length > 0 && !newGroupGroupId.value) newGroupGroupId.value = ALL_GROUP_ID
  } catch (e) {
    console.warn('Could not load groups', e)
  }
}

async function loadTeachers(yearId: number) {
  try {
    const res = await axios.get(`/api/teachers/${yearId}`)
    const data = Array.isArray(res.data) ? res.data : []
    teachers.value = data.map((t: any) => ({ id: t.id, name: (`${t.last_name ?? ''} ${t.first_name ?? ''}`.trim() || t.code || `T${t.id}`) }))
    if (teachers.value.length > 0 && !newTeacherSel.value) newTeacherSel.value = teachers.value[0].id
  } catch (e) {
    console.warn('Could not load teachers', e)
  }
}

async function ensureDataLoaded() {
  let yearId: number | null = null
  if (typeof edt.year === 'number') yearId = edt.year
  else if (typeof edt.year === 'string' && /^[0-9]+$/.test(edt.year)) yearId = parseInt(edt.year,10)
  if (!yearId) {
    try {
      const yrs = await axios.get('/api/years')
      const arr = Array.isArray(yrs.data) ? yrs.data : []
      if (arr.length > 0) yearId = arr[0].id
    } catch (e) {
      console.warn('Could not resolve year', e)
    }
  }

  await Promise.all([
    loadRooms(),
    yearId ? loadPromotions(yearId) : Promise.resolve(),
    yearId ? loadTeachers(yearId) : Promise.resolve()
  ])
  if (newGroupPromo.value) await loadGroupsForPromotion(newGroupPromo.value)
  await loadConstraints()
}

onMounted(() => { ensureDataLoaded() })

async function loadConstraints() {
  try {
    const [rRes, tRes, gRes] = await Promise.all([
      axios.get('/api/room-constraints'),
      axios.get('/api/teacher-constraints'),
      axios.get('/api/group-constraints')
    ])
      const rr = Array.isArray(rRes.data) ? rRes.data : []
      roomConstraints.value = rr.map((r: any) => ({ id: r.id, text: r.reason || r.constraint_type, room: (rooms.value.find(x => x.id === r.room_id)?.name) ?? String(r.room_id), week: r.week_id ?? undefined, day: r.day_of_week, startTime: r.start_time, endTime: r.end_time, repeatWeekly: (r.week_id == null) }))
      const tt = Array.isArray(tRes.data) ? tRes.data : []
      teacherConstraints.value = tt.map((s: any) => ({ id: s.id, text: s.reason || s.constraint_type, teacher: (teachers.value.find(x => x.id === s.teacher_id)?.name) ?? String(s.teacher_id), week: s.week_id ?? undefined, day: s.day_of_week, startTime: s.start_time, endTime: s.end_time, repeatWeekly: (s.week_id == null) }))
      const gg = Array.isArray(gRes.data) ? gRes.data : []
      groupConstraints.value = gg.map((s: any) => ({ id: s.id, text: s.reason || s.constraint_type, promotionId: undefined, groupId: s.group_id, subgroup: undefined, week: s.week_id ?? undefined, day: s.day_of_week, startTime: s.start_time, endTime: s.end_time, repeatWeekly: (s.week_id == null) }))
  } catch (e) {
    console.warn('Failed to load constraints', e)
  }
}


function addRoomConstraint() {
  const text = newRoomText.value?.trim()
  if (!text) return
  ;(async () => {
    try {
      if (!newRoomSel.value || !rooms.value.find(r => r.id === newRoomSel.value)) {
        alert('Veuillez sélectionner une salle valide avant de sauvegarder.')
        return
      }
      const payload = {
        room_id: newRoomSel.value,
        constraint_type: 'unavailable',
        day_of_week: newRoomDay.value ?? null,
        start_time: newRoomStart.value ?? null,
        end_time: newRoomEnd.value ?? null,
        reason: text,
        week_id: newRoomRepeat.value ? null : (newRoomWeek.value ?? null),
        priority: 'hard',
        active: true
      }
      const res = await axios.post('/api/room-constraints', payload)
      const id = res.data?.id ?? nextId++
          const roomName = rooms.value.find(r => r.id === newRoomSel.value)?.name ?? String(newRoomSel.value ?? '')
      roomConstraints.value.push({ id, text, room: roomName, week: newRoomWeek.value, day: newRoomDay.value, startTime: newRoomStart.value, endTime: newRoomEnd.value, repeatWeekly: newRoomRepeat.value })
      newRoomText.value = ''
      newRoomWeek.value = undefined
      newRoomDay.value = undefined
      newRoomStart.value = undefined
      newRoomEnd.value = undefined
      newRoomRepeat.value = false
    } catch (e: any) {
      console.error('Failed to save room constraint', e?.response?.data ?? e)
      alert('Échec sauvegarde contrainte salle: ' + (e?.response?.data?.message || e?.message || 'Erreur serveur'))
    }
  })()
}
const newTeacherWeek = ref<number | undefined>(undefined)
const newTeacherDay = ref<string | undefined>(undefined)
const newTeacherStart = ref<string | undefined>(undefined)
const newTeacherEnd = ref<string | undefined>(undefined)
const newTeacherRepeat = ref<boolean>(false)

function addTeacherConstraint() {
  const text = newTeacherText.value?.trim()
  if (!text) return
  ;(async () => {
    try {
      const payload = {
        teacher_id: newTeacherSel.value ?? null,
        constraint_type: 'unavailable',
        day_of_week: newTeacherDay.value ?? null,
        start_time: newTeacherStart.value ?? null,
        end_time: newTeacherEnd.value ?? null,
        reason: text,
        week_id: newTeacherRepeat.value ? null : (newTeacherWeek.value ?? null),
        priority: 'hard',
        active: true
      }
      if (!newTeacherSel.value || !teachers.value.find(t => t.id === newTeacherSel.value)) {
        alert('Veuillez sélectionner un professeur valide avant de sauvegarder.')
        return
      }
      const res = await axios.post('/api/teacher-constraints', payload)
      const id = res.data?.id ?? nextId++
      const teacherName = teachers.value.find(t => t.id === newTeacherSel.value)?.name ?? String(newTeacherSel.value ?? '')
      teacherConstraints.value.push({ id, text, teacher: teacherName, week: newTeacherWeek.value, day: newTeacherDay.value, startTime: newTeacherStart.value, endTime: newTeacherEnd.value, repeatWeekly: newTeacherRepeat.value })
      newTeacherText.value = ''
      newTeacherWeek.value = undefined
      newTeacherDay.value = undefined
      newTeacherStart.value = undefined
      newTeacherEnd.value = undefined
      newTeacherRepeat.value = false
    } catch (e: any) {
      console.error('Failed to save teacher constraint', e?.response?.data ?? e)
      alert('Échec sauvegarde contrainte professeur: ' + (e?.response?.data?.message || e?.message || 'Erreur serveur'))
    }
  })()
}
function addGroupConstraint() {
  const text = newGroupText.value?.trim()
  if (!text) return
  ;(async () => {
    try {
      const isAll = newGroupGroupId.value === ALL_GROUP_ID
      const targetGroups = isAll ? groups.value.filter(g => g.promotionId === (newGroupPromo.value ?? g.promotionId)) : groups.value.filter(g => g.id === newGroupGroupId.value)
      if (targetGroups.length === 0) {
        alert('Veuillez sélectionner une promotion et un groupe valides avant de sauvegarder.')
        return
      }
      for (const g of targetGroups) {
        const payload = {
          group_id: g.id,
          constraint_type: 'unavailable',
          day_of_week: newGroupDay.value ?? null,
          start_time: newGroupStart.value ?? null,
          end_time: newGroupEnd.value ?? null,
          reason: text,
          week_id: newGroupRepeat.value ? null : (newGroupWeek.value ?? null),
          priority: 'hard',
          active: true
        }
        const res = await axios.post('/api/group-constraints', payload)
        const id = res.data?.id ?? nextId++
        groupConstraints.value.push({ id, text, promotionId: newGroupPromo.value, groupId: g.id, subgroup: isAll ? 'Tous' : newGroupSubgroup.value, week: newGroupWeek.value, day: newGroupDay.value, startTime: newGroupStart.value, endTime: newGroupEnd.value, repeatWeekly: newGroupRepeat.value })
      }
      newGroupText.value = ''
      newGroupWeek.value = undefined
      newGroupDay.value = undefined
      newGroupStart.value = undefined
      newGroupEnd.value = undefined
      newGroupRepeat.value = false
    } catch (e: any) {
      console.error('Failed to save group constraint', e?.response?.data ?? e)
      alert('Échec sauvegarde contrainte groupe: ' + (e?.response?.data?.message || e?.message || 'Erreur serveur'))
    }
  })()
}

function removeConstraint(list: Ref<Constraint[]> | Constraint[], id: number) {
  const arr = Array.isArray(list) ? list : (list as Ref<Constraint[]>).value
  const idx = arr.findIndex(c => c.id === id)
  if (idx === -1) return
  ;(async () => {
    try {
      if (arr === roomConstraints.value) {
        await axios.delete(`/api/room-constraints/${id}`)
      } else if (arr === teacherConstraints.value) {
        await axios.delete(`/api/teacher-constraints/${id}`)
      } else if (arr === groupConstraints.value) {
        await axios.delete(`/api/group-constraints/${id}`)
      }
      arr.splice(idx, 1)
    } catch (e) {
      console.error('Failed to delete constraint', e)
      alert('Échec suppression contrainte')
    }
  })()
}

const editingRoomId = ref<number | null>(null)
const editRoomText = ref('')
const editRoomSel = ref<number | undefined>(undefined)
const editRoomWeek = ref<number | undefined>(undefined)
const editRoomDay = ref<string | undefined>(undefined)
const editRoomStart = ref<string | undefined>(undefined)
const editRoomEnd = ref<string | undefined>(undefined)
const editRoomRepeat = ref<boolean>(false)

const editingTeacherId = ref<number | null>(null)
const editTeacherText = ref('')
const editTeacherSel = ref<number | undefined>(undefined)
const editTeacherWeek = ref<number | undefined>(undefined)
const editTeacherDay = ref<string | undefined>(undefined)
const editTeacherStart = ref<string | undefined>(undefined)
const editTeacherEnd = ref<string | undefined>(undefined)
const editTeacherRepeat = ref<boolean>(false)

const editingGroupId = ref<number | null>(null)
const editGroupText = ref('')
const editGroupPromo = ref<number | undefined>(undefined)
const editGroupGroupId = ref<number | undefined>(undefined)
const editGroupSubgroup = ref<string | undefined>(undefined)
const editGroupWeek = ref<number | undefined>(undefined)
const editGroupDay = ref<string | undefined>(undefined)
const editGroupStart = ref<string | undefined>(undefined)
const editGroupEnd = ref<string | undefined>(undefined)
const editGroupRepeat = ref<boolean>(false)

function startEditRoom(id: number) {
  const c = roomConstraints.value.find(x => x.id === id)
  if (!c) return
  editingRoomId.value = id
  editRoomText.value = c.text
  editRoomSel.value = rooms.value.find(r => r.name === c.room)?.id
  editRoomWeek.value = c.week
  editRoomDay.value = c.day
  editRoomStart.value = c.startTime
  editRoomEnd.value = c.endTime
  editRoomRepeat.value = !!c.repeatWeekly
}
function saveRoomEdit(id: number) {
  const c = roomConstraints.value.find(x => x.id === id)
  if (!c) return
  ;(async () => {
    try {
      const payload = {
        room_id: editRoomSel.value ?? null,
        constraint_type: 'unavailable',
        day_of_week: editRoomDay.value ?? null,
        start_time: editRoomStart.value ?? null,
        end_time: editRoomEnd.value ?? null,
        reason: editRoomText.value ?? c.text,
        week_id: editRoomRepeat.value ? null : (editRoomWeek.value ?? null),
        priority: 'hard',
        active: true
      }
      await axios.put(`/api/room-constraints/${id}`, payload)
      c.text = editRoomText.value || c.text
      c.room = rooms.value.find(r => r.id === editRoomSel.value)?.name ?? c.room
      c.week = editRoomWeek.value
      c.day = editRoomDay.value
      c.startTime = editRoomStart.value
      c.endTime = editRoomEnd.value
      c.repeatWeekly = editRoomRepeat.value
      editingRoomId.value = null
    } catch (e) {
      console.error('Failed to update room constraint', e)
      alert('Échec mise à jour contrainte salle')
    }
  })()
}
function cancelRoomEdit() {
  editingRoomId.value = null
}

function startEditTeacher(id: number) {
  const c = teacherConstraints.value.find(x => x.id === id)
  if (!c) return
  editingTeacherId.value = id
  editTeacherText.value = c.text
  editTeacherSel.value = teachers.value.find(t => t.name === c.teacher)?.id
  editTeacherWeek.value = c.week
  editTeacherDay.value = c.day
  editTeacherStart.value = c.startTime
  editTeacherEnd.value = c.endTime
  editTeacherRepeat.value = !!c.repeatWeekly
}
function saveTeacherEdit(id: number) {
  const c = teacherConstraints.value.find(x => x.id === id)
  if (!c) return
  ;(async () => {
    try {
      if (!editTeacherSel.value || !teachers.value.find(t => t.id === editTeacherSel.value)) {
        alert('Veuillez sélectionner un professeur valide avant d\'enregistrer.')
        return
      }
      const payload = {
        teacher_id: editTeacherSel.value,
        constraint_type: 'unavailable',
        day_of_week: editTeacherDay.value ?? null,
        start_time: editTeacherStart.value ?? null,
        end_time: editTeacherEnd.value ?? null,
        reason: editTeacherText.value ?? c.text,
        week_id: editTeacherRepeat.value ? null : (editTeacherWeek.value ?? null),
        priority: 'hard',
        active: true
      }
      const res = await axios.put(`/api/teacher-constraints/${id}`, payload)
      if (res.status !== 200) throw new Error('Unexpected response ' + res.status)
      c.text = editTeacherText.value || c.text
      c.teacher = teachers.value.find(t => t.id === editTeacherSel.value)?.name ?? c.teacher
      c.week = editTeacherWeek.value
      c.day = editTeacherDay.value
      c.startTime = editTeacherStart.value
      c.endTime = editTeacherEnd.value
      c.repeatWeekly = editTeacherRepeat.value
      editingTeacherId.value = null
    } catch (e: any) {
      console.error('Failed to update teacher constraint', e?.response?.data ?? e)
      const msg = e?.response?.data?.message || e?.response?.data || e?.message || 'Erreur serveur'
      alert('Échec mise à jour contrainte professeur: ' + msg)
    }
  })()
}
function cancelTeacherEdit() { editingTeacherId.value = null }

function startEditGroup(id: number) {
  const c = groupConstraints.value.find(x => x.id === id)
  if (!c) return
  editingGroupId.value = id
  editGroupText.value = c.text
  editGroupPromo.value = c.promotionId ?? promotions.value[0]?.id
  editGroupGroupId.value = c.groupId
  editGroupSubgroup.value = c.subgroup
  editGroupWeek.value = c.week
  editGroupDay.value = c.day
  editGroupStart.value = c.startTime
  editGroupEnd.value = c.endTime
  editGroupRepeat.value = !!c.repeatWeekly
}
function saveGroupEdit(id: number) {
  const c = groupConstraints.value.find(x => x.id === id)
  if (!c) return
  ;(async () => {
    try {
      const payload = {
        group_id: editGroupGroupId.value ?? null,
        constraint_type: 'unavailable',
        day_of_week: editGroupDay.value ?? null,
        start_time: editGroupStart.value ?? null,
        end_time: editGroupEnd.value ?? null,
        reason: editGroupText.value ?? c.text,
        week_id: editGroupRepeat.value ? null : (editGroupWeek.value ?? null),
        priority: 'hard',
        active: true
      }
      await axios.put(`/api/group-constraints/${id}`, payload)
      c.text = editGroupText.value || c.text
      c.promotionId = editGroupPromo.value
      c.groupId = editGroupGroupId.value
      c.subgroup = editGroupSubgroup.value
      c.week = editGroupWeek.value
      c.day = editGroupDay.value
      c.startTime = editGroupStart.value
      c.endTime = editGroupEnd.value
      c.repeatWeekly = editGroupRepeat.value
      editingGroupId.value = null
    } catch (e) {
      console.error('Failed to update group constraint', e)
      alert('Échec mise à jour contrainte groupe')
    }
  })()
}
function cancelGroupEdit() { editingGroupId.value = null }

watch(editGroupPromo, async (val) => {
  await loadGroupsForPromotion(val)
  const first = groups.value.find(g => g.promotionId === val)
  editGroupGroupId.value = first?.id
})
watch(editGroupGroupId, (val) => {
  // Default to 'Tous' regardless of group; user can pick A/B
  editGroupSubgroup.value = 'Tous'
})

const newRoomWeek = ref<number | undefined>(undefined)
const newRoomDay = ref<string | undefined>(undefined)
const newRoomStart = ref<string | undefined>(undefined)
const newRoomEnd = ref<string | undefined>(undefined)
const newRoomRepeat = ref<boolean>(false)

const newGroupWeek = ref<number | undefined>(undefined)
const newGroupDay = ref<string | undefined>(undefined)
const newGroupStart = ref<string | undefined>(undefined)
const newGroupEnd = ref<string | undefined>(undefined)
const newGroupRepeat = ref<boolean>(false)

const newRoomText = ref('')
const newRoomSel = ref<number | undefined>(rooms.value[0]?.id)
const newTeacherText = ref('')
const newTeacherSel = ref<number | undefined>(teachers.value[0]?.id)
const newGroupText = ref('')
const newGroupPromo = ref<number | undefined>(promotions.value[0]?.id)
const newGroupGroupId = ref<number | undefined>(groups.value.find(g => g.promotionId === newGroupPromo.value)?.id)
const newGroupSubgroup = ref<string | undefined>('Tous')

// removed unused newGroupSel

const filteredRoomConstraints = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return roomConstraints.value
  return roomConstraints.value.filter(c => {
    const text = c.text.toLowerCase()
    const room = (c.room||'').toLowerCase()
    const day = (c.day||'').toLowerCase()
    const week = c.week ? String(c.week) : ''
    const times = ((c.startTime||'') + ' ' + (c.endTime||'')).toLowerCase()
    const repeat = c.repeatWeekly ? 'repeat' : ''
    return text.includes(q) || room.includes(q) || day.includes(q) || week.includes(q) || times.includes(q) || repeat.includes(q)
  })
})

const filteredTeacherConstraints = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return teacherConstraints.value
  return teacherConstraints.value.filter(c => {
    const text = c.text.toLowerCase()
    const teacher = (c.teacher||'').toLowerCase()
    const day = (c.day||'').toLowerCase()
    const week = c.week ? String(c.week) : ''
    const times = ((c.startTime||'') + ' ' + (c.endTime||'')).toLowerCase()
    return text.includes(q) || teacher.includes(q) || day.includes(q) || week.includes(q) || times.includes(q)
  })
})

const filteredGroupConstraints = computed(() => {
  const q = filter.value.trim().toLowerCase()
  if (!q) return groupConstraints.value
  return groupConstraints.value.filter(c => {
    const groupName = c.groupId ? (groups.value.find(g => g.id === c.groupId)?.name || '') : ''
    const day = (c.day||'').toLowerCase()
    const week = c.week ? String(c.week) : ''
    const times = ((c.startTime||'') + ' ' + (c.endTime||'')).toLowerCase()
    const repeat = c.repeatWeekly ? 'repeat' : ''
    return c.text.toLowerCase().includes(q) || groupName.toLowerCase().includes(q) || (c.subgroup || '').toLowerCase().includes(q) || day.includes(q) || week.includes(q) || times.includes(q) || repeat.includes(q)
  })
})

watch(newGroupPromo, async (val) => {
  await loadGroupsForPromotion(val)
  // Default to 'Tous' to allow promo-wide constraints
  newGroupGroupId.value = ALL_GROUP_ID
})
watch(newGroupGroupId, (val) => {
  // Default to 'Tous' regardless of group; user can pick A/B
  newGroupSubgroup.value = 'Tous'
})
</script>

<template>
  <AdminLayout>
    <div class="constraints-wrap">
      <header class="constraints-header">
        <h1>Contraintes</h1>
        <div class="controls">
          <input v-model="filter" placeholder="Rechercher une contrainte..." class="input" />
        </div>
      </header>

      <nav class="tabs">
        <button :class="['tab', {active: activeTab === 'salles'}]" @click="activeTab = 'salles'">Salles</button>
        <button :class="['tab', {active: activeTab === 'profs'}]" @click="activeTab = 'profs'">Professeurs</button>
        <button :class="['tab', {active: activeTab === 'groupes'}]" @click="activeTab = 'groupes'">Groupes</button>
      </nav>

      <section class="tab-content">
        <div v-if="activeTab === 'salles'" class="tab-section">
          <form @submit.prevent="addRoomConstraint" class="add-form">
            <input v-model="newRoomText" placeholder="Nouvelle contrainte (ex: salle indisponible)" class="input" />
            <select v-model="newRoomSel" class="input small">
              <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <input v-model.number="newRoomWeek" type="number" min="1" placeholder="Semaine" class="input small" />
            <select v-model="newRoomDay" class="input small">
              <option disabled value="">Jour</option>
              <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
            </select>
            <input v-model="newRoomStart" type="time" class="input small" />
            <input v-model="newRoomEnd" type="time" class="input small" />
            <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="newRoomRepeat" /> Répéter chaque semaine</label>
            <button class="btn primary" type="submit">Ajouter</button>
          </form>
          <div class="list-container">
          <ul class="list">
            <li v-for="c in filteredRoomConstraints" :key="c.id" class="list-item">
              <div class="li-main">
                <template v-if="editingRoomId === c.id">
                  <input v-model="editRoomText" class="input" />
                  <select v-model="editRoomSel" class="input small">
                    <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.name }}</option>
                  </select>
                  <input v-model.number="editRoomWeek" type="number" min="1" placeholder="Semaine" class="input small" />
                  <select v-model="editRoomDay" class="input small">
                    <option disabled value="">Jour</option>
                    <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
                  </select>
                  <input v-model="editRoomStart" type="time" class="input small" />
                  <input v-model="editRoomEnd" type="time" class="input small" />
                  <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="editRoomRepeat" /> Répéter chaque semaine</label>
                </template>
                <template v-else>
                  <div class="li-text">{{ c.text }}</div>
                  <div class="li-meta">Salle: <strong>{{ c.room || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Semaine: <strong>{{ c.week ?? '-' }}</strong>
                    &nbsp;•&nbsp;
                    Jour: <strong>{{ c.day || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Horaire: <strong>{{ c.startTime || '-' }} - {{ c.endTime || '-' }}</strong>
                    &nbsp;•&nbsp;
                    <strong>{{ c.repeatWeekly ? 'Répété chaque semaine' : 'Ponctuel' }}</strong>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:0.4rem;align-items:center">
                <template v-if="editingRoomId === c.id">
                  <button class="btn primary" @click="saveRoomEdit(c.id)">Enregistrer</button>
                  <button class="del" @click="cancelRoomEdit">Annuler</button>
                </template>
                <template v-else>
                  <button class="btn" @click="startEditRoom(c.id)">Modifier</button>
                  <button class="del" @click="removeConstraint(roomConstraints, c.id)" title="Supprimer">Supprimer</button>
                </template>
              </div>
            </li>
          </ul>
          </div>
        </div>

        <div v-if="activeTab === 'profs'" class="tab-section">
          <form @submit.prevent="addTeacherConstraint" class="add-form">
            <input v-model="newTeacherText" placeholder="Nouvelle contrainte (ex: indisponible)" class="input" />
            <select v-model="newTeacherSel" class="input small">
              <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <input v-model.number="newTeacherWeek" type="number" min="1" placeholder="Semaine" class="input small" />
            <select v-model="newTeacherDay" class="input small">
              <option disabled value="">Jour</option>
              <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
            </select>
            <input v-model="newTeacherStart" type="time" class="input small" />
            <input v-model="newTeacherEnd" type="time" class="input small" />
            <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="newTeacherRepeat" /> Répéter chaque semaine</label>
            <button class="btn primary" type="submit">Ajouter</button>
          </form>

          <div class="list-container">
          <ul class="list">
            <li v-for="c in filteredTeacherConstraints" :key="c.id" class="list-item">
              <div class="li-main">
                <template v-if="editingTeacherId === c.id">
                  <input v-model="editTeacherText" class="input" />
                  <select v-model="editTeacherSel" class="input small">
                    <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                  <input v-model.number="editTeacherWeek" type="number" min="1" placeholder="Semaine" class="input small" />
                  <select v-model="editTeacherDay" class="input small">
                    <option disabled value="">Jour</option>
                    <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
                  </select>
                  <input v-model="editTeacherStart" type="time" class="input small" />
                  <input v-model="editTeacherEnd" type="time" class="input small" />
                  <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="editTeacherRepeat" /> Répéter chaque semaine</label>
                </template>
                <template v-else>
                  <div class="li-text">{{ c.text }}</div>
                  <div class="li-meta">Prof: <strong>{{ c.teacher || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Semaine: <strong>{{ c.week ?? '-' }}</strong>
                    &nbsp;•&nbsp;
                    Jour: <strong>{{ c.day || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Horaire: <strong>{{ c.startTime || '-' }} - {{ c.endTime || '-' }}</strong>
                    &nbsp;•&nbsp;
                    <strong>{{ c.repeatWeekly ? 'Répété chaque semaine' : 'Ponctuel' }}</strong>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:0.4rem;align-items:center">
                <template v-if="editingTeacherId === c.id">
                  <button class="btn primary" @click="saveTeacherEdit(c.id)">Enregistrer</button>
                  <button class="del" @click="cancelTeacherEdit">Annuler</button>
                </template>
                <template v-else>
                  <button class="btn" @click="startEditTeacher(c.id)">Modifier</button>
                  <button class="del" @click="removeConstraint(teacherConstraints, c.id)" title="Supprimer">Supprimer</button>
                </template>
              </div>
            </li>
          </ul>
          </div>
        </div>

        <div v-if="activeTab === 'groupes'" class="tab-section">
          <form @submit.prevent="addGroupConstraint" class="add-form">
            <input v-model="newGroupText" placeholder="Nouvelle contrainte (ex: pas de cours)" class="input" />
            <select v-model="newGroupPromo" class="input small">
              <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="newGroupGroupId" class="input small">
              <option :value="ALL_GROUP_ID">Tous</option>
              <option v-for="g in groups.filter(gg => gg.promotionId === newGroupPromo)" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <select v-model="newGroupSubgroup" class="input small">
              <option value="Tous">Tous</option>
              <option v-if="newGroupGroupId !== ALL_GROUP_ID" v-for="s in ((subgroupsByGroup[newGroupGroupId ?? 0] && subgroupsByGroup[newGroupGroupId ?? 0].length) ? subgroupsByGroup[newGroupGroupId ?? 0] : ['A','B'])" :key="s" :value="s">{{ s }}</option>
            </select>
            <input v-model.number="newGroupWeek" type="number" min="1" placeholder="Semaine" class="input small" />
            <select v-model="newGroupDay" class="input small">
              <option disabled value="">Jour</option>
              <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
            </select>
            <input v-model="newGroupStart" type="time" class="input small" />
            <input v-model="newGroupEnd" type="time" class="input small" />
            <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="newGroupRepeat" /> Répéter chaque semaine</label>
            <button class="btn primary" type="submit">Ajouter</button>
          </form>

          <ul class="list">
            <li v-for="c in filteredGroupConstraints" :key="c.id" class="list-item">
              <div class="li-main">
                <template v-if="editingGroupId === c.id">
                  <input v-model="editGroupText" class="input" />
                  <select v-model="editGroupPromo" class="input small">
                    <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                  <select v-model="editGroupGroupId" class="input small">
                    <option v-for="g in groups.filter(gg => gg.promotionId === editGroupPromo)" :key="g.id" :value="g.id">{{ g.name }}</option>
                  </select>
                  <select v-model="editGroupSubgroup" class="input small">
                    <option value="Tous">Tous</option>
                    <option v-if="editGroupGroupId !== ALL_GROUP_ID" v-for="s in ((subgroupsByGroup[editGroupGroupId ?? 0] && subgroupsByGroup[editGroupGroupId ?? 0].length) ? subgroupsByGroup[editGroupGroupId ?? 0] : ['A','B'])" :key="s" :value="s">{{ s }}</option>
                  </select>
                  <input v-model.number="editGroupWeek" type="number" min="1" placeholder="Semaine" class="input small" />
                  <select v-model="editGroupDay" class="input small">
                    <option disabled value="">Jour</option>
                    <option v-for="d in ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche']" :key="d" :value="d">{{ d }}</option>
                  </select>
                  <input v-model="editGroupStart" type="time" class="input small" />
                  <input v-model="editGroupEnd" type="time" class="input small" />
                  <label style="display:flex;align-items:center;gap:0.3rem"><input type="checkbox" v-model="editGroupRepeat" /> Répéter chaque semaine</label>
                </template>
                <template v-else>
                  <div class="li-text">{{ c.text }}</div>
                  <div class="li-meta">
                    Promotion: <strong>{{ promotions.find(p => p.id === c.promotionId)?.name || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Groupe: <strong>{{ groups.find(g => g.id === c.groupId)?.name || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Sous-groupe: <strong>{{ c.subgroup || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Semaine: <strong>{{ c.week ?? '-' }}</strong>
                    &nbsp;•&nbsp;
                    Jour: <strong>{{ c.day || '-' }}</strong>
                    &nbsp;•&nbsp;
                    Horaire: <strong>{{ c.startTime || '-' }} - {{ c.endTime || '-' }}</strong>
                    &nbsp;•&nbsp;
                    <strong>{{ c.repeatWeekly ? 'Répété chaque semaine' : 'Ponctuel' }}</strong>
                  </div>
                </template>
              </div>
              <div style="display:flex;gap:0.4rem;align-items:center">
                <template v-if="editingGroupId === c.id">
                  <button class="btn primary" @click="saveGroupEdit(c.id)">Enregistrer</button>
                  <button class="del" @click="cancelGroupEdit">Annuler</button>
                </template>
                <template v-else>
                  <button class="btn" @click="startEditGroup(c.id)">Modifier</button>
                  <button class="del" @click="removeConstraint(groupConstraints, c.id)" title="Supprimer">Supprimer</button>
                </template>
              </div>
            </li>
          </ul>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<style scoped>
.constraints-wrap { padding:1rem ; padding-bottom: 10rem;}
.constraints-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem }
.input { padding:0.45rem; border-radius:6px; border:1px solid #e5e7eb }
.controls .input { min-width:300px }
.tabs { display:flex; gap:0.5rem; margin-bottom:1rem }
.tab { padding:0.45rem 0.7rem; border-radius:8px; background:#fff; border:1px solid #e5e7eb; cursor:pointer }
.tab.active { background:#FFD8E4 }
.add-form { display:flex; gap:0.5rem; margin-bottom:0.75rem; align-items:center; flex-wrap:wrap }
 .input.small { padding:0.35rem; min-width:120px }
.list-container { max-height:calc(100vh - 350px); overflow-y:auto; padding-right:0.5rem; padding-bottom:1rem }
.list-container::-webkit-scrollbar { width:10px }
.list-container::-webkit-scrollbar-track { background:#f1f5f9; border-radius:4px }
.list-container::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:4px }
.list-container::-webkit-scrollbar-thumb:hover { background:#94a3b8 }
.list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.5rem }
 .list-item { display:flex; justify-content:space-between; align-items:flex-start; padding:0.6rem; background:#fff; border-radius:8px; border:1px solid #eef2f7 }
 .li-main { display:flex; gap:1rem; align-items:center; flex-wrap:wrap }
.li-text { font-weight:600 }
.li-meta { color:#6b7280; font-size:0.9rem }
.del { background:transparent; border:1px solid #f3f4f6; padding:0.35rem 0.5rem; border-radius:6px; cursor:pointer }
.btn.primary { background:#FFD8E4; color:#000; border:none; padding:0.4rem 0.6rem; border-radius:6px }
</style>
