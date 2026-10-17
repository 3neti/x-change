export default function CockpitQuickGenerateFundingPreflightController(): {
  url: string;
  method: string;
} {
  return {
    url: '/x/cockpit/quick-generate/funding-preflight',
    method: 'post',
  };
}
