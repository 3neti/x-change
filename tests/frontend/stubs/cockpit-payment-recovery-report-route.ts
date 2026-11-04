export const index = ({ code }: { code: string }) => ({
  url: `/x/cockpit/pay-codes/${encodeURIComponent(code)}/payment-recovery-reports`,
  method: "get",
});
export const review = ({
  code,
  reference,
}: {
  code: string;
  reference: string;
}) => ({
  url: `/x/cockpit/pay-codes/${encodeURIComponent(code)}/payment-recovery-reports/${encodeURIComponent(reference)}/review`,
  method: "post",
});
