<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Validates the Claude Code engineering framework in .claude/.
 *
 * That directory is several thousand lines of frontmatter and cross-references
 * that fail SILENTLY: a mistyped frontmatter key is ignored at load time, a
 * renamed class leaves a skill quoting code that no longer exists, a skill whose
 * name collides with a bundled command resolves unpredictably, and a guard hook
 * that crashes exits non-zero — which Claude Code treats as a non-blocking
 * error, so a broken guard fails OPEN.
 *
 * CLAUDE.md requires every convention to ship with a guardrail. This is the
 * tooling's own.
 *
 * Run it on the host or on a CI runner: the guard-hook fixture table shells out
 * to the hook, which needs `bash` and `jq`. Both are missing from some container
 * images, and this command reports that as a hard failure rather than skipping —
 * a check that cannot catch an omission is not a check.
 */
class ValidateClaudeConfigCommand extends Command
{

    protected $signature = 'app:validate-claude-config';

    protected $description = 'Validate the Claude Code engineering framework in .claude/ (frontmatter, cross-references, permission floor, and guard-hook behaviour).';

    /**
     * Frontmatter keys Claude Code recognises on a SKILL.md. Anything else is
     * silently ignored at load time, which is why an unknown key is a failure.
     *
     * @var array<int, string>
     */
    private const array SUPPORTED_SKILL_KEYS = [
        'name',
        'description',
        'argument-hint',
        'disable-model-invocation',
        'user-invocable',
        'allowed-tools',
        'disallowed-tools',
        'model',
        'effort',
        'license',
        'metadata',
    ];

    /**
     * Frontmatter keys Claude Code recognises on a .claude/agents/*.md subagent.
     *
     * @var array<int, string>
     */
    private const array SUPPORTED_AGENT_KEYS = [
        'name',
        'description',
        'tools',
        'disallowedTools',
        'skills',
        'model',
        'permissionMode',
        'effort',
        'maxTurns',
        'color',
    ];

    /**
     * Tools that can modify a file. An agent holding any of these is not
     * read-only, however firmly its prose claims otherwise.
     *
     * @var array<int, string>
     */
    private const array FILE_MUTATING_TOOL_NAMES = ['Edit', 'Write', 'NotebookEdit', 'MultiEdit'];

    /**
     * Every user-invocable project skill carries this prefix so it cannot
     * collide with a Claude Code built-in command in the slash menu.
     */
    private const string USER_INVOCABLE_SKILL_PREFIX = 'gate-';

    /**
     * The conductor is a reviewed exemption from the prefix rule: it is not a
     * gate, so a `gate-` name would misdescribe it. Recorded explicitly rather
     * than allowed to pass unnoticed.
     *
     * @var array<int, string>
     */
    private const array PREFIX_EXEMPT_SKILL_NAMES = ['work-item'];

    /**
     * Bundled Claude Code command names. A project skill taking one of these
     * appears BESIDE the built-in in the slash menu rather than replacing it,
     * so the user picks by row and may run something entirely unrelated.
     *
     * @var array<int, string>
     */
    private const array KNOWN_BUILT_IN_COMMAND_NAMES = [
        'init',
        'review',
        'design',
        'run',
        'debug',
        'simplify',
        'schedule',
        'loop',
        'ticket',
        'code-review',
        'security-review',
        'verify',
        'compact',
        'clear',
        'help',
        'config',
        'skills',
        'mcp',
    ];

    /**
     * Documented truncation cap on the combined description text Claude Code
     * shows in the skill listing.
     */
    private const int SKILL_LISTING_CHARACTER_CAP = 1536;

    /**
     * @var array<int, string>
     */
    private const array VALID_EFFORT_LEVELS = ['low', 'medium', 'high', 'xhigh', 'max'];

    /**
     * @var array<int, string>
     */
    private const array VALID_AGENT_COLORS = [
        'red',
        'blue',
        'green',
        'yellow',
        'purple',
        'orange',
        'pink',
        'cyan',
    ];

    /**
     * Deny rules CLAUDE.md's "Human-owned operations" section promises are
     * enforced. Deleting one must not quietly retract a promise the document
     * still makes.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_DENY_RULES = [
        'Bash(git commit *)',
        'Bash(git push *)',
        'Bash(gh pr create *)',
        'Bash(php artisan migrate *)',
        'Bash(php artisan db:wipe*)',
        'Bash(./start.sh fresh*)',
        'Bash(./start.sh reset*)',
        'Bash(docker compose down *)',
        'Read(.env)',
        'Edit(.env)',
    ];

    /**
     * Git subcommands the guard hook must classify as human-owned. Kept in
     * lockstep with the Bash(git ...) deny rules; the two drifting apart is how
     * a documented floor silently loses a verb.
     *
     * @var array<int, string>
     */
    private const array HUMAN_OWNED_GIT_SUBCOMMANDS = [
        'commit',
        'push',
        'merge',
        'rebase',
        'tag',
        'reset',
        'clean',
        'stash',
        'checkout',
        'switch',
        'restore',
        'cherry-pick',
        'revert',
        'am',
        'apply',
        'remote',
        'filter-branch',
        'update-ref',
        'fast-import',
    ];

    /**
     * Path rule forms Claude Code accepts, never consults, and warns about at
     * startup — the worst failure shape available, because the file reads as
     * protected while nothing checks it.
     *
     * @var array<int, string>
     */
    private const array INERT_PATH_RULE_PREFIXES = ['Write(', 'Glob(', 'NotebookEdit(', 'MultiEdit('];

    /**
     * Architectural idioms the skills and standards assert exist. If one stops
     * existing in the source, the gates are reviewing code against a contract
     * the repository no longer has.
     *
     * @var array<int, string>
     */
    private const array ARCHITECTURAL_IDIOMS = [
        'BaseRequest',
        'BaseData',
        'BaseModel',
        'MetaData',
        'ResponseUtil',
        'BadRequestException',
        'DatabaseTableConstant',
        'BigDecimalCast',
        'MathUtil',
        'app:format',
        'app:generate-resource',
        'auth:api',
    ];

    /**
     * The unfilled Consumers row. An empty table and a deliberately empty one
     * are indistinguishable to every later reader, so the placeholder must go.
     */
    private const string CONSUMERS_PLACEHOLDER_ROW = '_(none declared yet)_';

    /**
     * The stock Laravel skeleton package name. A clone still carrying it has
     * not been bootstrapped into a real project yet, so the checks that demand
     * project-specific content stay quiet.
     */
    private const string UNADOPTED_TEMPLATE_PACKAGE_NAME = 'laravel/laravel';

    /**
     * Sections the gate-handoff contract must keep, because all five gates
     * delegate their closing behaviour to it by reference.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_HANDOFF_CONTRACT_SECTIONS = [
        '## 0. Establish the mode first',
        '## 1. Close the gate',
        '## 2. Name the next step concretely',
        '## 3. Offer to continue',
        '## 4. What continuing never authorises',
        '## 5. Closing a gate in conductor mode',
    ];

    /**
     * Directories searched when proving a documented symbol still exists.
     *
     * @var array<int, string>
     */
    private const array SYMBOL_SEARCH_ROOTS = ['app', 'routes', 'database', 'tests', 'config', '.claude/hooks'];

    /**
     * Collected failures, keyed by the file they belong to.
     *
     * @var array<int, array{file: string, message: string}>
     */
    private array $violations = [];

    /**
     * Concatenated source text, built once and reused by every symbol probe.
     */
    private ?string $sourceHaystack = null;

    /**
     * Run every validation and report.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->validateSkills();
        $this->validateAgents();
        $this->validateCrossReferences();
        $this->validateArchitecturalIdioms();
        $this->validateSettingsAndHooks();
        $this->validateGuardHookBehaviour();
        $this->validateConsumersTable();
        $this->validateHandoffModeContract();

        return $this->report();
    }

    // ── Skills ──────────────────────────────────────────────────────────────

    /**
     * Validate every SKILL.md frontmatter, name agreement, and prefix rule.
     *
     * @return void
     */
    private function validateSkills(): void
    {
        $skillsDirectory = base_path('.claude/skills');

        if (!is_dir($skillsDirectory)) {
            $this->addViolation('.claude/skills', 'directory is missing');

            return;
        }

        foreach (new FilesystemIterator($skillsDirectory, FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var SplFileInfo $entry */
            if (!$entry->isDir()) {
                continue;
            }

            $directoryName = $entry->getFilename();
            $skillFile = $entry->getPathname() . '/SKILL.md';

            if (!is_file($skillFile)) {
                $this->addViolation(".claude/skills/{$directoryName}", 'has no SKILL.md');

                continue;
            }

            $this->validateSkill($skillFile, $directoryName);
        }
    }

    /**
     * Validate one skill file.
     *
     * @param string $skillFile
     * @param string $directoryName
     * @return void
     */
    private function validateSkill(string $skillFile, string $directoryName): void
    {
        $relativePath = $this->relativePath($skillFile);
        $frontmatter = $this->parseFrontmatter(file_get_contents($skillFile));

        if ($frontmatter === null) {
            $this->addViolation($relativePath, 'has no YAML frontmatter block');

            return;
        }

        foreach (array_keys($frontmatter) as $key) {
            if (!in_array($key, self::SUPPORTED_SKILL_KEYS, true)) {
                $this->addViolation($relativePath, "unsupported frontmatter key '{$key}' — Claude Code ignores it silently");
            }
        }

        $name = $frontmatter['name'] ?? '';
        $description = $frontmatter['description'] ?? '';

        if ($name === '') {
            $this->addViolation($relativePath, "missing required frontmatter key 'name'");
        } elseif ($name !== $directoryName) {
            $this->addViolation($relativePath, "frontmatter name '{$name}' does not match its directory '{$directoryName}'");
        }

        if ($description === '') {
            $this->addViolation($relativePath, "missing required frontmatter key 'description'");
        } elseif (mb_strlen($description) > self::SKILL_LISTING_CHARACTER_CAP) {
            $length = mb_strlen($description);
            $this->addViolation($relativePath, "description is {$length} characters; the skill listing truncates at " . self::SKILL_LISTING_CHARACTER_CAP);
        }

        $isUserInvocable = ($frontmatter['user-invocable'] ?? 'true') !== 'false';

        if ($isUserInvocable && $name !== '') {
            if (in_array($name, self::KNOWN_BUILT_IN_COMMAND_NAMES, true)) {
                $this->addViolation($relativePath, "name '{$name}' collides with a bundled Claude Code command — it will appear beside the built-in in the slash menu, not replace it");
            }

            $isPrefixed = str_starts_with($name, self::USER_INVOCABLE_SKILL_PREFIX);
            $isExempt = in_array($name, self::PREFIX_EXEMPT_SKILL_NAMES, true);

            if (!$isPrefixed && !$isExempt) {
                $this->addViolation($relativePath, "user-invocable skill '{$name}' must be prefixed '" . self::USER_INVOCABLE_SKILL_PREFIX . "' or set user-invocable: false");
            }
        }

        if (isset($frontmatter['effort']) && !in_array($frontmatter['effort'], self::VALID_EFFORT_LEVELS, true)) {
            $this->addViolation($relativePath, "effort '{$frontmatter['effort']}' is not one of " . implode('/', self::VALID_EFFORT_LEVELS));
        }
    }

    // ── Agents ──────────────────────────────────────────────────────────────

    /**
     * Validate every agent definition, including that it is genuinely read-only.
     *
     * @return void
     */
    private function validateAgents(): void
    {
        $agentsDirectory = base_path('.claude/agents');

        if (!is_dir($agentsDirectory)) {
            $this->addViolation('.claude/agents', 'directory is missing');

            return;
        }

        foreach (new FilesystemIterator($agentsDirectory, FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var SplFileInfo $entry */
            if ($entry->getExtension() !== 'md') {
                continue;
            }

            $this->validateAgent($entry->getPathname());
        }
    }

    /**
     * Validate one agent definition.
     *
     * @param string $agentFile
     * @return void
     */
    private function validateAgent(string $agentFile): void
    {
        $relativePath = $this->relativePath($agentFile);
        $expectedName = basename($agentFile, '.md');
        $frontmatter = $this->parseFrontmatter(file_get_contents($agentFile));

        if ($frontmatter === null) {
            $this->addViolation($relativePath, 'has no YAML frontmatter block');

            return;
        }

        foreach (array_keys($frontmatter) as $key) {
            if (!in_array($key, self::SUPPORTED_AGENT_KEYS, true)) {
                $this->addViolation($relativePath, "unsupported frontmatter key '{$key}' — Claude Code ignores it silently");
            }
        }

        $name = $frontmatter['name'] ?? '';

        if ($name === '') {
            $this->addViolation($relativePath, "missing required frontmatter key 'name'");
        } elseif ($name !== $expectedName) {
            $this->addViolation($relativePath, "frontmatter name '{$name}' does not match its filename '{$expectedName}'");
        }

        if (($frontmatter['description'] ?? '') === '') {
            $this->addViolation($relativePath, "missing required frontmatter key 'description'");
        }

        if (isset($frontmatter['effort']) && !in_array($frontmatter['effort'], self::VALID_EFFORT_LEVELS, true)) {
            $this->addViolation($relativePath, "effort '{$frontmatter['effort']}' is not one of " . implode('/', self::VALID_EFFORT_LEVELS));
        }

        if (isset($frontmatter['color']) && !in_array($frontmatter['color'], self::VALID_AGENT_COLORS, true)) {
            $this->addViolation($relativePath, "color '{$frontmatter['color']}' is not one of " . implode('/', self::VALID_AGENT_COLORS));
        }

        $this->validateAgentIsReadOnly($relativePath, $frontmatter);
    }

    /**
     * Prove an agent is read-only from its EFFECTIVE TOOL POOL, never from a
     * sentence in its prose. Searching for a phrase such as "Never edit files"
     * would pass every agent that has neither the sentence nor the restriction —
     * silence reading as compliance. A check that cannot catch an omission is
     * not a check.
     *
     * @param string $relativePath
     * @param array<string, string> $frontmatter
     * @return void
     */
    private function validateAgentIsReadOnly(string $relativePath, array $frontmatter): void
    {
        $grantedTools = $this->splitList($frontmatter['tools'] ?? '');
        $deniedTools = $this->splitList($frontmatter['disallowedTools'] ?? '');

        if ($grantedTools === []) {
            $this->addViolation($relativePath, "declares no 'tools', so it inherits the full tool pool including Edit and Write");

            return;
        }

        if (in_array('*', $grantedTools, true)) {
            $this->addViolation($relativePath, "grants every tool ('*'), so it is not read-only");

            return;
        }

        foreach (self::FILE_MUTATING_TOOL_NAMES as $mutatingTool) {
            if (in_array($mutatingTool, $grantedTools, true)) {
                $this->addViolation($relativePath, "grants the file-mutating tool '{$mutatingTool}'; review agents must be read-only");
            }
        }

        foreach (['Edit', 'Write', 'NotebookEdit'] as $requiredDenial) {
            if (!in_array($requiredDenial, $deniedTools, true)) {
                $this->addViolation($relativePath, "does not list '{$requiredDenial}' in disallowedTools; read-only status must be enforced, not merely stated");
            }
        }
    }

    // ── Cross-references and symbols ────────────────────────────────────────

    /**
     * Every backticked repository path referenced from .claude/ must resolve.
     *
     * @return void
     */
    private function validateCrossReferences(): void
    {
        foreach ($this->markdownFilesUnder(base_path('.claude')) as $markdownFile) {
            $relativePath = $this->relativePath($markdownFile);
            $contents = file_get_contents($markdownFile);

            preg_match_all('/`([A-Za-z0-9_.\/-]+\.(?:md|php|json|sh|yaml|yml))`/', $contents, $matches, PREG_OFFSET_CAPTURE);

            $alreadyReported = [];

            foreach ($matches[1] as [$referencedPath, $matchOffset]) {
                if (isset($alreadyReported[$referencedPath])) {
                    continue;
                }

                // A bare filename with no directory separator is prose, not a
                // path: "its `SKILL.md`", "a `security-dast.yml`", "`user.blade.php`"
                // in a naming example. Resolving those against the repository
                // root produces noise that trains the reader to ignore this
                // check, which is worse than not running it.
                if (!str_contains($referencedPath, '/')) {
                    continue;
                }

                if ($this->pathReferenceResolves($referencedPath, $markdownFile)) {
                    continue;
                }

                // A file cited precisely BECAUSE it does not exist is a
                // counter-example, not a broken reference. context-mapper tells
                // agents that Laravel 11+ has no app/Http/Kernel.php so they
                // stop reporting its absence as a finding.
                if ($this->isCounterExampleReference($contents, $matchOffset)) {
                    continue;
                }

                $alreadyReported[$referencedPath] = true;
                $this->addViolation($relativePath, "references '{$referencedPath}', which does not exist");
            }
        }
    }

    /**
     * Resolve a documented path against the repository root and the referring
     * file's own directory.
     *
     * @param string $referencedPath
     * @param string $referringFile
     * @return bool
     */
    private function pathReferenceResolves(string $referencedPath, string $referringFile): bool
    {
        if (str_contains($referencedPath, '*') || str_contains($referencedPath, '<')) {
            return true;
        }

        $candidates = [
            base_path($referencedPath),
            dirname($referringFile) . '/' . $referencedPath,
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decide whether a path reference sits on a line that cites it precisely
     * because it does NOT exist.
     *
     * @param string $contents
     * @param int $matchOffset
     * @return bool
     */
    private function isCounterExampleReference(string $contents, int $matchOffset): bool
    {
        $lineStart = strrpos(substr($contents, 0, $matchOffset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($contents, "\n", $matchOffset);
        $line = substr($contents, $lineStart, ($lineEnd === false ? strlen($contents) : $lineEnd) - $lineStart);
        $line = mb_strtolower($line);

        $negations = ['there is no', 'does not exist', 'no longer', 'never', 'instead of', 'not included', 'absence'];

        foreach ($negations as $negation) {
            if (str_contains($line, $negation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every architectural idiom the framework asserts must still exist in the
     * source it claims to describe.
     *
     * @return void
     */
    private function validateArchitecturalIdioms(): void
    {
        foreach (self::ARCHITECTURAL_IDIOMS as $idiom) {
            if (str_contains($this->sourceHaystack(), $idiom)) {
                continue;
            }

            $this->addViolation('.claude', "documents the idiom '{$idiom}', which no longer appears anywhere in " . implode('/', self::SYMBOL_SEARCH_ROOTS));
        }
    }

    /**
     * Concatenate the searchable source once; every idiom probe reuses it.
     *
     * @return string
     */
    private function sourceHaystack(): string
    {
        if ($this->sourceHaystack !== null) {
            return $this->sourceHaystack;
        }

        $collected = '';

        foreach (self::SYMBOL_SEARCH_ROOTS as $root) {
            $absoluteRoot = base_path($root);

            if (!is_dir($absoluteRoot)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                $collected .= file_get_contents($file->getPathname()) . "\n";
            }
        }

        $this->sourceHaystack = $collected;

        return $this->sourceHaystack;
    }

    // ── Settings and hooks ──────────────────────────────────────────────────

    /**
     * Validate the committed permission floor and the hook wiring.
     *
     * @return void
     */
    private function validateSettingsAndHooks(): void
    {
        $settingsPath = base_path('.claude/settings.json');

        if (!is_file($settingsPath)) {
            $this->addViolation('.claude/settings.json', 'is missing');

            return;
        }

        $settings = json_decode(file_get_contents($settingsPath), true);

        if (!is_array($settings)) {
            $this->addViolation('.claude/settings.json', 'is not valid JSON');

            return;
        }

        $permissions = $settings['permissions'] ?? [];
        $denyRules = $permissions['deny'] ?? [];
        $askRules = $permissions['ask'] ?? [];
        $allowRules = $permissions['allow'] ?? [];

        $this->validateRequiredDenyFloor($denyRules);
        $this->validateShellRuleParity($denyRules, 'deny');
        $this->validateShellRuleParity($askRules, 'ask');
        $this->validateIssueTrackerRulePortability($denyRules, $askRules);
        $this->validateGitDenyMatchesGuardHook($denyRules);

        foreach (['deny' => $denyRules, 'ask' => $askRules, 'allow' => $allowRules] as $tier => $rules) {
            $this->validateFilePermissionRuleShapes($rules, $tier);
        }

        $this->validateHookScripts($settings);
    }

    /**
     * Every rule CLAUDE.md promises is enforced must actually be present.
     *
     * @param array<int, string> $denyRules
     * @return void
     */
    private function validateRequiredDenyFloor(array $denyRules): void
    {
        foreach (self::REQUIRED_DENY_RULES as $requiredRule) {
            if (in_array($requiredRule, $denyRules, true)) {
                continue;
            }

            $this->addViolation('.claude/settings.json', "missing required deny rule '{$requiredRule}' that CLAUDE.md promises is enforced");
        }
    }

    /**
     * Every Bash rule must be mirrored as a PowerShell rule. The PowerShell tool
     * is enabled by default on Windows without Git Bash, and Bash(...) rules do
     * not govern it — an unmirrored floor simply disappears on those machines,
     * with no warning anywhere.
     *
     * @param array<int, string> $rules
     * @param string $tier
     * @return void
     */
    private function validateShellRuleParity(array $rules, string $tier): void
    {
        foreach ($rules as $rule) {
            if (!str_starts_with($rule, 'Bash(')) {
                continue;
            }

            $mirroredRule = 'PowerShell(' . substr($rule, strlen('Bash('));

            if (in_array($mirroredRule, $rules, true)) {
                continue;
            }

            $this->addViolation('.claude/settings.json', "{$tier} rule '{$rule}' has no PowerShell mirror; the floor vanishes on Windows without Git Bash");
        }
    }

    /**
     * Issue-tracker rules must glob the server segment. A tool is named
     * mcp__<server>__<tool>, and <server> is whatever this project called it in
     * .mcp.json — a hardcoded name matches nothing in a project that renamed its
     * server, so the tracker floor is absent while the docs still promise it.
     *
     * @param array<int, string> $denyRules
     * @param array<int, string> $askRules
     * @return void
     */
    private function validateIssueTrackerRulePortability(array $denyRules, array $askRules): void
    {
        foreach (array_merge($denyRules, $askRules) as $rule) {
            if (!str_starts_with($rule, 'mcp__')) {
                continue;
            }

            if (str_starts_with($rule, 'mcp__*__')) {
                continue;
            }

            $this->addViolation('.claude/settings.json', "MCP rule '{$rule}' hardcodes a server name; use a glob server segment (mcp__*__…) so the floor survives a renamed server");
        }
    }

    /**
     * The guard hook's git verb table and the deny rules must stay in lockstep.
     *
     * @param array<int, string> $denyRules
     * @return void
     */
    private function validateGitDenyMatchesGuardHook(array $denyRules): void
    {
        $hookPath = base_path('.claude/hooks/guard-dangerous-commands.sh');

        if (!is_file($hookPath)) {
            return;
        }

        $hookContents = file_get_contents($hookPath);

        if (preg_match('/^GIT_HUMAN_OWNED_SUBCOMMANDS=\'([^\']*)\'/m', $hookContents, $matches) !== 1) {
            $this->addViolation('.claude/hooks/guard-dangerous-commands.sh', 'GIT_HUMAN_OWNED_SUBCOMMANDS table not found; the deny rules can no longer be checked against it');

            return;
        }

        $hookSubcommands = preg_split('/\s+/', trim($matches[1]), -1, PREG_SPLIT_NO_EMPTY);

        foreach (self::HUMAN_OWNED_GIT_SUBCOMMANDS as $subcommand) {
            if (!in_array($subcommand, $hookSubcommands, true)) {
                $this->addViolation('.claude/hooks/guard-dangerous-commands.sh', "git verb '{$subcommand}' is denied in settings.json but missing from the hook's table");
            }

            if (!in_array("Bash(git {$subcommand} *)", $denyRules, true)) {
                $this->addViolation('.claude/settings.json', "git verb '{$subcommand}' is classified by the hook but has no deny rule");
            }
        }
    }

    /**
     * Reject path rule forms Claude Code accepts but never consults.
     *
     * @param array<int, string> $rules
     * @param string $tier
     * @return void
     */
    private function validateFilePermissionRuleShapes(array $rules, string $tier): void
    {
        foreach ($rules as $rule) {
            foreach (self::INERT_PATH_RULE_PREFIXES as $inertPrefix) {
                if (!str_starts_with($rule, $inertPrefix)) {
                    continue;
                }

                $this->addViolation('.claude/settings.json', "{$tier} rule '{$rule}' uses a form Claude Code accepts but never consults; use Edit(...) or Read(...)");
            }
        }
    }

    /**
     * Hook scripts must exist, parse, be executable, and actually be wired up.
     * An unwired script still reads as an active guard.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    private function validateHookScripts(array $settings): void
    {
        $wiredCommands = json_encode($settings['hooks'] ?? []);
        $hooksDirectory = base_path('.claude/hooks');

        if (!is_dir($hooksDirectory)) {
            $this->addViolation('.claude/hooks', 'directory is missing');

            return;
        }

        foreach (new FilesystemIterator($hooksDirectory, FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var SplFileInfo $entry */
            if ($entry->getExtension() !== 'sh') {
                continue;
            }

            $scriptName = $entry->getFilename();
            $relativePath = ".claude/hooks/{$scriptName}";

            if (!is_executable($entry->getPathname())) {
                $this->addViolation($relativePath, 'is not executable (chmod +x)');
            }

            exec('bash -n ' . escapeshellarg($entry->getPathname()) . ' 2>&1', $syntaxOutput, $syntaxStatus);

            if ($syntaxStatus !== 0) {
                $this->addViolation($relativePath, 'is not valid bash: ' . implode(' ', $syntaxOutput));
            }

            if (!str_contains((string) $wiredCommands, $scriptName)) {
                $this->addViolation($relativePath, 'exists but is not referenced by settings.json; an unwired script still reads as an active guard');
            }
        }
    }

    // ── Guard hook behaviour ────────────────────────────────────────────────

    /**
     * The decision table. `bash -n` and `chmod +x` prove a hook runs, not that
     * it is right, and a hook that crashes exits non-zero — which Claude Code
     * treats as a non-blocking error, so a broken guard fails OPEN.
     *
     * The container and wrapper forms carry most of the weight: in this
     * repository almost every command runs through `docker exec … bash -c "…"`,
     * and the ordinary commands below must NEVER prompt — a guard that nags gets
     * switched off within a day, and then it protects nothing.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function guardHookFixtures(): array
    {
        return [
            ['git status --short', 'allow'],
            ['git log --oneline -5', 'allow'],
            ['git commit -m "x"', 'deny'],
            ['git -C /elsewhere commit -m x', 'deny'],
            ['git status && git commit -m sneaky', 'deny'],
            ['git branch -D feature', 'ask'],
            ['docker exec laravel-api php artisan migrate', 'deny'],
            ['docker exec -it laravel-api bash -c "php artisan migrate:fresh --seed"', 'deny'],
            ['docker exec -it laravel-api bash -lc "php artisan db:wipe"', 'deny'],
            ['docker exec -it laravel-api bash -c "php artisan app:format"', 'allow'],
            ['docker exec -it laravel-api bash -c "php artisan app:format --check"', 'allow'],
            ['docker exec laravel-api php artisan test --parallel', 'allow'],
            ['docker exec laravel-api bash -c "php artisan test --filter=UserFeatureTest"', 'allow'],
            ['docker exec laravel-api cat .env', 'deny'],
            ['./test.sh', 'allow'],
            ['./test.sh AppVersionFeatureTest tests/Feature/AppVersionFeatureTest.php', 'allow'],
            ['./test-pipeline.sh', 'allow'],
            ['./start.sh', 'allow'],
            ['./stop.sh', 'allow'],
            ['./start.sh fresh', 'deny'],
            ['./start.sh reset', 'deny'],
            ['php artisan migrate', 'deny'],
            ['php artisan migrate:rollback', 'deny'],
            ['php artisan route:list', 'allow'],
            ['php artisan passport:keys --force', 'ask'],
            ['docker exec laravel-api php artisan tinker --execute "User::count();"', 'ask'],
            ['composer install', 'allow'],
            ['composer update', 'ask'],
            ['sudo composer update', 'ask'],
            ['docker compose down -v', 'deny'],
            ['docker volume rm laravel-db', 'deny'],
            ['docker system prune -af', 'deny'],
            ['cat .env', 'deny'],
            ['cp .env /tmp/leak', 'deny'],
            ['cat storage/oauth-private.key', 'deny'],
            ['cat .env.example', 'allow'],
            ['cat .env.testing', 'allow'],
            ['echo .env is the config file', 'allow'],
            ['gh pr create --fill', 'deny'],
            ['gh release create v1', 'deny'],
            ['gh api /repos/x/y', 'ask'],
            ['rm -rf /', 'deny'],
            ['rm -rf ~', 'deny'],
            ['rm -rf storage/framework/cache', 'allow'],
            ['mysql -u root -p', 'ask'],
            ['ls -la app/Services', 'allow'],
        ];
    }

    /**
     * Run the guard hook against the decision table.
     *
     * @return void
     */
    private function validateGuardHookBehaviour(): void
    {
        $hookPath = base_path('.claude/hooks/guard-dangerous-commands.sh');

        if (!is_file($hookPath)) {
            $this->addViolation('.claude/hooks/guard-dangerous-commands.sh', 'is missing, so its behaviour cannot be verified');

            return;
        }

        foreach (['bash', 'jq'] as $requiredBinary) {
            exec('command -v ' . escapeshellarg($requiredBinary) . ' 2>/dev/null', $lookupOutput, $lookupStatus);

            if ($lookupStatus === 0) {
                continue;
            }

            $this->addViolation(
                '.claude/hooks/guard-dangerous-commands.sh',
                "BLOCKED: '{$requiredBinary}' is unavailable, so the guard's decisions cannot be verified. Run this command on the host or a CI runner rather than inside the application container."
            );

            return;
        }

        foreach ($this->guardHookFixtures() as [$command, $expectedDecision]) {
            $actualDecision = $this->guardHookDecisionFor($hookPath, $command);

            if ($actualDecision === $expectedDecision) {
                continue;
            }

            $this->addViolation(
                '.claude/hooks/guard-dangerous-commands.sh',
                "decided '{$actualDecision}' for `{$command}`, expected '{$expectedDecision}'"
            );
        }
    }

    /**
     * Feed one command to the guard hook and read back its decision.
     *
     * @param string $hookPath
     * @param string $command
     * @return string
     */
    private function guardHookDecisionFor(string $hookPath, string $command): string
    {
        $payload = json_encode(['tool_input' => ['command' => $command]]);
        $shellCommand = 'printf %s ' . escapeshellarg((string) $payload) . ' | ' . escapeshellarg($hookPath) . ' 2>/dev/null';
        $rawOutput = trim((string) shell_exec($shellCommand));

        if ($rawOutput === '') {
            return 'allow';
        }

        $decoded = json_decode($rawOutput, true);

        if (!is_array($decoded)) {
            return 'unparseable-output';
        }

        return $decoded['hookSpecificOutput']['permissionDecision'] ?? 'allow';
    }

    // ── Documentation contracts ─────────────────────────────────────────────

    /**
     * The Consumers table must be filled in or deliberately declared empty.
     *
     * @return void
     */
    private function validateConsumersTable(): void
    {
        $claudeMarkdown = base_path('CLAUDE.md');

        if (!is_file($claudeMarkdown)) {
            $this->addViolation('CLAUDE.md', 'is missing');

            return;
        }

        if (!str_contains(file_get_contents($claudeMarkdown), self::CONSUMERS_PLACEHOLDER_ROW)) {
            return;
        }

        // A fresh clone of the starter must ship green, or the first thing a
        // new project meets is a failing check about a table it has had no
        // chance to fill in. Adopting the template — renaming it away from the
        // stock Laravel skeleton name — turns this on.
        if ($this->isUnadoptedStarterClone()) {
            return;
        }

        $this->addViolation(
            'CLAUDE.md',
            'still carries the Consumers placeholder row. List every client that programs against this API, or replace the row with "_(none — internal only)_" and say why'
        );
    }

    /**
     * Detect a clone that has not been adopted yet — still carrying the stock
     * Laravel skeleton package name.
     *
     * @return bool
     */
    private function isUnadoptedStarterClone(): bool
    {
        $composerPath = base_path('composer.json');

        if (!is_file($composerPath)) {
            return false;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        return is_array($composer) && ($composer['name'] ?? '') === self::UNADOPTED_TEMPLATE_PACKAGE_NAME;
    }

    /**
     * The handoff contract must keep its sections, and every gate must be
     * mode-aware — a gate that does not distinguish standalone from conductor
     * mode will interrogate a /work-item run at every stage boundary.
     *
     * @return void
     */
    private function validateHandoffModeContract(): void
    {
        $contractPath = base_path('.claude/standards/gate-handoff.md');

        if (!is_file($contractPath)) {
            $this->addViolation('.claude/standards/gate-handoff.md', 'is missing, but every gate delegates its close to it');

            return;
        }

        $contractContents = file_get_contents($contractPath);

        foreach (self::REQUIRED_HANDOFF_CONTRACT_SECTIONS as $requiredSection) {
            if (str_contains($contractContents, $requiredSection)) {
                continue;
            }

            $this->addViolation('.claude/standards/gate-handoff.md', "missing required section '{$requiredSection}'");
        }

        foreach (glob(base_path('.claude/skills/gate-*/SKILL.md')) as $gateSkillPath) {
            $relativePath = $this->relativePath($gateSkillPath);
            $gateContents = file_get_contents($gateSkillPath);

            if (!str_contains($gateContents, 'gate-handoff.md')) {
                $this->addViolation($relativePath, 'does not reference .claude/standards/gate-handoff.md, so its close can drift from the others');
            }

            if (str_contains($gateContents, 'Conductor') || str_contains($gateContents, '/work-item')) {
                continue;
            }

            $this->addViolation($relativePath, 'has no conductor-mode handling; it would interrogate a /work-item run at every stage boundary');
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Parse a top-level YAML frontmatter block into key/value strings.
     *
     * Deliberately line-based rather than a YAML dependency: the frontmatter
     * Claude Code reads is flat, and the failure this guards against is a
     * mistyped KEY, which a permissive parser would happily accept anyway.
     *
     * @param string $contents
     * @return array<string, string>|null
     */
    private function parseFrontmatter(string $contents): ?array
    {
        $contents = ltrim($contents, "\xEF\xBB\xBF");

        if (!str_starts_with($contents, "---\n")) {
            return null;
        }

        $endPosition = strpos($contents, "\n---", 3);

        if ($endPosition === false) {
            return null;
        }

        $block = substr($contents, 4, $endPosition - 3);
        $parsed = [];

        foreach (explode("\n", $block) as $line) {
            if ($line === '' || str_starts_with($line, ' ') || str_starts_with($line, '#')) {
                continue;
            }

            $separatorPosition = strpos($line, ':');

            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPosition));
            $value = trim(substr($line, $separatorPosition + 1));
            $parsed[$key] = trim($value, '"\'');
        }

        return $parsed;
    }

    /**
     * Split a comma-separated frontmatter list into trimmed entries.
     *
     * @param string $value
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Every markdown file under a directory, recursively.
     *
     * @param string $directory
     * @return array<int, string>
     */
    private function markdownFilesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'md') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Express an absolute path relative to the repository root.
     *
     * @param string $path
     * @return string
     */
    private function relativePath(string $path): string
    {
        $root = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    /**
     * Record a failure.
     *
     * @param string $file
     * @param string $message
     * @return void
     */
    private function addViolation(string $file, string $message): void
    {
        $this->violations[] = ['file' => $file, 'message' => $message];
    }

    /**
     * Print the outcome and return the process exit code.
     *
     * @return int
     */
    private function report(): int
    {
        if ($this->violations === []) {
            $this->info('.claude/ configuration is valid.');

            return self::SUCCESS;
        }

        $this->error(count($this->violations) . ' problem(s) found in the Claude configuration:');
        $this->newLine();

        foreach ($this->violations as $violation) {
            $this->line("  {$violation['file']}");
            $this->line("    {$violation['message']}");
        }

        $this->newLine();
        $this->line('See .claude/README.md for what each guardrail promises.');

        return self::FAILURE;
    }

}
