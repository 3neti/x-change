export const review = ({ outbox }: { outbox: number }) => ({
  url: `/x/operations/recovery/treasury-payouts/${outbox}/review`,
  method: "post",
});
export const investigate = ({ outbox }: { outbox: number }) => ({
  url: `/x/operations/recovery/treasury-payouts/${outbox}/investigate`,
  method: "post",
});
