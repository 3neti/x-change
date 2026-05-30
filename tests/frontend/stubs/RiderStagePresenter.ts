export default {
    name: 'RiderStagePresenter',
    props: ['stage'],
    template: `
        <div data-testid="rider-stage">
            {{ stage?.key ?? stage?.type }}
        </div>
    `,
};
