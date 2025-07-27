<script setup lang="ts">
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar'

import { Badge } from '@/components/ui/badge';
import { useFormatCurrency } from '@/composables/useFormatCurrency';
import { useFormatDate } from '@/composables/useFormatDate';
import { useBankAlias } from '@/composables/useBankAlias';
import { useFlagEmoji } from '@/composables/useFlagEmoji';
import { VoucherList } from '@/types';

const formatCurrency = useFormatCurrency();
const { formatDate } = useFormatDate();
const { getBankAlias } = useBankAlias();
const { getFlagEmoji } = useFlagEmoji();
import { History } from 'lucide-vue-next';

const props = defineProps<{
    vouchers: VoucherList;
}>();

function formatVoucherCode(code: string, padChar: string = '\u00A0'): string {
    const hasDash = code.includes('-')
    const [prefix, suffix] = hasDash ? code.split('-') : ['', code]

    const paddedPrefix = prefix.padStart(8, padChar)
    const paddedSuffix = suffix.padEnd(8, padChar)

    // Dash column span, change bg-transparent to bg-yellow-200 if we want highlighted
    const separator = `<span class="inline-block w-[1ch] text-center bg-transparent whitespace-pre">${hasDash ? '-' : '&nbsp;'}</span>`

    return `${paddedPrefix}${separator}${paddedSuffix}`
}

</script>

<template>
    <div class="space-y-8">
        <div
            v-for="(voucher, index) in vouchers"
            :key="voucher.code"
            class="flex items-center"
        >
<!--            <Badge class="w-32 justify-center font-mono px-0">-->
                <Badge class="w-36 justify-center font-mono px-0 text-center whitespace-pre">
                <span v-html="formatVoucherCode(voucher.code)"></span>
            </Badge>

<!--            <Avatar class="h-9 w-9">-->
<!--                <AvatarImage-->
<!--                    :src="`/avatars/0${(index % 5) + 1}.png`"-->
<!--                    alt="Avatar"-->
<!--                />-->
<!--                <AvatarFallback>-->
<!--                    {{ voucher.contact?.mobile?.slice(-2).toUpperCase() || 'NA' }}-->
<!--                </AvatarFallback>-->
<!--            </Avatar>-->

            <div class="ml-4 space-y-1">
                <p class="text-sm font-medium leading-none">
                    {{ getFlagEmoji(voucher.instructions.cash.validation.country ?? 'PH') }}
                    {{ voucher.contact?.mobile || 'Unknown Mobile' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ getBankAlias(voucher.cash.withdrawTransaction?.payload.destination_account.bank_code ?? '') }}
                    :: {{ voucher.cash.withdrawTransaction?.payload.destination_account.account_number }}
                </p>
                <p class="text-xs text-muted-foreground inline-flex items-center gap-1">
                    <span><History /></span>
                    <span>{{ formatDate(voucher.redeemed_at) }}</span>
                </p>
            </div>
<!--                <div class="ml-4 space-y-1">-->
<!--                    <p class="text-sm font-medium leading-none">-->
<!--                        {{ getFlagEmoji(voucher.instructions.cash.validation.country ?? 'PH') }} {{ voucher.contact?.mobile || 'Unknown Mobile' }}-->
<!--                    </p>-->
<!--                    <p class="text-sm text-muted-foreground">-->
<!--                        {{ getBankAlias(voucher.cash.withdrawTransaction?.payload.destination_account.bank_code ?? '') }} :: {{ voucher.cash.withdrawTransaction?.payload.destination_account.account_number }}-->
<!--                    </p>-->
<!--                </div>-->
            <div class="ml-auto font-medium">
                {{ formatCurrency(voucher.cash?.amount, {isMinor: true}) }}
            </div>

        </div>
    </div>
</template>

<!--<template>-->
<!--    <div class="space-y-8">-->
<!--        <div class="flex items-center">-->
<!--            <Avatar class="h-9 w-9">-->
<!--                <AvatarImage src="/avatars/01.png" alt="Avatar" />-->
<!--                <AvatarFallback>OM</AvatarFallback>-->
<!--            </Avatar>-->
<!--            <div class="ml-4 space-y-1">-->
<!--                <p class="text-sm font-medium leading-none">-->
<!--                    Olivia Martin-->
<!--                </p>-->
<!--                <p class="text-sm text-muted-foreground">-->
<!--                    olivia.martin@email.com-->
<!--                </p>-->
<!--            </div>-->
<!--            <div class="ml-auto font-medium">-->
<!--                +$1,999.00-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="flex items-center">-->
<!--            <Avatar class="flex h-9 w-9 items-center justify-center space-y-0 border">-->
<!--                <AvatarImage src="/avatars/02.png" alt="Avatar" />-->
<!--                <AvatarFallback>JL</AvatarFallback>-->
<!--            </Avatar>-->
<!--            <div class="ml-4 space-y-1">-->
<!--                <p class="text-sm font-medium leading-none">-->
<!--                    Jackson Lee-->
<!--                </p>-->
<!--                <p class="text-sm text-muted-foreground">-->
<!--                    jackson.lee@email.com-->
<!--                </p>-->
<!--            </div>-->
<!--            <div class="ml-auto font-medium">-->
<!--                +$39.00-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="flex items-center">-->
<!--            <Avatar class="h-9 w-9">-->
<!--                <AvatarImage src="/avatars/03.png" alt="Avatar" />-->
<!--                <AvatarFallback>IN</AvatarFallback>-->
<!--            </Avatar>-->
<!--            <div class="ml-4 space-y-1">-->
<!--                <p class="text-sm font-medium leading-none">-->
<!--                    Isabella Nguyen-->
<!--                </p>-->
<!--                <p class="text-sm text-muted-foreground">-->
<!--                    isabella.nguyen@email.com-->
<!--                </p>-->
<!--            </div>-->
<!--            <div class="ml-auto font-medium">-->
<!--                +$299.00-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="flex items-center">-->
<!--            <Avatar class="h-9 w-9">-->
<!--                <AvatarImage src="/avatars/04.png" alt="Avatar" />-->
<!--                <AvatarFallback>WK</AvatarFallback>-->
<!--            </Avatar>-->
<!--            <div class="ml-4 space-y-1">-->
<!--                <p class="text-sm font-medium leading-none">-->
<!--                    William Kim-->
<!--                </p>-->
<!--                <p class="text-sm text-muted-foreground">-->
<!--                    will@email.com-->
<!--                </p>-->
<!--            </div>-->
<!--            <div class="ml-auto font-medium">-->
<!--                +$99.00-->
<!--            </div>-->
<!--        </div>-->
<!--        <div class="flex items-center">-->
<!--            <Avatar class="h-9 w-9">-->
<!--                <AvatarImage src="/avatars/05.png" alt="Avatar" />-->
<!--                <AvatarFallback>SD</AvatarFallback>-->
<!--            </Avatar>-->
<!--            <div class="ml-4 space-y-1">-->
<!--                <p class="text-sm font-medium leading-none">-->
<!--                    Sofia Davis-->
<!--                </p>-->
<!--                <p class="text-sm text-muted-foreground">-->
<!--                    sofia.davis@email.com-->
<!--                </p>-->
<!--            </div>-->
<!--            <div class="ml-auto font-medium">-->
<!--                +$39.00-->
<!--            </div>-->
<!--        </div>-->
<!--    </div>-->
<!--</template>-->
