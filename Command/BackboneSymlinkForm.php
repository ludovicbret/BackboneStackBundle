<?php

namespace Lb\Bundle\BackboneStackBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mopa\Bridge\Composer\Adapter\ComposerAdapter;
use Mopa\Bridge\Composer\Util\ComposerPathFinder;

/**
 * Command to check and create bootstrap symlink into LbBackboneBundle
 */
class BackboneSymlinkFormsCommand extends ContainerAwareCommand
{
    public static $BackboneSymlinkFormsCommand = "lb/backbone-stack";
    public static $powmediaBackboneFormsName = "powmedia/backbone-forms";

    protected function configure()
    {
        $this
            ->setName('lb:backbone:symlink:form')
            ->setDescription("Check and if possible install symlink to form")
            ->addArgument('pathToBackboneForms', InputArgument::OPTIONAL, 'Where is powmedia/backbone-forms located?')
            ->addArgument('pathToLbBackboneBundleBundle', InputArgument::OPTIONAL, 'Where is LbBackboneBundle located?')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force rewrite of existing symlink if possible!')
            ->addOption('manual', 'm', InputOption::VALUE_NONE, 'If set please specify pathToBackboneForms, and pathToLbBackboneBundleBundle')
            ->setHelp(<<<EOT
The <info>lb:backbone:install</info> command helps you checking and symlinking the powmedia/backbone-forms library.

By default, the command uses composer to retrieve the paths of LbBackboneStackBundle and powmedia/backbone-forms in your vendors.

If you want to control the paths yourself specify the paths manually:

php app/console lb:backbone:install <comment>--manual</comment> <pathToBackboneForms> <pathToLbBackboneBundleBundle>

Defaults if installed by composer would be :

pathToBackboneForms:    ../../../../../../vendor/powmedia/backbone-forms
pathToLbBackboneBundleBundle: vendor/lb/backbone-stack/Lb/BackboneStackBundle/Resources/backbone-forms

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        if ($input->getOption('manual')) {
            list($symlinkTarget, $symlinkName) = $this->getBootstrapPathsfromUser();
        } elseif (false !== $composer = ComposerAdapter::getComposer($input, $output)) {
            $cmanager = new ComposerPathFinder($composer);
            $options = array(
                    'targetSuffix' => DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "bootstrap",
                    'sourcePrefix' => '..' . DIRECTORY_SEPARATOR
                );
            list($symlinkTarget, $symlinkName) = $cmanager->getSymlinkFromComposer(
                self::$lbBackboneStackBundleNamelb
                self::$powmediaBackboneFormsName,
                $options
            );
        } else {
            $this->output->writeln("<error>Could not find composer and manual option not secified!</error>");

            return;
        }

        $this->output->write("Checking Symlink");
        if (false === self::checkSymlink($symlinkTarget, $symlinkName, true)) {
            $this->output->writeln(" ... <comment>not existing</comment>");
            $this->output->writeln("Creating Symlink: " . $symlinkName);
            $this->output->write("for Target: " . $symlinkTarget);
            self::createSymlink($symlinkTarget, $symlinkName);
        }
        $this->output->writeln(" ... <info>OK</info>");
    }

    protected function getBootstrapPathsfromUser()
    {
            $symlinkTarget = $this->input->getArgument('pathToBackboneForms');
            $symlinkName = $this->input->getArgument('pathToLbBackboneBundleBundle');
            if (empty($symlinkName)) {
                throw new \Exception("pathToLbBackboneBundleBundle not specified");
            } elseif (!is_dir(dirname($symlinkName))) {
                throw new \Exception("pathToLbBackboneBundleBundle: " . dirname($symlinkName) . " does not exist");
            }
            if (empty($symlinkTarget)) {
                throw new \Exception("pathToBackboneForms not specified");
            } else {
                if (substr($symlinkTarget, 0, 1) == "/") {
                    $this->output->writeln("<comment>Try avoiding absolute paths, for portability!</comment>");
                    if (!is_dir($symlinkTarget)) {
                        throw new \Exception("Target path " . $symlinkTarget . "is not a directory!");
                    }
                } else {
                    $resolve =
                        $symlinkName . DIRECTORY_SEPARATOR .
                        ".." . DIRECTORY_SEPARATOR .
                        $symlinkTarget;
                    $symlinkTarget = self::get_absolute_path($resolve);
                }
                if (!is_dir($symlinkTarget)) {
                    throw new \Exception("pathToBackboneForms would resolve to: " . $symlinkTarget . "\n and this is not reachable from \npathToLbBackboneBundleBundle: " . dirname($symlinkName));
                }
            }
            $dialog = $this->getHelperSet()->get('dialog');
            $text = <<<EOF
Creating the symlink: $symlinkName
  Pointing to: $symlinkTarget
EOF
;
            $this->output->writeln(array(
                '',
                $this->getHelperSet()->get('formatter')->formatBlock($text, $style = 'bg=blue;fg=white', true),
                '',
            ));
            if (!$dialog->askConfirmation($this->output, '<question>Should this link be created? (y/n)</question>', false)) {
                exit;
            }

            return array($symlinkTarget, $symlinkName);
    }

    protected static function get_absolute_path($path)
    {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $absolutes);
    }
    /**
     * Checks symlink
     *
     * @param string  $symlinkTarget The Target
     * @param string  $symlinkName   The Name
     * @param boolean $forceSymlink  Force to be a link or throw exception
     *
     * @throws \Exception
     * @return boolean
     */
    public static function checkSymlink($symlinkTarget, $symlinkName, $forceSymlink = false)
    {
        if (!$forceSymlink and file_exists($symlinkName) && !is_link($symlinkName)) {
            $type = filetype($symlinkName);
            if ($type != "link") {
                throw new \Exception($symlinkName . " exists and is no link!");
            }
        } elseif (is_link($symlinkName)) {
            $linkTarget = readlink($symlinkName);
            if ($linkTarget != $symlinkTarget) {
                if (!$forceSymlink) {
                    throw new \Exception("Symlink " . $symlinkName .
                        " Points  to " . $linkTarget .
                        " instead of " . $symlinkTarget);
                }
                unlink($symlinkName);

                return false;
            } else {

                return true;
            }
        }

        return false;
    }

    /**
     * Create the symlink
     *
     * @param string $symlinkTarget The Target
     * @param string $symlinkName   The Name
     *
     * @throws \Exception
     */
    public static function createSymlink($symlinkTarget, $symlinkName)
    {
        if (false === @symlink($symlinkTarget, $symlinkName)) {
            throw new \Exception("An error occured while creating symlink" . $symlinkName);
        }
        if (false === $target = readlink($symlinkName)) {
            throw new \Exception("Symlink $symlinkName points to target $target");
        }
    }
}