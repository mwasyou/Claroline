<?php

namespace Claroline\PluginBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends AbstractPluginCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setName('claroline:plugin:remove')
             ->setDescription('Removes a specified claroline plugin.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vendor = $input->getArgument('vendor_name');
        $bundle = $input->getArgument('bundle_name');
        $FQCN = "{$vendor}\\{$bundle}\\{$vendor}{$bundle}";

        $output->writeln('Launching plugin manager...');

        $manager = $this->getContainer()->get('claroline.plugin.manager');
        $manager->remove($FQCN);

        $output->writeln('Done');
    }
}