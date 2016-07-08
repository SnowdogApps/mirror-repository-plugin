<?php

namespace Snowdog\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\ComposerRepository;
use Composer\Util\StreamContextFactory;

class Plugin implements PluginInterface
{
    const ENDPOINT_URI = '/notify/require';

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $requires = $composer->getPackage()->getRequires();

        $managedRepository = $composer->getConfig()->get('mirror-repository');
        if (!$managedRepository) {
            $io->writeError('<error>Mirror repository is not configured. Snowdog mirror repository plugin disabled. See documentation for details</error>');
            return;
        }

        $repository = null;

        foreach ($composer->getRepositoryManager()->getRepositories() as $repositoryRow) {
            if ($repositoryRow instanceof ComposerRepository) {
                /** @var ComposerRepository $repository */
                $config = $repositoryRow->getRepoConfig();
                if (isset($config['url']) && $config['url'] == $managedRepository) {
                    $repository = $repositoryRow;
                    break;
                }
            }
        }

        if (!$repository) {
            $io->writeError('<warning>Mirror repository ' . $managedRepository . ' not found in configuration. Snowdog mirror repository plugin disabled. See documentation for details</warning>');
            return;
        } else {
            $io->write('<info>Found repository plugin is active for ' . $managedRepository . '</info>');
        }

        $missingPackages = [];

        foreach ($requires as $require) {
            $foundPackage = $repository->findPackage($require->getTarget(), $require->getConstraint());
            if (!$foundPackage) {
                $missingPackages[] = $require->getTarget();
            }
        }

        $requires = $composer->getPackage()->getDevRequires();
        foreach ($requires as $require) {
            $foundPackage = $repository->findPackage($require->getTarget(), $require->getConstraint());
            if (!$foundPackage) {
                $missingPackages[] = $require->getTarget();
            }
        }

        $repositoryName = parse_url($managedRepository, PHP_URL_HOST);
        if ($io->hasAuthentication($repositoryName)) {
            $auth = $io->getAuthentication($repositoryName);
            $authStr = base64_encode($auth['username'] . ':' . $auth['password']);
            $authHeader = 'Authorization: Basic ' . $authStr;
        }

        if (!empty($missingPackages)) {
            $postData = array('missingPackages' => $missingPackages);

            $opts = array(
                'http' =>
                    array(
                        'method' => 'POST',
                        'header' => array('Content-Type: application/json'),
                        'content' => json_encode($postData),
                        'timeout' => 6,
                    ),
            );
            if (isset($authHeader)) {
                $opts['http']['header'][] = $authHeader;
            }

            $notifyUrl = $managedRepository . self::ENDPOINT_URI;

            $context = StreamContextFactory::getContext($notifyUrl, $opts);
            @file_get_contents($notifyUrl, false, $context);
        }
    }
}