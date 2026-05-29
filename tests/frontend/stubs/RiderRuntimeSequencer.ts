export default {
    name: 'RiderRuntimeSequencer',
    props: ['stages', 'redirectEndpoint'],
    template: `
        <div data-testid="rider-runtime">
            <span
                v-for="stage in stages"
                :key="stage.key"
                data-testid="runtime-stage"
            >
                {{ stage.key }}
            </span>
        </div>
    `,
};
