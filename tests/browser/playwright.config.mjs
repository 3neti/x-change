export default {
    testDir: '.',
    testMatch: 'campaign-workflow-draft.spec.mjs',
    timeout: 30000,
    workers: 1,
    outputDir: process.env.WORKFLOW_BROWSER_ARTIFACTS,
    use: { ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } },
};
