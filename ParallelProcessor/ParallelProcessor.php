<?php

namespace Naldz\ParallelProcessor;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ParallelProcessor
{
    private $maxNumberOfParalleledProcess;
    private $processStack = array();
    private $processStartTimes = array();
    private $output;
    private $propagateChildExceptions = false;
    private $suppressProcessorOutput = false;

    public function __construct($maxNumberOfParalleledProcess=5, OutputInterface $output, $propagateChildExceptions=false, $suppressProcessorOutput=false)
    {
        $this->maxNumberOfParalleledProcess = $maxNumberOfParalleledProcess;
        $this->output = $output;
        $this->propagateChildExceptions = $propagateChildExceptions;
        $this->suppressProcessorOutput = $suppressProcessorOutput;
    }

    public function execute($cmdStr, $forcedWait=false)
    {
        if (count($this->processStack) < $this->maxNumberOfParalleledProcess) {
            $procId = uniqid();
            $this->processStack[$procId] = $this->spawnSubproc($procId, $cmdStr);
            //if stack is full, do NOT return control to the caller and wait for one of the processes to finish
            $doneProcs = $this->wait($forcedWait);
        }
        else {
            throw new \Exception('Process stack is full!');
        }

        return $doneProcs;
    }

    //In time this function should be converted to private. 
    //clients should start using the 'forcedWait' parameter when calling execute method
    public function wait($forcedWait = true)
    {
        $doneProcs = array();

        $waitEchoed = false;
        $shouldWait = true;
        $numberOfProcessesToWaitFor = 0;

        while ($shouldWait) {
            foreach ($this->processStack as $procId => $proc) {
                if (!$proc->isRunning()) {
                    $doneProcs[] = $proc;
                    $procEndTime = new \DateTime();
                    $elapseTime = $procEndTime->diff($this->processStartTimes[$procId]);

                    $elapseTimeStr = sprintf('%s:%s:%s', $elapseTime->h, $elapseTime->i, $elapseTime->s);

                    unset($this->processStack[$procId]);

                    if ($proc->isSuccessful()) {
                        if (!$this->suppressProcessorOutput) {
                            $this->output->writeln(sprintf('[proc: DONE] %s:(%s) >> Elapsed Time: %s', $procId, $procEndTime->format('H:i:s'), $elapseTimeStr));
                        }
                    }
                    else {
                        $this->output->writeln(sprintf('<error>%s</error>', $proc->getErrorOutput()));
                        //on error and propagateChildExceptions is true, transfer control to the caller immediately
                        if ($this->propagateChildExceptions) {
                            throw new ProcessFailedException($proc);
                        }
                    }
                }
            }

            if ($forcedWait) {
                $shouldWait = count($this->processStack) > 0;
            }
            else {
                $shouldWait = count($this->processStack) >= $this->maxNumberOfParalleledProcess;
            }

            if ($shouldWait) {
                $currentNumberOfProcessesToWaitFor = count($this->processStack);
                $processesWaitedForList = $currentNumberOfProcessesToWaitFor > 5 ? 'Too many' : implode(",", array_keys($this->processStack));

                if (!$waitEchoed) {
                    $waitEchoed = true;

                    if ($forcedWait) {
                        if (!$this->suppressProcessorOutput) {
                            $waitMsg = 'Waiting for all processes to finish.';
                            $this->output->writeln(sprintf('[proc: WAITING] >> %s Number of running processes: %d [%s]', $waitMsg, $currentNumberOfProcessesToWaitFor, $processesWaitedForList));
                        }
                    }
                    else {
                        if (!$this->suppressProcessorOutput) {
                            $waitMsg = 'Waiting for a process to finish.';
                            $this->output->writeln(sprintf('[proc: WAITING] >> %s Number of running processes: %d', $waitMsg, $currentNumberOfProcessesToWaitFor));
                        }
                    }
                }
                else if ($forcedWait) {
                    if ($currentNumberOfProcessesToWaitFor > 0 && $currentNumberOfProcessesToWaitFor < $numberOfProcessesToWaitFor) {
                        if (!$this->suppressProcessorOutput) {
                            $waitMsg = 'Waiting for all processes to finish.';
                            $this->output->writeln(sprintf('[proc: WAITING] >> %s Number of running processes: %d [%s]', $waitMsg, $currentNumberOfProcessesToWaitFor, $processesWaitedForList));
                        }
                        $numberOfProcessesToWaitFor = $currentNumberOfProcessesToWaitFor;
                    }
                }
            }
        }

        return $doneProcs;
    }

    public function stop()
    {
        foreach ($this->processStack as $procId => $proc) {
            $proc->stop();
        }
        return $this->wait(true);
    }

    private function spawnSubproc($procId, $cmdStr)
    {
        $procStartTime = new \DateTime();
        $this->processStartTimes[$procId] = $procStartTime;
        if (!$this->suppressProcessorOutput) {
            $this->output->writeln(sprintf('[proc: RUNNING] %s:(%s) >> %s', $procId, $procStartTime->format('H:i:s'), $cmdStr));
        }
        $process = new Process($cmdStr, null, null, null, null);
        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });
        return $process;
    }
}
