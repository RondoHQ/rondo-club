"""Guard complete, deterministic coverage when the PHP test suite changes."""

import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("split_php_tests", ROOT / "tests/ci/split_php_tests.py")
splitter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(splitter)


class TestGroupsTest(unittest.TestCase):
    def test_every_current_file_is_selected_exactly_once(self):
        files = [path.relative_to(ROOT).as_posix() for path in (ROOT / "tests/Wpunit").rglob("*Test.php")]
        durations = json.loads((ROOT / "tests/ci/php-test-durations.json").read_text())["seconds_by_file"]
        groups, _ = splitter.split_tests(files, durations)
        self.assertEqual(sorted(files), sorted(name for group in groups for name in group))
        self.assertTrue(all(groups))

    def test_new_renamed_and_deleted_files_do_not_break_coverage(self):
        files = ["slow.php", "renamed.php", "new.php", "existing.php"]
        durations = {"slow.php": 10, "old-name.php": 50, "deleted.php": 100, "existing.php": 2}
        groups, totals = splitter.split_tests(files, durations)
        self.assertEqual(sorted(files), sorted(name for group in groups for name in group))
        self.assertEqual((groups, totals), splitter.split_tests(reversed(files), durations))

    def test_long_tests_are_spread_across_groups(self):
        groups, totals = splitter.split_tests(["a", "b", "c", "d", "e", "f"], dict(zip("abcdef", [9, 8, 7, 3, 2, 1])))
        self.assertEqual([10, 10, 10], totals)
        self.assertEqual([["a", "f"], ["b", "e"], ["c", "d"]], groups)

    def test_empty_duplicate_or_invalid_inputs_fail_closed(self):
        for files, durations in [([], {}), (["a", "a", "b"], {}), (["a", "b", "c"], {"a": -1}), (["a", "b", "c"], {"a": float("nan")})]:
            with self.subTest(files=files, durations=durations), self.assertRaises(ValueError):
                splitter.split_tests(files, durations)

    def test_report_must_cover_every_selected_file_and_no_others(self):
        with tempfile.TemporaryDirectory() as directory:
            report = Path(directory) / "report.xml"
            file = (ROOT / "tests/Wpunit/ExampleTest.php").as_posix()
            report.write_text(f'<testsuites><testsuite><testcase file="{file}"/></testsuite></testsuites>')
            self.assertEqual(1, splitter.verify_report(report, ["tests/Wpunit/ExampleTest.php"]))
            for expected in [[], ["tests/Wpunit/OtherTest.php"], ["tests/Wpunit/ExampleTest.php", "tests/Wpunit/OtherTest.php"]]:
                with self.subTest(expected=expected), self.assertRaises(ValueError):
                    splitter.verify_report(report, expected)
            report.write_text("<testsuites/>")
            with self.assertRaises(ValueError):
                splitter.verify_report(report, [])


if __name__ == "__main__":
    unittest.main()
