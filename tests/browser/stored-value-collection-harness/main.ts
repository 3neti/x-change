import { createApp } from 'vue';
import CollectStoredValue from '../../../resources/js/pages/x-change/pos/CollectStoredValue.vue';

const view = new URLSearchParams(window.location.search).get('view');

createApp(CollectStoredValue, {
    channel: 'cockpit',
    fixed_amount_minor: 0,
    ...(view === 'activity' || view === 'exceptions' ? {
        view,
        recent_collections: [{
            reference: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            status: view === 'activity' ? 'committed' : 'ready',
            amount_minor: 2500,
            currency: 'PHP',
            created_at: new Date().toISOString(),
        }],
    } : {}),
    endpoints: {
        start: '/x/cockpit/stored-value-collections',
        status: '/x/cockpit/stored-value-collections/__REFERENCE__',
        verify: '/x/cockpit/stored-value-collections/__REFERENCE__/verification',
        commit: '/x/cockpit/stored-value-collections/__REFERENCE__/commit',
    },
}).mount('#app');
