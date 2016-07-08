<?php
/**
 * @package    JBuilderCli
 * @copyright  Copyright (c) 2003-2016 Frédéric Vandebeuque / Newebtime
 * @license    Mozilla Public License, version 2.0
 */

namespace Newebtime\JbuilderCli\Command\Component;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Entity extends AbstractComponent
{
	protected $entity;

	/**
	 * @@inheritdoc
	 */
	protected function configure()
	{
		parent::configure();

		$this
			->setName('component:entity')
			->setDescription('Generate a new component entity (e.g. todos)')
			->addOption(
				'name',
				null,
				InputOption::VALUE_REQUIRED,
				'The new entity name'
			)
			->addOption(
				'frontend',
				null,
				InputOption::VALUE_NONE,
				'Generate only the frontend'
			)
			->addOption(
				'backend',
				null,
				InputOption::VALUE_NONE,
				'Generate only the backend'
			);
	}

	/**
	 * @inheritdoc
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		//TODO: Check
		include_once JPATH_PLATFORM . '/fof30/include.php';

		$this->io->title('Generate new entity');

		$name  = $input->getOption('name');

		if (!$name
			&& $input->isInteractive())
		{
			$name = $this->io->ask('No entity name given, please enter the entity name (e.g. todos)');
		}

		if (preg_replace('/[^A-Z_]/i', '', $name) != $name
			|| empty($name))
		{
			$this->io->warning('Action canceled, the name is not correct, you can use only A-Z and _ (e.g. todos)');

			exit;
		}

		//TODO: check plurial

		$this->entity = $name;
	}

	/**
	 * @@inheritdoc
	 */
	protected function interact(InputInterface $input, OutputInterface $output)
	{
		$this->io->section('Database table');

		//TODO: Detect if the entity table already exists in the database.
		$hasTable = 0;

		if ($hasTable)
		{
			//TODO: Ask to use or delete
		}

		if (!$hasTable)
		{
			$this->io->note('No table found for this entity in the database');

			$this->io->table(['type', 'description'], [
				['default', 'Fields: id, title, created_on, created_by, modified_on, modified_by'],
				['builder', 'Create the table using the CLI builder'],
				['none',    'No table, Model and Layouts will not be generated']
			]);

			$type = $this->io->choice('What table generator do you want to use for this entity?', ['default', 'builder', 'none'], 'default');

			if ('default' == $type)
			{
				$tableName = '#__' . $this->component->name . '_' . $this->entity;

				$inflector = new \FOF30\Inflector\Inflector();

				$viewSingular = $inflector->singularize($this->entity);
				$idFieldName = $this->component->name . '_' . $viewSingular . '_id';

				$xml = new \SimpleXMLElement('<xml></xml>');

				$database = $xml->addChild('database');

				$table = $database->addChild('table_structure');
				$table->addAttribute('name', $tableName);

				$field = $table->addChild('field');
				$field->addAttribute('Field', $idFieldName);
				$field->addAttribute('Type', 'INT(11) UNSIGNED');
				$field->addAttribute('Null', 'NO');
				$field->addAttribute('Extra', 'AUTO_INCREMENT');

				$field = $table->addChild('field');
				$field->addAttribute('Field', 'title');
				$field->addAttribute('Type', 'VARCHAR(255)');
				$field->addAttribute('Null', 'NO');

				$field = $table->addChild('field');
				$field->addAttribute('Field', 'created_on');
				$field->addAttribute('Type', 'DATE');
				$field->addAttribute('Null', 'NO');

				$field = $table->addChild('field');
				$field->addAttribute('Field', 'created_by');
				$field->addAttribute('Type', 'INT(11) UNSIGNED');
				$field->addAttribute('Null', 'NO');

				$field = $table->addChild('field');
				$field->addAttribute('Field', 'modified_on');
				$field->addAttribute('Type', 'DATE');
				$field->addAttribute('Null', 'NO');

				$field = $table->addChild('field');
				$field->addAttribute('Field', 'modified_by');
				$field->addAttribute('Type', 'INT(11) UNSIGNED');
				$field->addAttribute('Null', 'NO');

				$key = $table->addChild('key');
				$key->addAttribute('Key_name', 'PRIMARY');
				$key->addAttribute('Column_name', $idFieldName);

				// Force to MySQLi, PDO has no xmlToCreate()
				$importer = new \JDatabaseImporterMysqli();
				$importer->setDbo(\JFactory::getDbo());

				$importer->from($xml)->mergeStructure();
			}
			elseif ('builder' == $type)
			{
				//TODO: Builder : Load the table builder
			}
			else
			{
				//TODO: None    : No database, disabled Model and Layouts, add caution or note.
			}
		}
	}

	/**
	 * @@inheritdoc
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sections = ['admin', 'site'];

		if ($input->getOption('frontend'))
		{
			$sections = ['site'];
		}
		elseif ($input->getOption('backend'))
		{
			$sections = ['admin'];
		}

		$this->generateController($sections);

		$this->generateLayouts($sections);

		$this->generateModel($sections);

		$this->generateView($sections);

		//TODO: Allow to create Non database aware controller and model (not possible using FOF scaffolding)
	}

	protected function generateController($sections)
	{
		$view = $this->entity;

		// Let's force the use of the Magic Factory
		$config = ['factoryClass' => 'FOF30\\Factory\\MagicFactory'];

		$container = \FOF30\Container\Container::getInstance($this->component->comName, $config);
		$container->factory->setSaveScaffolding(true);

		// plural / singular
		$view = $container->inflector->singularize($view);

		foreach ($sections as $section)
		{
			$classname = $container->getNamespacePrefix($section) . 'Controller\\' . ucfirst($view);

			$scaffolding = new \FOF30\Factory\Scaffolding\Controller\Builder($container);
			$scaffolding->setSection($section);

			if(!$scaffolding->make($classname, $view))
			{
				$this->io->error('An error occurred while creating the Controller class');

				exit;
			}
		}
	}

	protected function generateLayouts($sections)
	{
		$view = ucfirst($this->entity);

		// Let's force the use of the Magic Factory
		$config = ['factoryClass' => 'FOF30\\Factory\\MagicFactory'];

		// Let's force the use of the Magic Factory
		$container = \FOF30\Container\Container::getInstance($this->component->comName, $config);
		$container->factory->setSaveScaffolding(true);

		$types = [
			'admin' => ['default', 'form'],
			'site'  => ['default', 'item']
		];

		$originalFrontendPath = $container->frontEndPath;
		$originalBackendPath  = $container->backEndPath;

		foreach ($sections as $section)
		{
			foreach ($types[$section] as $type)
			{
				// plural / singular
				if ($type != 'default')
				{
					$view = $container->inflector->singularize($view);
				}
				else
				{
					$view = $container->inflector->pluralize($view);
				}

				$container->frontEndPath = ($section == 'admin') ? $container->backEndPath : $container->frontEndPath;

				$scaffolding = new \FOF30\Factory\Scaffolding\Layout\Builder($container);

				if(!$scaffolding->make('form.' . $type, $view))
				{
					$this->io->error('An error occurred while creating the Controller class');

					exit;
				}

				// And switch them back!
				$container->frontEndPath = $originalFrontendPath;
				$container->backEndPath  = $originalBackendPath;
			}
		}
	}

	protected function generateModel($sections)
	{
		$view = $this->entity;

		// Let's force the use of the Magic Factory
		$config = ['factoryClass' => 'FOF30\\Factory\\MagicFactory'];

		// Let's force the use of the Magic Factory
		$container = \FOF30\Container\Container::getInstance($this->component->comName, $config);
		$container->factory->setSaveScaffolding(true);

		// plural / singular
		$view = $container->inflector->pluralize($view);

		foreach ($sections as $section)
		{
			$classname = $container->getNamespacePrefix($section) . 'Model\\' . ucfirst($view);

			$scaffolding = new \FOF30\Factory\Scaffolding\Model\Builder($container);
			$scaffolding->setSection($section);

			if(!$scaffolding->make($classname, $view))
			{
				$this->io->error('An error occurred while creating the Model class');

				exit;
			}
		}
	}

	protected function generateView($sections)
	{
		$view = $this->entity;

		// Let's force the use of the Magic Factory
		$config = ['factoryClass' => 'FOF30\\Factory\\MagicFactory'];

		// Let's force the use of the Magic Factory
		$container = \FOF30\Container\Container::getInstance($this->component->comName, $config);
		$container->factory->setSaveScaffolding(true);

		// plural / singular
		$view = $container->inflector->pluralize($view);

		foreach ($sections as $section)
		{
			$classname = $container->getNamespacePrefix($section) . 'View\\' . ucfirst($view) . '\\Html';

			$scaffolding = new \FOF30\Factory\Scaffolding\View\Builder($container);
			$scaffolding->setSection($section);

			if(!$scaffolding->make($classname, $view, 'html'))
			{
				$this->io->error('An error occurred while creating the View class');

				exit;
			}
		}
	}
}