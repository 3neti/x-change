import { test } from 'node:test';
import assert from 'node:assert/strict';
import { verifyReleaseEvidence } from './ci-release-evidence.mjs';

const sha = 'a'.repeat(40);
const repo = { owner: 'example', repo: 'package' };
const successful = {
    id: 123, run_number: 10, head_sha: sha, head_branch: 'main', event: 'push',
    status: 'completed', conclusion: 'success', html_url: 'https://example.test/run/123',
};
const jobs = ['PHP tests', 'Vue tests'].map(name => ({ name, status: 'completed', conclusion: 'success' }));
function api(runs, evidence = jobs) {
    return {
        rest: { actions: { listWorkflowRuns: 'runs', listJobsForWorkflowRun: 'jobs' } },
        async paginate(endpoint, parameters) {
            if (endpoint === 'runs') {
                assert.equal(parameters.workflow_id, 'ci.yml');
                assert.equal(parameters.head_sha, sha);
                assert.equal(parameters.event, 'push');
                assert.equal(parameters.branch, 'main');
                return runs;
            }
            assert.equal(parameters.run_id, successful.id);
            assert.equal(parameters.filter, 'latest');
            return evidence;
        },
    };
}
test('accepts exact commit with both successful required jobs', async () => {
    assert.equal(await verifyReleaseEvidence(api([successful]), repo, sha), successful);
});
for (const [label, patch] of Object.entries({
    'different commit': { head_sha: 'b'.repeat(40) },
    'PR evidence': { event: 'pull_request' },
    'manual evidence': { event: 'workflow_dispatch' },
    'different branch': { head_branch: 'feature/example' },
    'pending evidence': { status: 'in_progress', conclusion: null },
    'failed evidence': { conclusion: 'failure' },
    'cancelled evidence': { conclusion: 'cancelled' },
})) {
    test(`rejects ${label}`, async () => {
        await assert.rejects(verifyReleaseEvidence(api([{ ...successful, ...patch }]), repo, sha));
    });
}
test('rejects missing evidence and malformed SHA', async () => {
    await assert.rejects(verifyReleaseEvidence(api([]), repo, sha));
    await assert.rejects(verifyReleaseEvidence(api([]), repo, 'main'));
});
test('does not hide a newer failed run behind an earlier success', async () => {
    await assert.rejects(verifyReleaseEvidence(api([successful, {
        ...successful, run_number: 11, conclusion: 'failure',
    }]), repo, sha));
});
for (const conclusion of ['failure', 'cancelled', 'skipped']) {
    test(`rejects ${conclusion} required check`, async () => {
        await assert.rejects(verifyReleaseEvidence(api([successful], [jobs[0], { ...jobs[1], conclusion }]), repo, sha));
    });
}
test('rejects missing required check', async () => {
    await assert.rejects(verifyReleaseEvidence(api([successful], [jobs[0]]), repo, sha));
});
