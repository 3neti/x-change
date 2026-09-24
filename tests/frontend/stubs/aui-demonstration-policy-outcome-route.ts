const controller = (params: { campaign: string }) => ({
  url: `/x/cockpit/campaigns/lead-scenario-runner/runs/${params.campaign}/demonstration-policy-outcome`,
  method: "post",
});

export default controller;
