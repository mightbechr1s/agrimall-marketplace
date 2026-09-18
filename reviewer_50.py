#!/usr/bin/env python3
"""
Reviewer for AniMAL 50% backup.
Checks that the backup folder contains the expected source tree
and no heavy dev artefacts (node_modules, .git, logs).
Usage:
    python reviewer_50.py [path-to-backup]
Defaults to Desktop\\AniMAL_Backup_50per
"""
import sys, os, json, hashlib
from pathlib import Path

REQUIRED_DIRS = [
    "src", "public", "database", "config", "docs", "tests", "assets"
]
REQUIRED_FILES = [
    "README.md",
    "package.json",
    "database/schema.sql",
    "database/seed.php",
    "config/config.php",
    "src/bootstrap.php",
    "src/DB.php",
    "src/functions.php",
    "public/index.php",
    "public/browse.php",
    "public/listing.php",
    "public/dashboard.php",
    "public/login.php",
    "public/register.php",
    "public/logout.php",
    "public/order_request.php",
    "public/order_action.php",
    "public/order.php",
]
FORBIDDEN_DIRS = [".git", "node_modules", ".hallmark", ".impeccable", "logs"]
FORBIDDEN_FILES = ["server.log"]

def md5(path):
    h = hashlib.md5()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()

def check(root: Path):
    ok = True
    report = {"missing_dirs": [], "missing_files": [], "forbidden_found": [], "hashes": {}}
    for d in REQUIRED_DIRS:
        if not (root / d).is_dir():
            report["missing_dirs"].append(d)
            ok = False
    for f in REQUIRED_FILES:
        p = root / f
        if not p.is_file():
            report["missing_files"].append(f)
            ok = False
        else:
            report["hashes"][f] = md5(p)
    for d in FORBIDDEN_DIRS:
        if (root / d).exists():
            report["forbidden_found"].append(d)
            ok = False
    for f in FORBIDDEN_FILES:
        if (root / f).exists():
            report["forbidden_found"].append(f)
            ok = False
    return ok, report

def main():
    target = Path(sys.argv[1]) if len(sys.argv) > 1 else Path.home() / "Desktop" / "AniMAL_Backup_50per"
    if not target.exists():
        print(f"ERROR: backup path not found: {target}")
        sys.exit(2)
    ok, report = check(target)
    print(json.dumps(report, indent=2))
    if ok:
        print("\n[OK] 50% backup looks good.")
        sys.exit(0)
    else:
        print("\n[FAIL] Issues found.")
        sys.exit(1)

if __name__ == "__main__":
    main()