"""Reject incomplete CI evidence; only exact, successful PR trees may be reused."""

import copy
import importlib.util
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("reuse_pr_checks", ROOT / "tests/ci/reuse_pr_checks.py")
reuse = importlib.util.module_from_spec(spec)
spec.loader.exec_module(reuse)


class ReuseChecksTest(unittest.TestCase):
    def setUp(self):
        self.repository = "owner/club"
        self.commit, self.tree, self.head = "a" * 40, "b" * 40, "c" * 40
        self.pr = {
            "number": 7, "merged": True, "merge_commit_sha": self.commit,
            "base": {"ref": "main", "repo": {"full_name": self.repository}},
            "head": {"sha": self.head, "repo": {"full_name": self.repository}},
        }
        self.run = {
            "id": 12, "run_attempt": 2, "event": "pull_request", "status": "completed",
            "conclusion": "success", "path": ".github/workflows/ci.yml", "head_sha": self.head,
            "head_repository": {"full_name": self.repository},
        }
        self.artifacts = [{"name": f"ci-tree-2-7-{self.tree}", "expired": False}]
        self.jobs = [{"name": name, "conclusion": "success"} for name in reuse.REQUIRED_JOBS]

    def api(self, path):
        prefix = f"repos/{self.repository}"
        if path == f"{prefix}/commits/{self.commit}/pulls?per_page=100":
            return [{"number": 7}]
        if path == f"{prefix}/pulls/7":
            return self.pr
        if path == f"{prefix}/actions/workflows/ci.yml/runs?event=pull_request&head_sha={self.head}&per_page=20":
            return {"workflow_runs": [self.run]}
        if path == f"{prefix}/actions/runs/12/artifacts?per_page=100":
            return {"artifacts": self.artifacts}
        if path == f"{prefix}/actions/runs/12/attempts/2/jobs?per_page=100":
            return {"jobs": self.jobs}
        self.fail(f"Unexpected API request: {path}")

    def plan(self, **kwargs):
        return reuse.plan_checks(
            kwargs.get("api", self.api), kwargs.get("event", "push"),
            kwargs.get("ref", "refs/heads/main"), self.repository, self.commit,
            kwargs.get("tree", self.tree),
        )

    def test_matching_tree_from_successful_merged_pr_is_reused(self):
        self.assertEqual(12, self.plan())

    def test_any_changed_file_changes_the_tree_and_requires_checks(self):
        self.assertIsNone(self.plan(tree="d" * 40))

    def test_pr_manual_and_other_branch_runs_always_test_without_api(self):
        def no_api(path):
            self.fail("These events must not look up reusable checks")
        for event, ref in [("pull_request", "refs/pull/7/merge"), ("workflow_dispatch", "refs/heads/main"), ("push", "refs/heads/feature")]:
            with self.subTest(event=event):
                self.assertIsNone(self.plan(api=no_api, event=event, ref=ref))

    def test_direct_push_without_associated_pr_runs_checks(self):
        self.assertIsNone(self.plan(api=lambda path: []))

    def test_pr_must_be_merged_to_this_commit_and_main_in_same_repository(self):
        for field, value in [("merged", False), ("merge_commit_sha", "d" * 40), ("base", {"ref": "other", "repo": {"full_name": self.repository}}), ("head", {"sha": self.head, "repo": None})]:
            with self.subTest(field=field):
                original = copy.deepcopy(self.pr)
                self.pr[field] = value
                self.assertIsNone(self.plan())
                self.pr = original

    def test_run_identity_and_success_are_required(self):
        for field, value in [("event", "push"), ("status", "in_progress"), ("conclusion", "failure"), ("conclusion", "cancelled"), ("path", ".github/workflows/other.yml"), ("head_sha", "d" * 40), ("head_repository", {"full_name": "fork/club"})]:
            with self.subTest(field=field, value=value):
                original = copy.deepcopy(self.run)
                self.run[field] = value
                self.assertIsNone(self.plan())
                self.run = original

    def test_missing_expired_wrong_pr_or_old_attempt_markers_are_rejected(self):
        for artifacts in [[], [{"name": f"ci-tree-2-7-{self.tree}", "expired": True}], [{"name": f"ci-tree-1-7-{self.tree}", "expired": False}], [{"name": f"ci-tree-2-8-{self.tree}", "expired": False}]]:
            with self.subTest(artifacts=artifacts):
                self.artifacts = artifacts
                self.assertIsNone(self.plan())

    def test_every_required_job_must_be_present_once_and_successful(self):
        for name in reuse.REQUIRED_JOBS:
            for state in ["failure", "skipped", "cancelled", None]:
                with self.subTest(name=name, state=state):
                    jobs = copy.deepcopy(self.jobs)
                    next(job for job in self.jobs if job["name"] == name)["conclusion"] = state
                    self.assertIsNone(self.plan())
                    self.jobs = jobs
        self.jobs.pop()
        self.assertIsNone(self.plan())

    def test_duplicate_job_names_are_ambiguous(self):
        self.jobs.append(copy.deepcopy(self.jobs[0]))
        self.assertIsNone(self.plan())

    def test_api_errors_and_malformed_responses_fall_back_to_checks(self):
        for error in [subprocess.CalledProcessError(1, "gh"), subprocess.TimeoutExpired("gh", 20), ValueError("invalid JSON"), KeyError("missing field")]:
            def broken_api(path):
                raise error
            with self.subTest(error=error):
                self.assertIsNone(self.plan(api=broken_api))


if __name__ == "__main__":
    unittest.main()
