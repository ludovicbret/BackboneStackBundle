<?php
/**
 * Script for composer, to symlink bootstrap lib into Bundle
 *
 * Maybe nice to convert this to a command and then reuse command in here.
 */
namespace Lb\BackboneStackBundle\Composer;

use Composer\Script\Event;
use Mopa\Bridge\Composer\Util\ComposerPathFinder;
use Lb\Bundle\BackboneStackBundle\Command\BootstrapSymlinkFormCommand;

class ScriptHandler
{

    public static function postInstallSymlinkForm(Event $event)
    {
        $IO = $event->getIO();
        $composer = $event->getComposer();
        $cmanager = new ComposerPathFinder($composer);
        $options = array(
            'targetSuffix' => DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "bootstrap",
            'sourcePrefix' => '..' . DIRECTORY_SEPARATOR
        );
        list($symlinkTarget, $symlinkName) = $cmanager->getSymlinkFromComposer(
            BackboneSymlinkFormsCommand::$lbBackboneStackBundleName,
            BackboneSymlinkFormsCommand::$powmediaBackboneFormsName,
            $options
        );

        $IO->write("Checking Symlink", FALSE);
        if (false === BackboneSymlinkFormsCommand::checkSymlink($symlinkTarget, $symlinkName, true)) {
            $IO->write("Creating Symlink: " . $symlinkName, FALSE);
            BackboneSymlinkFormsCommand::createSymlink($symlinkTarget, $symlinkName);
        }
        $IO->write(" ... <info>OK</info>");
    }
}