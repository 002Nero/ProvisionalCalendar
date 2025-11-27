import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useEdtStore = defineStore('edt', () => {
    const year = ref<string | null>(null)
    const week = ref<number | null>(null)
    const promotionId = ref<number | null>(null)
    const groupId = ref<number | null>(null)

    function setYear(y: string | null) { year.value = y }
    function setWeek(w: number | null) { week.value = w }
    function setPromotion(id: number | null) { promotionId.value = id }
    function setGroup(id: number | null) { groupId.value = id }

    return { year, week, promotionId, groupId, setYear, setWeek, setPromotion, setGroup }
})
