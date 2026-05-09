/**
 * Centralized route definitions for x-change package.
 * Eliminates hardcoded URL strings across pages and composables.
 * Designed for reuse across Inertia pages and future PWA.
 */
export function useXChangeRoutes() {
    return {
        dashboard: '/x/dashboard',

        payCodes: {
            index: '/x/pay-codes',
            create: '/x/pay-codes/create',
            show: (code: string) => `/x/pay-codes/${code}`,
        },

        balances: '/x/balances',

        api: {
            dashboardStats: '/api/x/v1/dashboard/stats',
            dashboardActivity: '/api/x/v1/dashboard/activity',
            pricelist: '/api/x/v1/pricelist',
            estimatePayCode: '/api/x/v1/pay-codes/estimate',
            generatePayCode: '/api/x/v1/pay-codes',
            vouchers: '/api/x/v1/vouchers',
        },
    };
}
