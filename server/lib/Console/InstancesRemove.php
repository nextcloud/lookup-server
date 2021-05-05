<?php


namespace LookupServer\Console;

use LookupServer\InstanceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class InstancesRemove extends Command {


	/** @var InstanceManager */
	private $instanceManager;


	/**
	 * InstancesRemove constructor.
	 *
	 * @param InstanceManager $instanceManager
	 */
	public function __construct(InstanceManager $instanceManager) {
		parent::__construct('instances:remove');

		$this->instanceManager = $instanceManager;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setDescription('Remove an instance from the list');
		$this->addOption('users', '', InputOption::VALUE_NONE, 'remove also users from the instance')
			 ->addArgument('instance', InputArgument::REQUIRED, 'instance to remove');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$instance = $input->getArgument('instance');
		$this->instanceManager->remove($instance, $input->getOption('users'));

		return 0;
	}

}

