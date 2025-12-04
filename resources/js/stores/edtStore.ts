import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useEdtStore = defineStore('edt', () => {
    // Initialize `year` from localStorage so selection survives refresh/navigation
    const storedYear = (() => {
        try {
            const v = localStorage.getItem('edt.year')
            if (v === null) return null
            const n = Number(v)
            return Number.isNaN(n) ? v : n
        } catch (e) {
            return null
        }
    })()

    const year = ref<number | string | null>(storedYear)
    const week = ref<number | null>(null)
    const promotionId = ref<number | null>(null)
    const groupId = ref<number | null>(null)
    const subgroup = ref<string | null>('A')

    function setYear(y: number | string | null) {
        year.value = y
    }
    function setWeek(w: number | null) { week.value = w }
    function setPromotion(id: number | null) { promotionId.value = id }
    function setGroup(id: number | null) { groupId.value = id }
    function setSubgroup(s: string | null) { subgroup.value = s }

    // Persist year changes to localStorage
    watch(year, (val) => {
        try {
            if (val === null) localStorage.removeItem('edt.year')
            else localStorage.setItem('edt.year', String(val))
        } catch (e) {
            // ignore storage errors (e.g. private mode)
        }
    })

    return { year, week, promotionId, groupId, subgroup, setYear, setWeek, setPromotion, setGroup, setSubgroup }
})
