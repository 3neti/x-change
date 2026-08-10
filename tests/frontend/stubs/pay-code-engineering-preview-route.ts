type RouteDefinition = {
    url: string;
    method: 'get';
};

export default (args: { code: string }): RouteDefinition => ({
    url: `/x/cockpit/pay-codes/${args.code}/engineering-preview`,
    method: 'get',
});
