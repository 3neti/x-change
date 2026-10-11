export const open = (reference: string) => ({ url: `/x/checkout/${reference}/refund` });
export const record = (reference: string) => ({ url: `/x/checkout/${reference}/refund/record` });
export const reconcile = (reference: string) => ({ url: `/x/checkout/${reference}/refund/reconcile` });
