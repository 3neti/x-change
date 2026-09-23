export const show = (code: string | { code: string }) => ({
  url: `/x/cockpit/pay-codes/${typeof code === "string" ? code : code.code}`,
  method: "get" as const,
});
