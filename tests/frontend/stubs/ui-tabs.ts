export const Tabs = {
    name: 'Tabs',
    template: '<div data-testid="tabs"><slot /></div>',
};

export const TabsContent = {
    name: 'TabsContent',
    template: '<div data-testid="tabs-content"><slot /></div>',
};

export const TabsList = {
    name: 'TabsList',
    template: '<div data-testid="tabs-list"><slot /></div>',
};

export const TabsTrigger = {
    name: 'TabsTrigger',
    template: '<button data-testid="tabs-trigger"><slot /></button>',
};
