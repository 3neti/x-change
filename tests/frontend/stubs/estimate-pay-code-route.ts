export default function EstimatePayCodeController() {
    return {
        url: '/api/x/v1/pay-codes/estimate',
        method: 'post' as const,
    };
}
