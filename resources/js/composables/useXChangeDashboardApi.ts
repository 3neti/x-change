import { ref } from 'vue';

export interface DashboardStats {
    vouchers: {
        total: number;
        active: number;
        redeemed: number;
        expired: number;
        cancelled: number;
    };
    disbursements: {
        total_attempts: number;
        successful: number;
        failed: number;
        success_rate: number;
        total_disbursed: number;
        currency: string;
    };
    reconciliations: {
        needs_review: number;
    };
}

export interface ActivityItem {
    id: number;
    type: string;
    code?: string;
    amount?: number;
    currency?: string;
    status?: string;
    mobile?: string;
    provider?: string;
    needs_review?: boolean;
    reference?: string;
    created_at?: string;
}

export interface RecentActivity {
    vouchers: ActivityItem[];
    claims: ActivityItem[];
    reconciliations: ActivityItem[];
}

export function useXChangeDashboardApi() {
    const loading = ref(false);
    const error = ref<Error | null>(null);

    const fetchJson = async (url: string) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    };

    const getStats = async (): Promise<DashboardStats | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchJson('/api/x/v1/dashboard/stats');
            return response.data.stats;
        } catch (err) {
            error.value =
                err instanceof Error ? err : new Error('Failed to fetch stats');
            return null;
        } finally {
            loading.value = false;
        }
    };

    const getActivity = async (): Promise<RecentActivity | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetchJson('/api/x/v1/dashboard/activity');
            return response.data.activity;
        } catch (err) {
            error.value =
                err instanceof Error
                    ? err
                    : new Error('Failed to fetch activity');
            return null;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        getStats,
        getActivity,
    };
}
