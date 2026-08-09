type RouteDefinition = {
    url: string;
    method: 'post';
};

type TerminalStateController = {
    (args: { code: string }): RouteDefinition;
    form: (args: { code: string }) => { action: string; method: 'post' };
};

const controller = ((args: { code: string }): RouteDefinition => ({
    url: `/x/cockpit/pay-codes/${args.code}/terminal-actions`,
    method: 'post',
})) as TerminalStateController;

controller.form = (args: { code: string }) => ({
    action: `/x/cockpit/pay-codes/${args.code}/terminal-actions`,
    method: 'post',
});

export default controller;
