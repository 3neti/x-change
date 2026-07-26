import fs from 'node:fs/promises';
import path from 'node:path';

export async function writeStoryboard({ artifactDirectory, storyboard }) {
  const storyboardPath = path.join(artifactDirectory, 'claim-walkthrough-storyboard.json');
  await fs.writeFile(storyboardPath, `${JSON.stringify(storyboard, null, 2)}\n`);

  return storyboardPath;
}
