import { ref, onMounted } from 'vue';
import axios from 'axios';

export function useQrCode(amount: number) {
    const qrCode = ref<string | null>(null);
    const status = ref<'idle'|'loading'|'success'|'error'>(amount>0 ? 'idle':'error');
    const message = ref('');

    const fetchQr = async () => {
        status.value = 'loading';
        message.value = 'Generating QR code…';

        try {
            const { data } = await axios.get(route('wallet.qr-code'), { params: { amount } });
            if (data.success) {
                qrCode.value = data.qr_code;
                status.value = 'success';
                message.value = 'QR code generated.';
            } else {
                status.value = 'error';
                message.value = data.message || 'Failed to generate QR code.';
            }
        } catch {
            status.value = 'error';
            message.value = 'Error occurred while generating QR code.';
        }
    };

    onMounted(fetchQr);

    return { qrCode, status, message, refresh: fetchQr };
}
