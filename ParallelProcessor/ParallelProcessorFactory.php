<?php

namespace Naldz\ParallelProcessor;

use Symfony\Component\Console\Output\OutputInterface;
use Naldz\ParallelProcessor\ParallelProcessor;

class ParallelProcessorFactory
{
    public function create($maxNumberOfParalleledProcesses = 5, OutputInterface $output, $propagateChildExceptions=true, $suppressProcessorOutput=false)
    {
        return new ParallelProcessor($maxNumberOfParalleledProcesses, $output, $propagateChildExceptions, $suppressProcessorOutput);
    }
}