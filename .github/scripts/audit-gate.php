<?php

/**
 * CI dependency-audit gate.
 *
 * Why a script rather than an inline severity count: `composer audit` has no way
 * to accept a SPECIFIC advisory, so an all-or-nothing check meets a single
 * genuinely-unfixable high finding and then either red-lights the security job
 * permanently or gets deleted. This keeps the gate hard (any high/critical fails
 * the build) while allowing a DOCUMENTED, DATED exception per advisory, and it
 * actively nags when an exception has gone stale (the advisory is no longer
 * reported, so the entry should be removed) or has passed its review date.
 *
 * Dependency-free on purpose: adding an audit tool would add its own dependency
 * tree — and its own audit surface. Plain PHP, no autoloader, no framework.
 *
 * Usage: php .github/scripts/audit-gate.php <path-to-composer-audit-json>
 * The JSON is the output of `composer audit --locked --format=json`.
 */

const BLOCKING_SEVERITIES = ['high', 'critical'];

/**
 * Advisories consciously accepted, with the rationale in-line so the next
 * reader (or auditor) sees WHY without digging through git history. Match is by
 * advisory id or CVE — whichever the tooling reports.
 *
 * EMPTY BY DEFAULT, and it should stay that way as long as possible. An
 * allowlist entry is the LAST rung of the ladder, not the first:
 *
 *   1. `composer update <package>` — most advisories are satisfied by a version
 *      the declared constraint ALREADY permits, i.e. the lockfile is stale
 *      rather than the upstream constraint being real. Costs nothing and leaves
 *      composer.json untouched.
 *   2. Widen the constraint in composer.json — only when the installed major is
 *      genuinely behind. Note what it unblocks.
 *   3. An entry here — only when NO compatible patched version exists at all.
 *
 * Diagnose which rung you are on with:
 *   composer audit --locked --format=json   → package, affected versions, CVE
 *   composer why <package>                  → who pulls it in
 *   composer outdated <package>             → what versions exist
 *
 * Shape of an entry:
 *
 *   [
 *       'ids' => ['CVE-2026-00000', 'PKSA-xxxx-xxxx-xxxx'],
 *       'package' => 'vendor/package',
 *       'reason' => 'What the vulnerability is, why no upgrade path exists, and '
 *           . 'why it is not exploitable in THIS codebase. Be specific — '
 *           . '"low risk" is not a rationale.',
 *       'reviewBy' => '2026-12-31',
 *   ],
 */
const ALLOWLISTED_ADVISORIES = [];

$auditJsonPath = $argv[1] ?? null;

if ($auditJsonPath === null) {
    fwrite(STDERR, "audit-gate: missing path to composer audit JSON output\n");
    exit(2);
}

if (!is_file($auditJsonPath)) {
    fwrite(STDERR, "audit-gate: '{$auditJsonPath}' does not exist\n");
    exit(2);
}

$decodedReport = json_decode((string) file_get_contents($auditJsonPath), true);

if (!is_array($decodedReport)) {
    // A malformed report must never be read as "no findings". `composer audit`
    // exits non-zero when it finds something, so the workflow captures its
    // output with `|| true` — which means a crash would otherwise leave an
    // empty file that parses as a clean bill of health.
    fwrite(STDERR, "audit-gate: could not parse '{$auditJsonPath}' as JSON — refusing to report a clean result\n");
    exit(2);
}

$blockingAdvisories = [];

foreach (($decodedReport['advisories'] ?? []) as $packageAdvisories) {
    if (!is_array($packageAdvisories)) {
        continue;
    }

    foreach ($packageAdvisories as $advisory) {
        $severity = strtolower((string) ($advisory['severity'] ?? ''));

        if (!in_array($severity, BLOCKING_SEVERITIES, true)) {
            continue;
        }

        $identifiers = array_values(array_filter([
            $advisory['advisoryId'] ?? null,
            $advisory['cve'] ?? null,
        ]));

        $blockingAdvisories[] = [
            'severity' => $severity,
            'package' => (string) ($advisory['packageName'] ?? 'unknown'),
            'title' => (string) ($advisory['title'] ?? ''),
            'identifiers' => $identifiers,
        ];
    }
}

$acceptedAdvisories = [];
$unacceptedAdvisories = [];

foreach ($blockingAdvisories as $advisory) {
    $matchedEntry = null;

    foreach (ALLOWLISTED_ADVISORIES as $allowlistEntry) {
        if (array_intersect($advisory['identifiers'], $allowlistEntry['ids']) !== []) {
            $matchedEntry = $allowlistEntry;

            break;
        }
    }

    if ($matchedEntry !== null) {
        $acceptedAdvisories[] = ['advisory' => $advisory, 'entry' => $matchedEntry];
    } else {
        $unacceptedAdvisories[] = $advisory;
    }
}

foreach ($acceptedAdvisories as $accepted) {
    $isPastReview = $accepted['entry']['reviewBy'] < date('Y-m-d');
    $reviewNote = $isPastReview ? ' (REVIEW OVERDUE — re-justify or remove)' : '';

    printf(
        "audit-gate: allowing %s %s [%s] until %s%s\n",
        $accepted['advisory']['severity'],
        $accepted['advisory']['package'],
        implode(', ', $accepted['entry']['ids']),
        $accepted['entry']['reviewBy'],
        $reviewNote
    );
}

// A stale allowlist entry (its advisory no longer appears) is a smell: the
// exception outlived the vulnerability. Surface it, but don't fail the build on
// it alone — the entry is harmless until someone prunes it.
foreach (ALLOWLISTED_ADVISORIES as $allowlistEntry) {
    $isStillMatched = false;

    foreach ($acceptedAdvisories as $accepted) {
        if ($accepted['entry'] === $allowlistEntry) {
            $isStillMatched = true;

            break;
        }
    }

    if ($isStillMatched) {
        continue;
    }

    printf(
        "audit-gate: allowlist entry for %s [%s] no longer matches any finding — remove it from audit-gate.php\n",
        $allowlistEntry['package'],
        implode(', ', $allowlistEntry['ids'])
    );
}

if ($unacceptedAdvisories !== []) {
    fwrite(STDERR, sprintf(
        "\naudit-gate: %d un-allowlisted high/critical advisory(ies) — failing the build:\n",
        count($unacceptedAdvisories)
    ));

    foreach ($unacceptedAdvisories as $advisory) {
        fwrite(STDERR, sprintf(
            "  - %s %s [%s] %s\n",
            $advisory['severity'],
            $advisory['package'],
            implode(', ', $advisory['identifiers']) ?: 'no identifier reported',
            $advisory['title']
        ));
    }

    fwrite(STDERR,
        "\nFix by upgrading (prefer an in-range `composer update <package>` over widening the constraint), "
        . "or, only if genuinely unfixable, add a documented+dated entry to ALLOWLISTED_ADVISORIES in this file.\n"
    );

    exit(1);
}

echo "\naudit-gate: no un-allowlisted high/critical advisories. OK.\n";
