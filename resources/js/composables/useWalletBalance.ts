import { ref, onMounted } from 'vue';
import axios from 'axios';

export function useWalletBalance(type?: string) {
    const balance    = ref<number | null>(null);
    const currency   = ref<string | null>(null);
    const walletType = ref<string>(type || 'default');
    const status     = ref<'idle'|'loading'|'success'|'error'>('idle');
    const message    = ref<string>('');

    const fetchBalance = async () => {
        status.value  = 'loading';
        message.value = 'Fetching balance…';

        try {
            const url = route('wallet.balance');
            const { data } = await axios.get(url, {
                params: type ? { type } : {}
            });
            balance.value    = data.balance;
            currency.value   = data.currency;
            walletType.value = 'Platform'; //data.type;
            status.value     = 'success';
            message.value    = '';
        } catch (e: any) {
            status.value  = 'error';
            message.value = e.response?.data?.message || 'Failed to fetch balance.';
        }
    };

    onMounted(fetchBalance);

    return { balance, currency, walletType, status, message, fetchBalance };
}
