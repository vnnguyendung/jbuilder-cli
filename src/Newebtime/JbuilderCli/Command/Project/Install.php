<?php
/**
 * @package    JBuilderCli
 * @copyright  Copyright (c) 2003-2016 Frédéric Vandebeuque / Newebtime
 * @license    Mozilla Public License, version 2.0
 */

namespace Newebtime\JbuilderCli\Command\Project;

use Joomlatools\Console\Command\Extension\Install as ExtensionInstall;
use Joomlatools\Console\Command\Site\Download as SiteDownload;
use Joomlatools\Console\Command\Site\Install as SiteInstall;
use Joomlatools\Console\Command\Versions as Versions;
use Joomlatools\Console\Joomla\Bootstrapper;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Newebtime\JbuilderCli\Command\Base as BaseCommand;
use Newebtime\JbuilderCli\Exception\OutputException;

class Install extends BaseCommand
{
	/**
	 * @@inheritdoc
	 */
	protected function configure()
	{
		$this
			->setName('project:install')
			->setDescription('Download and install the dependency for the project (Joomla, FOF, package)');
	}
	/**
	 * @inheritdoc
	 */
	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->initIO($input, $output);

		$this->io->title('Install project');
	}

	/**
	 * @@inheritdoc
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		try
		{
			$this->installJoomla($input, $output);
			$this->installFof($input, $output);
			$this->installPackage($input, $output);
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

	public function installJoomla(InputInterface $input, OutputInterface $output)
	{
		$this->io->section('Joomla');

		if (file_exists($this->basePath . $this->config->paths->demo . '/includes/defines.php'))
		{
			if ('use' == $this->io->choice('Joomla already exists, do you want to use it?', ['use', 'delete'], 'use'))
			{
				$this->io->note('Skipped Joomla installation');

				return;
			}

			$this->io->note('Deleting current Joomla instance');

			$demoPath = $this->basePath . $this->config->paths->demo;

			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($demoPath, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			/** @var \SplFileInfo $fileInfo */
			foreach ($files as $fileInfo)
			{
				if ($fileInfo->isLink())
				{
					unlink($fileInfo->getPathname());
				}
				elseif ($fileInfo->isDir())
				{
					rmdir($fileInfo->getRealPath());
				}
				else
				{
					unlink($fileInfo->getRealPath());
				}
			}

			$this->io->success('Deleting completed');
		}

		$this->io->note('Start downloading Joomla');

		$arguments = [
			'site:download',
			'site'      => $this->config->paths->demo,
			'--refresh' => true,
			'--www'     => $this->basePath,
		];

		$command = new SiteDownload();
		$command->run(new ArrayInput($arguments), $this->io);

		$this->io->note('Start Installing Joomla');

		$arguments = [
			'site:install',
			'site'          => $this->config->paths->demo,
			'--www'         => $this->basePath,
			'--sample-data' => 'default',
			'--interactive' => true,
		];

		$command = new SiteInstall();
		$command->setApplication($this->getApplication());
		$command->run(new ArrayInput($arguments), $this->io);

		$this->io->success('Demo website installation completed');
	}

	public function installFof(InputInterface $input, OutputInterface $output)
	{
		$this->io->section('FOF');

		$this->io->note('Start downloading FOF');

		$app = Bootstrapper::getApplication($this->basePath . '/' . $this->config->paths->demo);

		$versions = new Versions();

		$versions->setRepository('https://github.com/akeeba/fof.git');
		$versions->refresh();

		$version = str_replace('.', '-', $versions->getLatestRelease());
		$package = str_replace('{VERSION}', $version, 'https://www.akeebabackup.com/download/fof3/{VERSION}/lib_fof30-{VERSION}-zip.zip');

		$this->io->note('Version: ' . $version);

		if (!$name = \JInstallerHelper::downloadPackage($package))
		{
			$this->io->warning('Action cancel, impossible to download FOF package');

			return;
		}

		$this->io->note('Start installing FOF');

		// Output buffer is used as a guard against Joomla including ._ files when searching for adapters
		// See: http://kadin.sdf-us.org/weblog/technology/software/deleting-dot-underscore-files.html
		ob_start();

		$tmpPath = $app->get('tmp_path');
		$pkgPath = $tmpPath . $name;

		if (!$result = \JInstallerHelper::unpack($pkgPath))
		{
			$this->io->warning('Action cancel, impossible to unpack FOF package');

			return;
		}

		$resultPath = $result['dir'];
		$destPath   = $this->basePath . '/' . $this->config->paths->src . $this->config->paths->libraries . 'fof30';

		if (is_dir($destPath))
		{
			if ('delete' == $this->io->choice('FOF directoty detected in your libraries sources, use it?', ['use', 'delete'], 'delete'))
			{
				\JFolder::delete($destPath);
			}
			else
			{
				$skipCopy = true;
			}
		}

		if (!isset($skipCopy)
			&& !\JFolder::copy($resultPath, $destPath))
		{
			$this->io->warning('Action cancel, impossible to copy FOF to the library directory');

			return;
		}

		if (!\JInstallerHelper::cleanupInstall($pkgPath, $resultPath))
		{
			$this->io->note('Joomla temp directory could not be cleaned');
		}

		$linkPath   = $destPath . '/fof';
		$linkTarget = $this->basePath . '/' . $this->config->paths->demo . 'libraries/fof30';

		//TODO: Check
		symlink($linkPath, $linkTarget);

		$linkXMLPath   = $destPath . '/fof/lib_fof30.xml';
		$linkXMLTarget = $this->basePath . '/' . $this->config->paths->demo . 'administrator/manifests/libraries/lib_fof30.xml';

		//TODO: Check
		symlink($linkXMLPath, $linkXMLTarget);

		$arguments = new ArrayInput(array(
			'extension:install',
			'site'      => $this->config->paths->demo,
			'extension' => 'lib_fof30',
			'--www'     => $this->basePath,
		));

		$command = new ExtensionInstall();
		$command->run($arguments, $output);

		ob_end_clean();

		$this->io->success('FOF installation completed');
	}

	public function installPackage(InputInterface $input, OutputInterface $output)
	{
		$this->io->section('Package');
		//TODO: ln the pkg_ then install all the other libraries and components if any
		$this->io->success('Package installation completed');
	}
}
