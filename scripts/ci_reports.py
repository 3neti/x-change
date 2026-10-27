"""Validate PHP shard coverage and publish CI timing evidence (stdlib only)."""
import argparse
from collections import Counter
from datetime import datetime
import html
import json
import math
import os
import re
import subprocess
from pathlib import Path
import sys
import tempfile
import xml.etree.ElementTree as ET


def inventory(path):
    root = ET.parse(path).getroot()
    identities = [node.attrib['id'] for node in root.findall('.//{*}testMethod')]
    if not identities or len(set(identities)) != len(identities):
        raise ValueError(f'{path.name}: empty or duplicate test inventory')
    return set(identities)


def junit(path):
    root = ET.parse(path).getroot()
    if root.tag not in ('testsuites', 'testsuite'):
        raise ValueError(f'{path.name}: not a JUnit report')
    cases = []
    for node in root.iter('testcase'):
        name = node.attrib['name']
        owner = node.get('class') or node.get('classname')
        seconds = float(node.get('time', '0'))
        if not owner or seconds < 0 or not math.isfinite(seconds):
            raise ValueError(f'{path.name}: invalid test identity or duration')
        cases.append({
            'id': f'{owner}::{name}', 'seconds': seconds,
            'failed': node.find('failure') is not None or node.find('error') is not None,
            'skipped': node.find('skipped') is not None,
        })
    if not cases:
        raise ValueError(f'{path.name}: empty JUnit report')
    if len({case['id'] for case in cases}) != len(cases):
        raise ValueError(f'{path.name}: duplicate executed test identities')
    return cases


def check_needs(needs):
    expected = {'php-shards', 'php-verification'}
    if set(needs) != expected or any(needs[key].get('result') != 'success' for key in expected):
        raise ValueError('All PHP shards and verification must succeed; cancelled/skipped jobs cannot pass.')


def escape(value):
    return html.escape(str(value)).replace('|', '&#124;').replace('\n', ' ')


def summarize(directory, needs):
    directory = Path(directory)
    errors, rows, slowest = [], [], []
    all_inventory, selected, executed = None, Counter(), Counter()
    try:
        check_needs(needs)
    except (ValueError, KeyError, TypeError) as error:
        errors.append(str(error))
    for shard in range(1, 5):
        folder = directory / f'php-shard-{shard}'
        try:
            baseline = inventory(folder / 'inventory-all.xml')
            if all_inventory is None:
                all_inventory = baseline
            elif baseline != all_inventory:
                raise ValueError('Shard baseline inventories differ')
            members = inventory(folder / f'inventory-{shard}.xml')
            selected.update(members)
            cases = junit(folder / f'php-{shard}.xml')
            executed.update(case['id'] for case in cases)
            if len(cases) != len(members):
                errors.append(f'Shard {shard}: executed {len(cases)} tests, expected {len(members)}')
            rows.append((f'Shard {shard}', cases))
        except (OSError, ET.ParseError, ValueError, KeyError) as error:
            errors.append(f'Shard {shard}: {error}')
    if all_inventory is not None:
        if set(selected) != all_inventory or any(count != 1 for count in selected.values()):
            errors.append('Shard inventories must cover the unsharded inventory exactly once')
        if len(executed) != len(all_inventory) or any(count != 1 for count in executed.values()):
            errors.append('Executed shards have missing or duplicate test identities')
    for label in ('preliminary', 'serial'):
        try:
            rows.append((label.title(), junit(directory / 'php-verification' / f'php-{label}.xml')))
        except (OSError, ET.ParseError, ValueError, KeyError) as error:
            errors.append(f'{label}: {error}')
    lines = ['## PHP test results', '', '| Invocation | Tests | Failed | Skipped | Sum of test seconds |',
             '| --- | ---: | ---: | ---: | ---: |']
    for label, cases in rows:
        failures = sum(case['failed'] for case in cases)
        skipped = sum(case['skipped'] for case in cases)
        lines.append(f'| {label} | {len(cases)} | {failures} | {skipped} | {sum(c["seconds"] for c in cases):.2f} |')
        if failures:
            errors.append(f'{label}: {failures} failed tests')
        slowest.extend((case['seconds'], label, case['id']) for case in cases)
    lines += ['', 'Test seconds are summed test durations, not parallel wall time. Preliminary checks intentionally repeat selected regressions.', '',
              '### Slowest 20 tests', '', '| Seconds | Invocation | Test |', '| ---: | --- | --- |']
    for seconds, label, name in sorted(slowest, reverse=True)[:20]:
        lines.append(f'| {seconds:.2f} | {label} | {escape(name)} |')
    jobs_file = directory / 'jobs.json'
    if jobs_file.exists():
        try:
            jobs = json.loads(jobs_file.read_text())
            lines += ['', '### Completed job elapsed times', '', '| Job | Minutes |', '| --- | ---: |']
            total = 0
            for job in jobs:
                if not job.get('started_at') or not job.get('completed_at'):
                    continue
                elapsed = (datetime.fromisoformat(job['completed_at'].replace('Z', '+00:00'))
                           - datetime.fromisoformat(job['started_at'].replace('Z', '+00:00'))).total_seconds() / 60
                total += elapsed
                lines.append(f'| {escape(job["name"])} | {elapsed:.2f} |')
            lines += ['', f'Sum of completed job elapsed time: **{total:.2f} runner minutes** (not billed minutes; excludes this running gate and unfinished jobs).']
        except (ValueError, KeyError, TypeError) as error:
            lines += ['', f'Job timing data unavailable: {escape(error)}']
    else:
        lines += ['', 'Job timing API data unavailable; test reports remain authoritative.']
    if errors:
        lines += ['', '### Verification failed', ''] + [f'- {escape(error)}' for error in errors]
    else:
        lines += ['', f'Coverage verified: **{len(all_inventory)} tests**, partitioned and executed once across four shards.']
    return '\n'.join(lines) + '\n', errors


def shard_command(path, report, root=Path(".")):
    identities = inventory(path)
    classes = sorted({identity.split('::', 1)[0].removeprefix('P\\') for identity in identities})
    # Pest 4.7.5 leaks --processes into serial shard discovery. Select with native
    # Pest first, then execute the exact selected classes without --shard.
    files = []
    for name in classes:
        parts = name.split('\\')
        if parts[0] != 'Tests' or any(not part.isidentifier() for part in parts):
            raise ValueError(f'Unsupported test class: {name}')
        file = root.joinpath('tests', *parts[1:]).with_suffix('.php')
        if not file.is_file():
            raise ValueError(f'Missing test file for {name}: {file}')
        files.append(str(file))
    configuration = ET.parse(root / 'phpunit.xml')
    suites = configuration.getroot().find('testsuites')
    if suites is None:
        raise ValueError('Missing testsuites in phpunit.xml')
    suites.clear()
    suite = ET.SubElement(suites, 'testsuite', name='CI shard')
    for file in files:
        ET.SubElement(suite, 'file').text = str(Path(file).resolve())
    descriptor, config_path = tempfile.mkstemp(prefix='.ci-shard-', suffix='.xml', dir=root)
    os.close(descriptor)
    configuration.write(config_path, encoding='utf-8', xml_declaration=True)
    pattern = r"^(?:P\\)?(?:" + '|'.join(re.escape(name) for name in classes) + r")::"
    return ['php', '-d', 'memory_limit=2G', 'vendor/bin/pest', '--parallel',
            '--processes=6', '--compact', '--filter', pattern, '--log-junit', str(report),
            '--configuration', config_path]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    summary_parser = commands.add_parser('summarize')
    summary_parser.add_argument('directory', type=Path)
    summary_parser.add_argument('--summary', type=Path, required=True)
    shard_parser = commands.add_parser('run-shard')
    shard_parser.add_argument('inventory', type=Path)
    shard_parser.add_argument('--report', type=Path, required=True)
    arguments = parser.parse_args()
    if arguments.command == 'run-shard':
        command = shard_command(arguments.inventory, arguments.report)
        try:
            return subprocess.run(command, check=False).returncode
        finally:
            Path(command[-1]).unlink(missing_ok=True)
    try:
        needs = json.loads(os.environ.get('PHP_NEEDS', '{}'))
    except ValueError:
        needs = {}
    summary, errors = summarize(arguments.directory, needs)
    with arguments.summary.open('a') as output:
        output.write(summary)
    print(summary)
    return 1 if errors else 0


if __name__ == '__main__':
    sys.exit(main())
