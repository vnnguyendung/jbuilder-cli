<?php
/**
 * @package    JBuilderCli
 * @copyright  Copyright (c) 2003-2016 Frédéric Vandebeuque / Newebtime
 * @license    Mozilla Public License, version 2.0
 */

namespace Newebtime\JbuilderCli\Command\Project;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Newebtime\JbuilderCli\Command\Base as BaseCommand;
use Newebtime\JbuilderCli\Exception\OutputException;

class Init extends BaseCommand
{
	protected $ignoreDemo;

	/**
	 * @@inheritdoc
	 */
	protected function configure()
	{
		$this
			->setName('project:init')
			->setDescription('Init a new development project in the directory')
			->addArgument(
				'path',
				InputArgument::OPTIONAL,
				'The path of the directory'
			)->addOption(
				'name',
				null,
				InputOption::VALUE_OPTIONAL,
				'The project name'
			);
	}

	/**
	 * @inheritdoc
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		try
		{
			$this->initIO($input, $output);

			$this->io->title('Init project');

			$path = $input->getArgument('path');

			if (!$path)
			{
				$path = $this->basePath;
			}

			$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (!is_dir($path))
			{
				throw new OutputException([
					'This directory does not exists, please check',
					$path
				], 'error');
			}

			if (file_exists($path . '.jbuilder'))
			{
				throw new OutputException([
					'This directory has already been init',
					$path
				], 'warning');
			}

			$this->basePath = $path;

			$this->config = (object) [
				'paths' => (object) [
					'src'        => 'src/',
					'components' => 'components/',
					'libraries'  => 'libraries/',
					'demo'       => 'demo/'
				],
				'infos' => (object) [
					'author'      => 'me',
					'email'       => 'me@domain.tld',
					'url'         => 'http://www.domain.tld',
					'copyright'   => 'Copyright (c) 2016 Me',
					'license'     => 'GNU General Public License version 2 or later',
					'description' => ''
				]
			];

			if ($name = $input->getOption('name'))
			{
				$this->config->name = $name;
			}
		}
		catch (OutputException $e)
		{
			$type = $e->getType();

			$this->io->$type($e->getMessages());

			exit;
		}
		catch (\Exception $e)
		{
			$this->io->error($e->getMessage());

			exit;
		}
	}

	/**
	 * @@inheritdoc
	 */
	protected function interact(InputInterface $input, OutputInterface $output)
	{
		$this->io->section('Project configuration');

		$name = $this->io->ask('What is the package name?', 'myproject');

		$this->config->name = $name;

		if (!$this->io->confirm('Use the default structure?'))
		{
			$src = $this->io->ask('Define the sources directory', 'src');
			$src = rtrim($src, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			$components = $this->io->ask('Define the components directory (relative to the sources directory)', 'components');
			$components = rtrim($components, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			$libraries = $this->io->ask('Define the libraries directory (relative to the sources directory)', 'libraries');
			$libraries = rtrim($libraries, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			$demo = $this->io->ask('Define the Joomla website directory', 'demo');
			$demo = rtrim($demo, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			$this->io->comment([
				'Sources    ' . $src,
				'Components ' . $src . $components,
				'Libraries  ' . $src . $libraries,
				'Demo       ' . $demo
			]);

			$this->config->paths = (object) [
				'src'        => $src,
				'components' => $components,
				'libraries'  => $libraries,
				'demo'       => $demo
			];
		}

		if (!$this->io->confirm('Use the default informations (author, copyright, etc)?'))
		{
			$author      = $this->io->ask('Define the author?', 'me');
			$email       = $this->io->ask('Define the email?', 'me@domain.tld');
			$url         = $this->io->ask('Define the website URL', 'http://www.domain.tld');
			$copyright   = $this->io->ask('Define the copyright', 'Copyright (c) 2016 Me');
			$license     = $this->io->ask('Define the license', 'GNU General Public License version 2 or later');
			$description = $this->io->ask('Define the description', '');

			$this->config->infos = [
				'author'      => $author,
				'email'       => $email,
				'url'         => $url,
				'copyright'   => $copyright,
				'license'     => $license,
				'description' => $description
			];
		}

		$this->ignoreDemo = $this->io->confirm('Add the demo in .gitignore?');
	}

	/**
	 * @@inheritdoc
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		try
		{
			$this->io->section('Project creation');

			if (empty($this->config->name))
			{
				throw new OutputException([
					'Action canceled, enter a name for this project'
				], 'warning');
			}

			$path = $this->basePath;

			$mkPaths = [
				$path . $this->config->paths->src,
				$path . $this->config->paths->src . $this->config->paths->components,
				$path . $this->config->paths->src . $this->config->paths->libraries,
				$path . $this->config->paths->demo
			];

			foreach ($mkPaths as $mkPath)
			{
				if (is_dir($mkPath))
				{
					$this->io->note([
						'Skip directory creation, this directory already exists',
						$mkPath
					]);
				}
				elseif (!@mkdir($mkPath))
				{
					throw new OutputException([
						'Something wrong happened during the creation po the directory',
						$mkPath
					], 'error');
				}
			}

			if (!@touch($path . 'README.md'))
			{
				$this->io->warning([
					'The README.md could not be created',
					$path . 'README.md'
				]);
			}

			if ($this->ignoreDemo
				&& !@file_put_contents($path . '.gitignore', $this->config->paths->demo))
			{
				$this->io->warning([
					'The .gitignore could not be created',
					$path . 'README.md'
				]);
			}

			if (!@file_put_contents($path . '.jbuilder', json_encode($this->config, JSON_PRETTY_PRINT)))
			{
				throw new OutputException([
					'Action canceled, the builder file cannot be created, please check.',
					$path . '.jbuilder'
				], 'error');
			}

			$this->createPackageXml();
		}
		catch (OutputException $e)
		{
			$type = $e->getType();

			$this->io->$type($e->getMessages());

			exit;
		}
		catch (\Exception $e)
		{
			$this->io->error($e->getMessage());

			exit;
		}
	}

	public function createPackageXml()
	{
		$xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><extension></extension>');

		$xml->addAttribute('type', 'package');
		$xml->addAttribute('version', '3.3.6');
		$xml->addAttribute('method', 'upgrade');

		$xml->addChild('name', $this->config->name);
		$xml->addChild('author', $this->config->infos->author);
		$xml->addChild('creationDate', date('Y-m-d'));
		$xml->addChild('packagename', $this->config->name);
		$xml->addChild('version', '0.0.1');
		$xml->addChild('url', $this->config->infos->url);
		$xml->addChild('description', $this->config->infos->description);

		$fof = $xml
			->addChild('files')
				->addChild('folder', $this->config->paths->libraries . 'fof');

		$fof->addAttribute('type', 'library');
		$fof->addAttribute('id', 'fof30');

		$this->saveXML($xml->asXML(), $this->basePath . $this->config->paths->src . 'pkg_' . $this->config->name . '.xml');
	}
}
