type RouteParameters = {
  code: string;
};

type RouteDefinition = {
  url: string;
  method: "post";
};

type RouteAction = {
  (args: RouteParameters): RouteDefinition;
  form: (args: RouteParameters) => { action: string; method: "post" };
};

const action = (suffix: string): RouteAction => {
  const routeAction = ((args: RouteParameters): RouteDefinition => ({
    url: `/x/claim/${args.code}/payout-recovery/${suffix}`,
    method: "post",
  })) as RouteAction;

  routeAction.form = (args: RouteParameters) => ({
    action: `/x/claim/${args.code}/payout-recovery/${suffix}`,
    method: "post",
  });

  return routeAction;
};

export default {
  start: action("challenge"),
  verify: action("verification"),
  submit: action("destination"),
};
