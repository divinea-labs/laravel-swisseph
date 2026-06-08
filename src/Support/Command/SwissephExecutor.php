<?php

namespace DivineaLabs\Swisseph\Support\Command;

use DivineaLabs\Swisseph\Data\SwissephCommand;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SwissephExecutor
{
    /**
     * @param  string[]  $skipPrefixes  Lines starting with any of these (after trim) are dropped.
     * @return array<int, string>
     */
    public function run(SwissephCommand $command, array $skipPrefixes = []): array
    {
        // swetest echoes the invoked command line (starting with the executable path)
        // as the first stdout line in special-event modes. Auto-skip it so callers only
        // need to declare mode-specific headers (e.g. 'geo. long'). The executable is an
        // ABSOLUTE path in production, so matching on it is robust (a literal './' is not).
        return $this->runRaw(
            $command->toProcessArray(),
            array_merge([$command->executable], $skipPrefixes),
        );
    }

    /**
     * @param  string[]  $processArray
     * @param  string[]  $skipPrefixes
     * @return array<int, string>
     */
    public function runRaw(array $processArray, array $skipPrefixes = []): array
    {
        $process = new Process($processArray);
        $process->setTimeout((float) config('swisseph.timeout', 10));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $lines = preg_split("/\R/u", $output) ?: [];
        $lines = array_map('trim', $lines);

        return array_values(array_filter(
            $lines,
            static function (string $line) use ($skipPrefixes): bool {
                if ($line === '') {
                    return false;
                }
                foreach ($skipPrefixes as $prefix) {
                    if (str_starts_with($line, $prefix)) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }
}
