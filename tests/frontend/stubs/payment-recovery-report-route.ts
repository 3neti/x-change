export const store = ({ code }: { code: string }) => ({
  url: `/x/pay/${encodeURIComponent(code)}/recovery-reports`,
  method: "post",
});
