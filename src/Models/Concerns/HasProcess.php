<?php

namespace Spatie\ServerMonitor\Models\Concerns;

use Symfony\Component\Process\Process;
use Spatie\ServerMonitor\Manipulators\Manipulator;

trait HasProcess
{
    public function getProcess(): Process
    {
        return blink()->once("process.{$this->id}", function () {
            $process = new Process($this->getProcessCommand());

            $process->setTimeout($this->getDefinition()->timeoutInSeconds());

            $manipulator = app(Manipulator::class);

            return $manipulator->manipulateProcess($process, $this);
        });
    }

    public function getProcessCommand(): string
    {
        $delimiter = 'EOF-LARAVEL-SERVER-MONITOR';

        $definition = $this->getDefinition();

        $portArgument = empty($this->host->port) ? '' : "-p {$this->host->port}";

        $sshCommandPrefix = config('server-monitor.ssh_command_prefix');
        $sshCommandSuffix = config('server-monitor.ssh_command_suffix');

        $result = '';
        if($this->host->name != 'localhost' || $this->host->port != '127.0.0.1') {
            $result .= 'ssh';
            if ($sshCommandPrefix) {
                $result .= ' '.$sshCommandPrefix;
            }
            $result .= ' '.$this->getTarget();
            if ($portArgument) {
                $result .= ' '.$portArgument;
            }
            if ($sshCommandSuffix) {
                $result .= ' '.$sshCommandSuffix;
            }
            $result .= " 'bash -se <<$delimiter".PHP_EOL
                .'set -e'.PHP_EOL
                .$definition->command().PHP_EOL
                .$delimiter."'";
        } else {
            $result .= "bash -se <<$delimiter".PHP_EOL
                .'set -e'.PHP_EOL
                .$definition->command().PHP_EOL
                .$delimiter;
        }


        return $result;
    }

    protected function getTarget(): string
    {
        $target = empty($this->host->ip)
            ? $this->host->name
            : $this->host->ip;

        if ($this->host->ssh_user) {
            $target = $this->host->ssh_user.'@'.$target;
        }

        return $target;
    }
}
