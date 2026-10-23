import { createApp } from 'vue';
import CollectStoredValue from '../../../resources/js/pages/x-change/pos/CollectStoredValue.vue';

createApp(CollectStoredValue, {
    channel: 'cockpit',
    endpoints: {
        start: '/x/cockpit/stored-value-collections',
        status: '/x/cockpit/stored-value-collections/__REFERENCE__',
        verify: '/x/cockpit/stored-value-collections/__REFERENCE__/verification',
        commit: '/x/cockpit/stored-value-collections/__REFERENCE__/commit',
    },
}).mount('#app');
