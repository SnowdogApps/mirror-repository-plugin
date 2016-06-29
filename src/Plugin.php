<?php

namespace Snowdog\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $requires = $composer->getPackage()->getRequires();

        $repository = $composer->getRepositoryManager()->createRepository('composer', ['url' => 'http://satis.local']);
        
        foreach($requires as $require) {
            $io->write(
                $require->getTarget() . ' ' .
                $require->getConstraint()->getPrettyString()
            );
            $foundPackage = $repository->findPackage($require->getTarget(), $require->getConstraint());
            if($foundPackage) {
                $io->write('FOUND !!');
            }
        }
        
        $requires = $composer->getPackage()->getDevRequires();
        foreach($requires as $require) {
            $io->write(
                'dev :: ' . 
                $require->getTarget() . ' ' .
                $require->getConstraint()->getPrettyString()
            );
            $foundPackage = $repository->findPackage($require->getTarget(), $require->getConstraint());
            if($foundPackage) {
                $io->write('FOUND !!');
            }
        }

        

        $package = $composer->getRepositoryManager()->findPackage('slim/slim', '*');
        
        
        echo $io->write('Hello this is plugin ;)');
    }
}