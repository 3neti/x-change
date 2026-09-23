const showPolicyLifecycle = () => ({
  url: "/x/cockpit/campaigns/policy-lifecycle",
  method: "get" as const,
});

showPolicyLifecycle.url = () => "/x/cockpit/campaigns/policy-lifecycle";

export default showPolicyLifecycle;
