# Reviewer Guide

How to review changes in this repository, written from what has actually gone
wrong here.

This is **not** a summary of the system, and it is not a list of fixes. Changes in
this project have failed in a small number of recurring ways, and those failures
share a shape. Knowing the shape is what lets a reviewer catch the next one. Where
a section names a specific trap, it is because it has already cost real time, not
because it is interesting.

For what the system *is*, see `docs/SRS.md`. For whether each requirement is met,
see `docs/traceability-matrix.md`. For decisions not traceable to a requirement,
see `docs/decisions.md`.

---

## The failure mode worth learning from

Almost every significant mistake here — in code and in our own verification — was
the same mistake:

> **An assertion that could not observe what it claimed to observe.**

It looked like a passing check and produced a wrong conclusion. The variants are
worth recognising on sight:

| The check | Why it could not observe |
|---|---|
| `height > 0` on an element | The element was `visibility: hidden`; it had a box, and nothing on screen |
| "the click was clipped" | The box was *wider* than its text, so the trigger condition was backwards |
| "the new visitor saw a cookie banner" | A fresh browser context manufactured a fake new device |
| "the user is logged out" | The probe was still logged in; `login.php` redirects authenticated sessions |
| "the scan found no issues" | A regex matched the *word* `query` in `$query = $_GET` |
| "no raw exception is shown" | The assertion matched a catch block's own documentation |
| "the polyglot is rejected" | The file had been quarantined by antivirus and never reached the check |
| "the export cap works" | The form had failed for an unrelated reason, so nothing was created either way |
| "the sitemap index is 7 bytes" | PowerShell counted *lines*, not bytes |

Two of these produced **false alarms** (flagging correct code) and several
produced **false comfort** (passing over broken code). Both directions cost the
same.

**The review question that catches most of them:** *what would have to be true for
this check to be wrong?* If the answer is "nothing", the check cannot fail and
proves nothing. If the answer involves the check's own harness, it is fragile.

### Assertions must name the reason, not the outcome

The recurring false-comfort case. "The listing was not created" passes just as
happily when the *image* was rejected as when the *form* was malformed. Assert
which check fired, not merely that the outcome looks right. Where the application
reports a specific reason, match that reason.

### Prove the guard can fail

A regression test that has never gone red is not evidence. Before trusting a new
assertion, reintroduce the defect and confirm the suite fails, then restore it and
confirm it passes. If a guard cannot be made to fail, either the guard is
ineffective or the harness around it is wrong — both need fixing before the claim
is worth anything.

---

## Verifying changes here

**Force the path, do not infer it.** Branches guarded by a cap or a threshold are
untested until something reaches them. Temporarily lower the limit, observe the
behaviour, restore it. Confirm restoration explicitly — a limit left lowered is a
defect that looks like a fix.

**End-to-end beats unit for anything touching the filesystem, the network or the
database.** The missing upload directory passed the entire unit suite and was found
only by logging in and posting a real file through the form. A unit test cannot
observe a directory that is not there.

**Check the status code, not just the body.** A response that looks right at the
wrong status code is a defect that hides well: dashboards were once broken while
every page still "returned 200". Likewise, prefer asserting on something
observable over something derived.

**Run the suite on more than one interpreter.** The suite passes on the XAMPP
build and on the `php` on `PATH`. They differ in loaded extensions, and the
difference has been load-bearing — see the environment notes below.

**Optional is not the same as silent.** Five `catch` blocks were empty, each
quietly discarding a database error behind a graceful-degradation comment. The
degradation was right; the silence was not, because a failing query then left no
trace anywhere. When a value is optional, degrade *and* record.

---

## Environment notes that will mislead you

These are properties of this machine and its tooling, not of the application. Each
one has produced a confident wrong answer.

**Two different PHP binaries.** `C:\xampp\php\php.exe` (8.2) loads `ext/fileinfo`;
the `php` first on `PATH` (8.5, winget) does not, and neither loads GD. The `npm`
scripts use the `PATH` one. Running a check by hand against a *different*
interpreter than CI will happily miss an unguarded extension call.

**PowerShell writes a UTF-8 BOM.** `Set-Content -Encoding utf8` and
`Out-File -Encoding utf8` prepend `EF BB BF`. A probe file written that way
returns three bytes of BOM and no application output, which reads exactly like a
silent failure. Write probe files with a BOM-less UTF-8 encoder.

**PowerShell counts lines, not bytes, for native command output.** Assigning
`curl.exe` output to a variable yields an array; `.Length` is the element count.
This produced a wrong "7 bytes" figure in a document. Measure bytes by writing to
a file and inspecting it, or use a tool that reports them.

**`$LASTEXITCODE` is unreliable here.** The same script has reported both 0 and 1
for one native command. To branch on an exit code, use
`Start-Process -PassThru` and read `.ExitCode`.

**`Set-Location` does not move .NET's working directory.** File APIs such as
`[IO.File]::ReadAllText` and `WriteAllText` resolve relative paths against the
process directory, not the PowerShell location. Pass absolute paths.

**These sources carry trailing whitespace on blank lines.** Exact-string
replacements that look correct fail to match. Prefer a whitespace-tolerant
replacement, or read the file's real bytes before editing it.

**Repeated automated logins trip the login rate limiter.** A probe that logs in
more than a few times in quick succession is blocked, and subsequent checks fail
for a reason unrelated to the thing under test. Clear the rate-limit table between
runs, and confirm a failed check is not a rate-limit artefact before believing it.

**Antivirus removes deliberately hostile test payloads.** A webshell-shaped
fixture is quarantined on write, which aborts the HTTP request mid-transfer and
makes a healthy endpoint look broken. Keep such fixtures opt-in, and check whether
a file survived before asserting anything about its contents.

---

## Traps in the codebase itself

**`git` does not track empty directories.** A directory that exists only at
runtime must either be created by the code or held open by a placeholder file. If
`.gitignore` excludes a directory's contents, re-including the directory itself
also re-includes everything in it, and needs a second rule to exclude the contents
again. Getting this wrong commits real uploaded files.

**`.htaccess` rules apply to subdirectories.** The uploads guard does not need
duplicating per level. If a subdirectory seems unprotected, the guard is probably
absent from the parent, not the child.

**`500.php` is deliberately self-contained** and takes no input from the handler;
it generates its own reference code. Do not "fix" it to read one. Note that an
output buffer opened during shutdown is discarded, which is why the fatal path
does not use one — the exception path and the shutdown path are not
interchangeable, and look identical until measured.

**Identifier interpolation cannot be parameterised.** Where a table or column name
must vary, it is allow-listed and pattern-checked. Grep for backtick-quoted or
concatenated identifiers when reviewing a new query, and check what happens when
the identifier is neither.

**Counting a fetched page is not counting.** Totals for any paginated list come
from a separate aggregate query. `count($page)` silently caps every total at the
page size, which corrupts dashboard figures without failing anything.

**Generated files are not evidence.** A committed report, snapshot or dump goes
stale the moment the code moves, and reads as a result nobody can reproduce. Keep
the tool, ignore the output, and regenerate on demand. If a default in such a tool
drifts from the documented dev port, it has been scanning nothing.

---

## Before you approve

- Does the change do what the commit message says, and nothing more?
- Could any new assertion pass for the wrong reason? Name the reason it checks.
- Has each new assertion been made to fail at least once?
- Is anything claimed in a document that is not derived from the code, or that has
  not been re-measured?
- For anything touching uploads, error pages, or configuration: has it been run
  on both interpreters, and end-to-end?
- Are the limits of the change written down where a panelist will find them?

A change that cannot be shown to fail, and cannot be shown to work, is not
finished — and saying so plainly is more useful than a green tick that means
nothing.