"""Balance complete PHP test files by recorded CI time, including new files."""

import argparse
import json
import math
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
GROUP_COUNT = 3


def split_tests(files, durations, count=GROUP_COUNT):
    files = sorted(files)
    if count < 1 or len(files) < count or len(files) != len(set(files)):
        raise ValueError("Each group needs at least one unique test file")
    weights = {name: durations.get(name, 1.0) for name in files}
    if any(not math.isfinite(value) or value <= 0 for value in weights.values()):
        raise ValueError("Test durations must be positive finite numbers")

    groups = [[] for _ in range(count)]
    totals = [0.0] * count
    for name in sorted(files, key=lambda name: (-weights[name], name)):
        index = min(range(count), key=lambda index: (totals[index], index))
        groups[index].append(name)
        totals[index] += weights[name]
    return [sorted(group) for group in groups], totals


def verify_report(report, expected):
    cases = ET.parse(report).findall(".//testcase")
    actual = {Path(case.get("file", "")).resolve() for case in cases}
    expected = {(ROOT / name).resolve() for name in expected}
    if not cases or actual != expected:
        raise ValueError(f"Test report does not cover the selected files: missing={expected - actual}, unexpected={actual - expected}")
    return len(cases)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--group", type=int, choices=range(1, GROUP_COUNT + 1), required=True)
    parser.add_argument("--verify-report", type=Path)
    args = parser.parse_args()

    # Discover the suite every time: the timings are weights, never an allowlist.
    files = [path.relative_to(ROOT).as_posix() for path in (ROOT / "tests/Wpunit").rglob("*Test.php")]
    durations = json.loads((ROOT / "tests/ci/php-test-durations.json").read_text())["seconds_by_file"]
    groups, totals = split_tests(files, durations)
    if args.verify_report:
        count = verify_report(args.verify_report, groups[args.group - 1])
        print(f"Verified {count} test cases covering every file in group {args.group}")
        return
    output = ROOT / "tests/_output/ci-group.txt"
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text("\n".join(groups[args.group - 1]) + "\n")
    for index, group in enumerate(groups):
        print(f"Group {index + 1}: {len(group)} files, estimated test time {totals[index]:.1f}s")
    print(f"Selected group {args.group}: {output.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
