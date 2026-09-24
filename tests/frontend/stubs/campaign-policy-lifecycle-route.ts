type Options = { query?: Record<string, string> };
const url = (options?: Options) => {
  const query = new URLSearchParams(options?.query).toString();
  return "/x/cockpit/campaigns/policy-lifecycle" + (query ? `?${query}` : "");
};
const showPolicyLifecycle = (options?: Options) => ({
  url: url(options),
  method: "get" as const,
});

showPolicyLifecycle.url = url;

export default showPolicyLifecycle;
