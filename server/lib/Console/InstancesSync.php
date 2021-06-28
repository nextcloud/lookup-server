<?php


namespace LookupServer\Console;

use LookupServer\InstanceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class InstancesSync
 *
 * @package LookupServer\Console
 */
class InstancesSync extends Command {


	/** @var InstanceManager */
	private $instanceManager;


	/**
	 * InstancesSync constructor.
	 *
	 * @param InstanceManager $instanceManager
	 */
	public function __construct(InstanceManager $instanceManager) {
		parent::__construct('instances:sync');

		$this->instanceManager = $instanceManager;
	}


	/**
	 *
	 */
	protected function configure() {
		$this->setDescription('Generate list of instances, based on the users list');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->instanceManager->syncInstances();
		return 0;
	}

}
