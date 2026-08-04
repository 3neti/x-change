type RouteDefinition = {
  url: string;
  method: "post";
};

type PayoutCorrectionController = {
  (args: { code: string }): RouteDefinition;
  form: (args: { code: string }) => { action: string; method: "post" };
};

const controller = ((args: { code: string }): RouteDefinition => ({
  url: `/x/cockpit/pay-codes/${args.code}/payout-corrections`,
  method: "post",
})) as PayoutCorrectionController;

controller.form = (args: { code: string }) => ({
  action: `/x/cockpit/pay-codes/${args.code}/payout-corrections`,
  method: "post",
});

export default controller;
