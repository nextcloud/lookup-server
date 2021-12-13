<?php


namespace LookupServer\Console;

use LookupServer\InstanceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class InstancesList
 *
 * @package LookupServer\Console
 */
class InstancesList extends Command {


	/** @var InstanceManager */
	private $instanceManager;


	/**
	 * InstancesList constructor.
	 *
	 * @param InstanceManager $instanceManager
	 */
	public function __construct(InstanceManager $instanceManager) {
		parent::__construct('instances:list');

		$this->instanceManager = $instanceManager;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setDescription('Displays list of known instance');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$instances = $this->instanceManager->getAll();
		$output->writeln(json_encode($instances, JSON_PRETTY_PRINT));

		return 0;
	}

}

