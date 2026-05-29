export default {
    name: 'RiderCountdown',
    props: ['redirect', 'redirectEndpoint'],
    template: `
        <div data-testid="rider-countdown">
            <span data-testid="countdown-delay">{{ redirect?.delay_seconds }}</span>
            <span data-testid="countdown-endpoint">{{ redirectEndpoint }}</span>
        </div>
    `,
};
