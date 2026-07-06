<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Applies the project's hand-maintained PHP code-style standard (see CLAUDE.md
 * "Code style") — the rules the Laravel Pint preset cannot express or actively
 * fights: a blank line after each class/enum opening brace and before its
 * closing brace, method/constructor opening braces on their own line, and no
 * space after the unary "!" operator. Pint is intentionally NOT used here.
 */
class FormatCommand extends Command
{

    protected $signature = 'app:format
        {--check : Report unformatted files and exit non-zero without writing changes}';

    protected $description = 'Apply the project PHP code-style standard to app/, database/, and tests/.';

    /**
     * Directories scanned for PHP files.
     *
     * @var array<int, string>
     */
    private const array PATHS = ['app', 'database', 'tests'];

    public function handle(): int
    {
        $check = (bool) $this->option('check');
        $changed = [];

        foreach ($this->phpFiles() as $path) {
            $original = file_get_contents($path);
            $formatted = $this->format($original);

            if ($formatted === $original) {
                continue;
            }

            $changed[] = $this->relativePath($path);

            if (!$check) {
                file_put_contents($path, $formatted);
            }
        }

        return $check ? $this->reportCheck($changed) : $this->reportWrite($changed);
    }

    /**
     * Yield every PHP file under the scanned directories.
     *
     * @return iterable<int, string>
     */
    private function phpFiles(): iterable
    {
        foreach (self::PATHS as $dir) {
            $base = base_path($dir);

            if (!is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    /**
     * Apply the code-style transforms to a file's contents.
     */
    private function format(string $text): string
    {
        $lines = explode("\n", $text);

        // A) Braces on their own line: expand ') {}' and multi-line signature closes.
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $indent = substr($line, 0, strlen($line) - strlen(ltrim($line)));

            if ($trimmed === ') {}') {
                $out[] = $indent . ')';
                $out[] = $indent . '{';
                $out[] = $indent . '}';
            } elseif (preg_match('/^\)(\s*:\s*[\w\\\\|?]+)?\s*\{$/', $trimmed)) {
                $out[] = rtrim(substr(rtrim($line), 0, -1));
                $out[] = $indent . '{';
            } else {
                $out[] = $line;
            }
        }

        $lines = $out;

        // Locate the class/enum/trait/interface (or anonymous class) declaration.
        $declIndex = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|enum|interface|trait)\b/', $line)
                || preg_match('/\bnew\s+class\b/', $line)) {
                $declIndex = $i;
                break;
            }
        }

        if ($declIndex !== null) {
            // B) Blank line after the class-body opening brace.
            $openIndex = null;

            for ($j = $declIndex; $j < count($lines); $j++) {
                if (str_ends_with(rtrim($lines[$j]), '{')) {
                    $openIndex = $j;
                    break;
                }
            }

            if ($openIndex !== null && isset($lines[$openIndex + 1]) && trim($lines[$openIndex + 1]) !== '') {
                array_splice($lines, $openIndex + 1, 0, '');
            }

            // C) Blank line before the class-body closing brace.
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if ($lines[$i] === '}' || $lines[$i] === '};') {
                    if ($i > 0 && trim($lines[$i - 1]) !== '') {
                        array_splice($lines, $i, 0, '');
                    }
                    break;
                }
            }
        }

        $text = implode("\n", $lines);

        // D) No space after the unary '!' operator (string-safe — never matches
        //    inside a quoted string, where '!' is preceded by a word char or quote).
        return preg_replace('/(?<![\w"\'])! +(?=[\w$(!])/', '!', $text);
    }

    /**
     * @param  array<int, string>  $changed
     */
    private function reportCheck(array $changed): int
    {
        if (empty($changed)) {
            $this->info('Code style OK — all files conform to the project standard.');

            return self::SUCCESS;
        }

        $this->error(count($changed) . ' file(s) do not conform to the project code style:');

        foreach ($changed as $path) {
            $this->line('  ' . $path);
        }

        $this->newLine();
        $this->line('Run "php artisan app:format" to fix them. Do NOT run Pint.');

        return self::FAILURE;
    }

    /**
     * @param  array<int, string>  $changed
     */
    private function reportWrite(array $changed): int
    {
        if (empty($changed)) {
            $this->info('Nothing to format — all files already conform.');

            return self::SUCCESS;
        }

        foreach ($changed as $path) {
            $this->line('formatted ' . $path);
        }

        $this->info('Formatted ' . count($changed) . ' file(s).');

        return self::SUCCESS;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }

}
