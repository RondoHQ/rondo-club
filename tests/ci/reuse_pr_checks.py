"""Reuse successful PR checks only for an identical, recorded Git tree."""

import json
import os
import re
import subprocess
from pathlib import Path

REQUIRED_JOBS = {
    "Plan checks",
    "JavaScript lint and build",
    "PHP coding standards",
    "Capacitor spike checks",
    "PHP tests",
    *(f"PHP tests (group {group}/3)" for group in range(1, 4)),
}


def github_api(path):
    result = subprocess.run(
        ["gh", "api", path], check=True, capture_output=True, text=True, timeout=20
    )
    return json.loads(result.stdout)


def find_verified_run(api, repository, commit, tree):
    prefix = f"repos/{repository}"
    for associated in api(f"{prefix}/commits/{commit}/pulls?per_page=100"):
        pr = api(f"{prefix}/pulls/{associated['number']}")
        if not (
            pr.get("merged") is True
            and pr.get("merge_commit_sha") == commit
            and pr["base"]["ref"] == "main"
            and pr["base"]["repo"]["full_name"] == repository
            and (pr["head"].get("repo") or {}).get("full_name") == repository
        ):
            continue
        head = pr["head"]["sha"]
        runs = api(f"{prefix}/actions/workflows/ci.yml/runs?event=pull_request&head_sha={head}&per_page=20")
        for run in runs["workflow_runs"]:
            if not (
                run.get("event") == "pull_request"
                and run.get("status") == "completed"
                and run.get("conclusion") == "success"
                and run.get("path") == ".github/workflows/ci.yml"
                and run.get("head_sha") == head
                and run["head_repository"]["full_name"] == repository
            ):
                continue
            run_id, attempt = int(run["id"]), int(run["run_attempt"])
            artifacts = api(f"{prefix}/actions/runs/{run_id}/artifacts?per_page=100")["artifacts"]
            marker = f"ci-tree-{attempt}-{pr['number']}-{tree}"
            if not any(item.get("name") == marker and item.get("expired") is False for item in artifacts):
                continue
            jobs = api(f"{prefix}/actions/runs/{run_id}/attempts/{attempt}/jobs?per_page=100")["jobs"]
            # A green overall run alone is insufficient: skipped jobs must not count.
            if all(
                len(matches := [job for job in jobs if job.get("name") == name]) == 1
                and matches[0].get("conclusion") == "success"
                for name in REQUIRED_JOBS
            ):
                return run_id
    return None


def plan_checks(api, event, ref, repository, commit, tree):
    # PRs always test; manual runs remain an explicit way to recheck main.
    if event != "push" or ref != "refs/heads/main":
        return None
    try:
        return find_verified_run(api, repository, commit, tree)
    except (subprocess.SubprocessError, OSError, ValueError, KeyError, TypeError):
        print("No reusable proof: GitHub lookup unavailable or incomplete; running all checks.")
        return None


def main():
    tree = subprocess.check_output(["git", "rev-parse", "HEAD^{tree}"], text=True).strip()
    if not re.fullmatch(r"[0-9a-f]{40}", tree):
        raise ValueError("Invalid Git tree")
    repository = os.environ["GITHUB_REPOSITORY"]
    run_id = plan_checks(
        github_api, os.environ["GITHUB_EVENT_NAME"], os.environ["GITHUB_REF"],
        repository, os.environ["GITHUB_SHA"], tree,
    )
    Path(os.environ["RUNNER_TEMP"], "ci-tested-tree.txt").write_text(tree + "\n")
    with open(os.environ["GITHUB_OUTPUT"], "a") as output:
        output.write(f"tree={tree}\nreuse={'true' if run_id else 'false'}\n")
    summary = (
        f"Reusing all checks from https://github.com/{repository}/actions/runs/{run_id}; identical Git tree `{tree}`."
        if run_id else f"Running all checks for Git tree `{tree}`."
    )
    print(summary)
    with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
        output.write(summary + "\n")


if __name__ == "__main__":
    main()
