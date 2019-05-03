<?php
/**
 * Part of the Joomla Framework Database Package
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Database\Command;

use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Filesystem\Folder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console command for importing the database
 *
 * @since  __DEPLOY_VERSION__
 */
class ImportCommand extends AbstractCommand
{
	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $defaultName = 'database:import';

	/**
	 * Database connector
	 *
	 * @var    DatabaseInterface
	 * @since  __DEPLOY_VERSION__
	 */
	private $db;

	/**
	 * Instantiate the command.
	 *
	 * @param   DatabaseInterface  $db  Database connector
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;

		parent::__construct();
	}

	/**
	 * Internal function to execute the command.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  integer  The command exit code
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$symfonyStyle = new SymfonyStyle($input, $output);

		$symfonyStyle->title('Importing Database');

		$total_time = microtime(true);

		$folderPath = $input->getOption('folder');
		$tableName = $input->getOption('table');
		$all = $input->getOption('all');

		$tables = Folder::files($folderPath, '\.xml$');

		if (($tableName == null) && ($all == null))
		{
			$symfonyStyle->warning("Either the --table or --all option must be specified");
			return 1;
		}

		if ($tableName)
		{
			$tables = array($tableName . '.xml');
		}

		foreach ($tables as $table)
		{
			$task_i_time = microtime(true);
			$percorso    = $folderPath . '/' . $table;

			// Check file
			if (!file_exists($percorso))
			{
				$symfonyStyle->error('Not Found ' . $table . '....');
				return 1;
			}

			$table_name = str_replace('.xml', '', $table);
			$symfonyStyle->text('Importing ' . $table_name . ' from ' . $table);

			try
			{
				$imp = $this->db->getImporter()->from(file_get_contents($percorso))->withStructure()->asXml();
			}
			catch (\Exception $e)
			{
				$symfonyStyle->error('Error on getImporter' . $table . ' ' . $e);
				return 1;
			}

			$symfonyStyle->text('Reading data from ' . $table);

			try
			{
				$symfonyStyle->text('Drop ' . $table_name);
				$this->db->dropTable($table_name, true);
			}
			catch (ExecutionFailureException $e)
			{
				$symfonyStyle->error(' Error in DROP TABLE ' . $table_name . ' ' . $e);
				return 1;
			}

			try
			{
				$imp->mergeStructure();
			}
			catch (\Exception $e)
			{
				$symfonyStyle->error('Error on mergeStructure' . $table . ' ' . $e);
				return 1;
			}

			$symfonyStyle->text('Checked structure ' . $table);

			try
			{
				$imp->importData();
			}
			catch (\Exception $e)
			{
				$symfonyStyle->error('Error on importData' . $table . ' ' . $e);
				return 1;
			}
			$symfonyStyle->text('Data loaded ' . $table . ' in ' . round(microtime(true) - $task_i_time, 3));
			$symfonyStyle->newLine();
		}

		$symfonyStyle->text('Total time: ' . round(microtime(true) - $total_time, 3));
	}

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function configure()
	{
		$this->setDescription('Import the database');
		$this->addOption('folder', null, InputOption::VALUE_OPTIONAL, 'Import from folder path', '.');
		$this->addOption('table', null, InputOption::VALUE_REQUIRED, 'Import table name');
		$this->addOption('all', null, InputOption::VALUE_NONE, 'Import all files');
	}
}
