<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed, Ref, watch } from 'vue'

const activeTab = ref<'salles'|'profs'|'groupes'>('salles')
const filter = ref('')

const rooms = ref(['R.50','209','103','112','R.47','B101'])
const teachers = ref(['M. Dubreuil','Mme. Poursat','M. Monediere','M. Onete','Dr. Laurent'])
const promotions = ref([
  { id: 1, name: 'A1' },
  { id: 2, name: 'A2' },
  { id: 3, name: 'A3' }
])
const groups = ref([
  { id: 1, name: 'G1', promotionId: 1 },
  { id: 2, name: 'G2', promotionId: 1 },
  { id: 3, name: 'G3', promotionId: 1 },
  { id: 4, name: 'G4', promotionId: 2 },
  { id: 5, name: 'G5', promotionId: 2 },
  { id: 6, name: 'G6', promotionId: 2 },
  { id: 7, name: 'G7', promotionId: 3 },
  { id: 8, name: 'G8', promotionId: 3 },

])
const subgroupsByGroup: Record<number, string[]> = {
  1: ['A','B'],
  2: ['A','B'],
  3: ['A','B'],
  4: ['A','B'],
  5: ['A','B'],
  6: ['A','B'],
  7: ['A','B'],
  8: ['A','B'],
}

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

function addRoomConstraint() {
  const text = newRoomText.value?.trim()
  if (!text) return
  roomConstraints.value.push({
    id: nextId++,
    text,
    room: newRoomSel.value,
    week: newRoomWeek.value,
    day: newRoomDay.value,
    startTime: newRoomStart.value,
    endTime: newRoomEnd.value,
    repeatWeekly: newRoomRepeat.value,
  })
  newRoomText.value = ''
  newRoomWeek.value = undefined
  newRoomDay.value = undefined
  newRoomStart.value = undefined
  newRoomEnd.value = undefined
  newRoomRepeat.value = false
}
const newTeacherWeek = ref<number | undefined>(undefined)
const newTeacherDay = ref<string | undefined>(undefined)
const newTeacherStart = ref<string | undefined>(undefined)
const newTeacherEnd = ref<string | undefined>(undefined)
const newTeacherRepeat = ref<boolean>(false)

function addTeacherConstraint() {
  const text = newTeacherText.value?.trim()
  if (!text) return
  teacherConstraints.value.push({
    id: nextId++,
    text,
    teacher: newTeacherSel.value,
    week: newTeacherWeek.value,
    day: newTeacherDay.value,
    startTime: newTeacherStart.value,
    endTime: newTeacherEnd.value,
    repeatWeekly: newTeacherRepeat.value,
  })
  newTeacherText.value = ''
  newTeacherWeek.value = undefined
  newTeacherDay.value = undefined
  newTeacherStart.value = undefined
  newTeacherEnd.value = undefined
  newTeacherRepeat.value = false
}
function addGroupConstraint() {
  const text = newGroupText.value?.trim()
  if (!text) return
  const promoId = newGroupPromo.value
  const gid = newGroupGroupId.value
  const sub = newGroupSubgroup.value
  groupConstraints.value.push({ id: nextId++, text, promotionId: promoId, groupId: gid, subgroup: sub, week: newGroupWeek.value, day: newGroupDay.value, startTime: newGroupStart.value, endTime: newGroupEnd.value, repeatWeekly: newGroupRepeat.value })
  newGroupText.value = ''
  newGroupWeek.value = undefined
  newGroupDay.value = undefined
  newGroupStart.value = undefined
  newGroupEnd.value = undefined
  newGroupRepeat.value = false
}

function removeConstraint(list: Ref<Constraint[]> | Constraint[], id: number) {
  const arr = Array.isArray(list) ? list : (list as Ref<Constraint[]>).value
  const idx = arr.findIndex(c => c.id === id)
  if (idx !== -1) arr.splice(idx, 1)
}

const editingRoomId = ref<number | null>(null)
const editRoomText = ref('')
const editRoomSel = ref<string | undefined>(undefined)
const editRoomWeek = ref<number | undefined>(undefined)
const editRoomDay = ref<string | undefined>(undefined)
const editRoomStart = ref<string | undefined>(undefined)
const editRoomEnd = ref<string | undefined>(undefined)
const editRoomRepeat = ref<boolean>(false)

const editingTeacherId = ref<number | null>(null)
const editTeacherText = ref('')
const editTeacherSel = ref<string | undefined>(undefined)
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
  editRoomSel.value = c.room
  editRoomWeek.value = c.week
  editRoomDay.value = c.day
  editRoomStart.value = c.startTime
  editRoomEnd.value = c.endTime
  editRoomRepeat.value = !!c.repeatWeekly
}
function saveRoomEdit(id: number) {
  const c = roomConstraints.value.find(x => x.id === id)
  if (!c) return
  c.text = editRoomText.value || c.text
  c.room = editRoomSel.value
  c.week = editRoomWeek.value
  c.day = editRoomDay.value
  c.startTime = editRoomStart.value
  c.endTime = editRoomEnd.value
  c.repeatWeekly = editRoomRepeat.value
  editingRoomId.value = null
}
function cancelRoomEdit() {
  editingRoomId.value = null
}

function startEditTeacher(id: number) {
  const c = teacherConstraints.value.find(x => x.id === id)
  if (!c) return
  editingTeacherId.value = id
  editTeacherText.value = c.text
  editTeacherSel.value = c.teacher
  editTeacherWeek.value = c.week
  editTeacherDay.value = c.day
  editTeacherStart.value = c.startTime
  editTeacherEnd.value = c.endTime
  editTeacherRepeat.value = !!c.repeatWeekly
}
function saveTeacherEdit(id: number) {
  const c = teacherConstraints.value.find(x => x.id === id)
  if (!c) return
  c.text = editTeacherText.value || c.text
  c.teacher = editTeacherSel.value
  c.week = editTeacherWeek.value
  c.day = editTeacherDay.value
  c.startTime = editTeacherStart.value
  c.endTime = editTeacherEnd.value
  c.repeatWeekly = editTeacherRepeat.value
  editingTeacherId.value = null
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
}
function cancelGroupEdit() { editingGroupId.value = null }

watch(editGroupPromo, (val) => {
  const first = groups.value.find(g => g.promotionId === val)
  editGroupGroupId.value = first?.id
})
watch(editGroupGroupId, (val) => {
  editGroupSubgroup.value = (subgroupsByGroup[val ?? 0] || [])[0]
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
const newRoomSel = ref(rooms.value[0])
const newTeacherText = ref('')
const newTeacherSel = ref(teachers.value[0])
const newGroupText = ref('')
const newGroupPromo = ref<number | undefined>(promotions.value[0]?.id)
const newGroupGroupId = ref<number | undefined>(groups.value.find(g => g.promotionId === newGroupPromo.value)?.id)
const newGroupSubgroup = ref<string | undefined>(subgroupsByGroup[newGroupGroupId.value ?? groups.value[0].id]?.[0])

const newGroupSel = ref(groups.value[0].name)

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

watch(newGroupPromo, (val) => {
  const first = groups.value.find(g => g.promotionId === val)
  newGroupGroupId.value = first?.id
})
watch(newGroupGroupId, (val) => {
  newGroupSubgroup.value = (subgroupsByGroup[val ?? 0] || [])[0]
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
        <div v-if="activeTab === 'salles'">
          <form @submit.prevent="addRoomConstraint" class="add-form">
            <input v-model="newRoomText" placeholder="Nouvelle contrainte (ex: salle indisponible)" class="input" />
            <select v-model="newRoomSel" class="input small">
              <option v-for="r in rooms" :key="r" :value="r">{{ r }}</option>
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

          <ul class="list">
            <li v-for="c in filteredRoomConstraints" :key="c.id" class="list-item">
              <div class="li-main">
                <template v-if="editingRoomId === c.id">
                  <input v-model="editRoomText" class="input" />
                  <select v-model="editRoomSel" class="input small">
                    <option v-for="r in rooms" :key="r" :value="r">{{ r }}</option>
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

        <div v-if="activeTab === 'profs'">
          <form @submit.prevent="addTeacherConstraint" class="add-form">
            <input v-model="newTeacherText" placeholder="Nouvelle contrainte (ex: indisponible)" class="input" />
            <select v-model="newTeacherSel" class="input small">
              <option v-for="t in teachers" :key="t" :value="t">{{ t }}</option>
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

          <ul class="list">
            <li v-for="c in filteredTeacherConstraints" :key="c.id" class="list-item">
              <div class="li-main">
                <template v-if="editingTeacherId === c.id">
                  <input v-model="editTeacherText" class="input" />
                  <select v-model="editTeacherSel" class="input small">
                    <option v-for="t in teachers" :key="t" :value="t">{{ t }}</option>
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

        <div v-if="activeTab === 'groupes'">
          <form @submit.prevent="addGroupConstraint" class="add-form">
            <input v-model="newGroupText" placeholder="Nouvelle contrainte (ex: pas de cours)" class="input" />
            <select v-model="newGroupPromo" class="input small">
              <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="newGroupGroupId" class="input small">
              <option v-for="g in groups.filter(gg => gg.promotionId === newGroupPromo)" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <select v-model="newGroupSubgroup" class="input small">
              <option v-for="s in (subgroupsByGroup[newGroupGroupId ?? 0] || [])" :key="s" :value="s">{{ s }}</option>
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
                    <option v-for="s in (subgroupsByGroup[editGroupGroupId ?? 0] || [])" :key="s" :value="s">{{ s }}</option>
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
.constraints-wrap { padding:1rem }
.constraints-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem }
.input { padding:0.45rem; border-radius:6px; border:1px solid #e5e7eb }
.controls .input { min-width:220px }
.tabs { display:flex; gap:0.5rem; margin-bottom:1rem }
.tab { padding:0.45rem 0.7rem; border-radius:8px; background:#fff; border:1px solid #e5e7eb; cursor:pointer }
.tab.active { background:#FFD8E4 }
.add-form { display:flex; gap:0.5rem; margin-bottom:0.75rem; align-items:center; flex-wrap:wrap }
 .input.small { padding:0.35rem; min-width:120px }
.list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.5rem }
 .list-item { display:flex; justify-content:space-between; align-items:flex-start; padding:0.6rem; background:#fff; border-radius:8px; border:1px solid #eef2f7 }
 .li-main { display:flex; gap:1rem; align-items:center; flex-wrap:wrap }
.li-text { font-weight:600 }
.li-meta { color:#6b7280; font-size:0.9rem }
.del { background:transparent; border:1px solid #f3f4f6; padding:0.35rem 0.5rem; border-radius:6px; cursor:pointer }
.btn.primary { background:#FFD8E4; color:#000; border:none; padding:0.4rem 0.6rem; border-radius:6px }
</style>
