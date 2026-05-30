export default {
    name: 'RiderStagePresenter',
    props: ['stage'],
    template: `
        <div data-testid="rider-stage">
            {{ stage?.key ?? stage?.type }}
        </div>
    `,
};
// Test stub.
//
// We intentionally render stage.key instead of full stage content
// so precedence tests can assert which stage source won
// (compiled phase vs legacy rider stages).
