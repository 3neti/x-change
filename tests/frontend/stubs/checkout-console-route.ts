export const show = Object.assign(() => ({ url: "/x/checkout" }), {
  url: () => "/x/checkout",
});
export const viewer = Object.assign(() => ({ url: "/x/checkout/view/token" }), {
  url: (token: string) => `/x/checkout/view/${token}`,
});
export const unlock = () => ({ url: "/x/checkout/unlock" });
export const lock = () => ({ url: "/x/checkout/lock" });
export const retry = (reference: string) => ({ url: `/x/checkout/${reference}/retry` });
