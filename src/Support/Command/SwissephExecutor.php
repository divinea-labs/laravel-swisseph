<?php

namespace DivineaLabs\Swisseph\Support\Command;

use DivineaLabs\Swisseph\Data\SwissephCommand;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SwissephExecutor
{
    /**
     * Execute the given Swisseph command and return the output lines.
     *
     * @return array<int, string>
     */
    public function run(SwissephCommand $command): array
    {
        $process = new Process($command->toProcessArray());
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
            static fn (string $line) => $line !== ''
        ));
    }
}
