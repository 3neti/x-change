import { useCleanForm } from './useCleanForm'
import { ref, computed, watch } from 'vue'
import { debounce } from 'lodash'
import axios from 'axios'

export function useCostBreakdown(form: any, excluded: string[] = []) {
    const { clean } = useCleanForm()

    const payload = computed(() => clean(form, [], excluded))
    const costBreakdown = ref<any>(null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    axios.defaults.withCredentials = true

    async function calculateCost() {
        loading.value = true
        error.value = null

        try {
            const response = await axios.post(route('calculate-cost'), payload.value)
            costBreakdown.value = response.data
        } catch (err: any) {
            error.value = err.response?.data?.message || 'An error occurred.'
        } finally {
            loading.value = false
        }
    }

    function getCostComponent(path: string): string | undefined {
        return costBreakdown.value?.breakdown?.[path]
    }

    function getTotalCost(): number | undefined {
        return costBreakdown.value?.total
    }

    watch(
        payload,
        debounce(() => {
            calculateCost()
        }, 500),
        { deep: true, immediate: true }
    )

    return {
        costBreakdown,
        getCostComponent,
        getTotalCost,
        loading,
        error,
        refresh: calculateCost,
    }
}
