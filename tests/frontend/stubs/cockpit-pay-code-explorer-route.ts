const url = '/x/cockpit/pay-codes';

function CockpitPayCodeExplorerPageController() {
    return {
        url,
        method: 'get',
    };
}

CockpitPayCodeExplorerPageController.url = () => url;
CockpitPayCodeExplorerPageController.form = () => ({
    action: url,
    method: 'get',
});

export default CockpitPayCodeExplorerPageController;
