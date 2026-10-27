/** Verify a tag against CI for the exact commit; PR and manual runs do not qualify. */
export async function verifyReleaseEvidence(github, repo, sha) {
    if (!/^[a-f0-9]{40}$/.test(sha ?? '')) {
        throw new Error('A full commit SHA is required.');
    }
    const runs = await github.paginate(github.rest.actions.listWorkflowRuns, {
        ...repo,
        workflow_id: 'ci.yml',
        branch: 'main',
        event: 'push',
        head_sha: sha,
        per_page: 100,
    });
    const matching = runs.filter(run => run.head_sha === sha
        && run.head_branch === 'main' && run.event === 'push')
        .sort((a, b) => b.run_number - a.run_number);
    const run = matching[0];
    if (!run || run.status !== 'completed' || run.conclusion !== 'success') {
        throw new Error(`No successful latest main-push CI evidence for ${sha}.`);
    }
    const jobs = await github.paginate(github.rest.actions.listJobsForWorkflowRun, {
        ...repo, run_id: run.id, filter: 'latest', per_page: 100,
    });
    for (const name of ['PHP tests', 'Vue tests']) {
        if (!jobs.some(job => job.name === name && job.status === 'completed' && job.conclusion === 'success')) {
            throw new Error(`Required ${name} evidence is missing or unsuccessful.`);
        }
    }
    return run;
}
