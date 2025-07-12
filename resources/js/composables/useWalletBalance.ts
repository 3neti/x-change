import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { useEcho } from '@laravel/echo-vue'
import type { SharedData, User } from '@/types';

export function useWalletBalance(type?: string) {
    const balance    = ref<number | null>(null)
    const currency   = ref<string | null>(null)
    const walletType = ref<string>(type || 'default')
    const status     = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
    const message    = ref<string>('')

    const page = usePage<SharedData>();
    const user = page.props.auth.user as User;

    const fetchBalance = async () => {
        status.value  = 'loading'
        message.value = 'Fetching balance…'

        try {
            const url = route('wallet.balance')
            const { data } = await axios.get(url, {
                params: type ? { type } : {},
            })

            balance.value    = data.balance
            currency.value   = data.currency
            walletType.value = 'Platform' // data.type
            status.value     = 'success'
            message.value    = ''
        } catch (e: any) {
            status.value  = 'error'
            message.value = e.response?.data?.message || 'Failed to fetch balance.'
        }
    }

    // 1️⃣ Fetch on mount
    onMounted(fetchBalance)

    // 2️⃣ Echo: subscribe to user balance update event
    const { listen } = useEcho<{ balance: number, balanceFloat: number }>(
        `user.${user.id}`,
        '.balance.updated',
        (event) => {
            balance.value = event.balanceFloat
        }
    )

    onMounted(() => {
        listen()
    })

    // 3️⃣ Computed formatted balance
    const formattedBalance = computed(() =>
        balance.value !== null
            ? new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: currency.value || 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(balance.value)
            : '₱0.00'
    )

    return {
        balance,
        currency,
        walletType,
        status,
        message,
        fetchBalance,
        formattedBalance, // ✅ included here
    }
}
