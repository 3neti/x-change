"""Regression tests for CI coverage and fail-closed reporting."""
import json
import re
from pathlib import Path
import tempfile
import unittest
import xml.etree.ElementTree as ET

from ci_reports import summarize, shard_command

SUCCESS = {'php-shards': {'result': 'success'}, 'php-verification': {'result': 'success'}}


def write_inventory(path, identities):
    root = ET.Element('testSuite', xmlns='https://xml.phpunit.de/testSuite')
    tests = ET.SubElement(root, 'tests')
    for identity in identities:
        ET.SubElement(tests, 'testMethod', id=identity)
    ET.ElementTree(root).write(path)


def write_junit(path, identities, outcome=None):
    root = ET.Element('testsuites')
    suite = ET.SubElement(root, 'testsuite')
    for identity in identities:
        case = ET.SubElement(suite, 'testcase', name=identity, classname='Example', time='1.5')
        if outcome:
            ET.SubElement(case, outcome)
    ET.ElementTree(root).write(path)


class ReportTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'phpunit.xml').write_text('<phpunit bootstrap="vendor/autoload.php"><testsuites><testsuite name="All"><directory>tests</directory></testsuite></testsuites><groups><exclude><group>serial</group></exclude></groups></phpunit>')
        for index in range(1, 5):
            folder = self.root / f'php-shard-{index}'
            folder.mkdir()
            write_inventory(folder / 'inventory-all.xml', ['t1', 't2', 't3', 't4'])
            write_inventory(folder / f'inventory-{index}.xml', [f't{index}'])
            write_junit(folder / f'php-{index}.xml', [f't{index}'])
        folder = self.root / 'php-verification'
        folder.mkdir()
        write_junit(folder / 'php-preliminary.xml', ['t1'])
        write_junit(folder / 'php-serial.xml', ['serial'])

    def test_shard_runner_selects_exact_classes_with_six_workers(self):
        path = self.root / 'runner.xml'
        write_inventory(path, [r'P\Tests\Unit\ExampleTest::first', r'P\Tests\Unit\ExampleTest::second'])
        file = self.root / 'tests/Unit/ExampleTest.php'
        file.parent.mkdir(parents=True)
        file.touch()
        command = shard_command(path, self.root / 'result.xml', self.root)
        pattern = command[command.index('--filter') + 1]
        self.assertRegex(r'P\Tests\Unit\ExampleTest::first', pattern)
        self.assertRegex(r'Tests\Unit\ExampleTest::second with data set #1', pattern)
        self.assertIsNone(re.search(pattern, r'P\Tests\Unit\ExampleTestExtra::first'))
        self.assertIn('--processes=6', command)
        configuration = ET.parse(command[-1])
        self.assertEqual([node.text for node in configuration.findall('.//file')], [str(file.resolve())])
        self.assertEqual(configuration.find('.//group').text, 'serial')
        self.assertEqual(configuration.getroot().get('bootstrap'), 'vendor/autoload.php')
        self.assertNotIn('--shard', ' '.join(command))

    def test_shard_runner_rejects_unresolved_test_files(self):
        path = self.root / 'missing.xml'
        write_inventory(path, [r'P\Tests\Unit\MissingTest::first'])
        with self.assertRaisesRegex(ValueError, 'Missing test file'):
            shard_command(path, self.root / 'result.xml', self.root)

    def test_complete_reports_allow_intentional_preliminary_recheck(self):
        summary, errors = summarize(self.root, SUCCESS)
        self.assertEqual(errors, [])
        self.assertIn('4 tests', summary)

    def test_failed_cancelled_skipped_and_missing_jobs_fail(self):
        for outcome in ['failure', 'cancelled', 'skipped', None]:
            with self.subTest(outcome=outcome):
                needs = {**SUCCESS, 'php-shards': {'result': outcome}}
                self.assertTrue(summarize(self.root, needs)[1])
        self.assertTrue(summarize(self.root, {})[1])

    def test_missing_and_malformed_report_fail(self):
        path = self.root / 'php-shard-1/php-1.xml'
        path.unlink()
        self.assertTrue(summarize(self.root, SUCCESS)[1])
        path.write_text('<invalid')
        self.assertTrue(summarize(self.root, SUCCESS)[1])
        path.write_text('<unrelated/>')
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_empty_and_duplicate_reports_fail(self):
        path = self.root / 'php-shard-1/php-1.xml'
        write_junit(path, [])
        self.assertTrue(summarize(self.root, SUCCESS)[1])
        write_junit(path, ['t1', 't1'])
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_missing_serial_report_fails(self):
        (self.root / 'php-verification/php-serial.xml').unlink()
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_shard_omission_or_overlap_fails(self):
        write_inventory(self.root / 'php-shard-4/inventory-4.xml', ['t3'])
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_inconsistent_baselines_fail(self):
        write_inventory(self.root / 'php-shard-2/inventory-all.xml', ['t1', 't2', 't3'])
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_execution_count_must_match_shard_inventory(self):
        write_junit(self.root / 'php-shard-1/php-1.xml', ['t1', 'extra'])
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_repeated_execution_across_shards_fails(self):
        write_junit(self.root / 'php-shard-2/php-2.xml', ['t1'])
        self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_failure_and_error_results_fail_even_if_job_claims_success(self):
        for outcome in ['failure', 'error']:
            write_junit(self.root / 'php-shard-1/php-1.xml', ['t1'], outcome)
            self.assertTrue(summarize(self.root, SUCCESS)[1])

    def test_documented_skipped_tests_remain_counted(self):
        write_junit(self.root / 'php-shard-1/php-1.xml', ['t1'], 'skipped')
        self.assertFalse(summarize(self.root, SUCCESS)[1])

    def test_test_names_are_escaped_in_markdown(self):
        write_junit(self.root / 'php-shard-1/php-1.xml', ['<script>|test'])
        summary, _ = summarize(self.root, SUCCESS)
        self.assertNotIn('<script>', summary)
        self.assertIn('&lt;script&gt;&#124;test', summary)

    def test_job_elapsed_time_excludes_incomplete_jobs(self):
        (self.root / 'jobs.json').write_text(json.dumps([
            {'name': 'PHP shard 1/4', 'started_at': '2026-10-28T00:00:00Z', 'completed_at': '2026-10-28T00:02:00Z'},
            {'name': 'PHP tests', 'started_at': '2026-10-28T00:02:00Z', 'completed_at': None},
        ]))
        summary, errors = summarize(self.root, SUCCESS)
        self.assertFalse(errors)
        self.assertIn('2.00 runner minutes', summary)

    def test_unavailable_job_api_does_not_invalidate_test_evidence(self):
        (self.root / 'jobs.json').write_text('invalid json')
        self.assertFalse(summarize(self.root, SUCCESS)[1])


if __name__ == '__main__':
    unittest.main()
