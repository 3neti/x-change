export const index = ({ code }: { code: string }) => ({
  url: `/x/cockpit/pay-codes/${code}/value-flow-payouts`,
});
