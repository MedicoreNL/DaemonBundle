<?php

namespace DaemonBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Merges each configured daemon with the default configuration and makes sure the pid directory is writable.
 */
class InitPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('daemon.daemons')) {
            return;
        }

        $config = $container->getParameter('daemon.daemons');
        $filesystem = new Filesystem();
        foreach ($config as $name => $cnf) {
            if (null == $cnf) {
                $cnf = array();
            }
            try {
                $pidDir = $cnf['appPidDir'];
                $filesystem->mkdir($pidDir, 0777);
            } catch (\Exception $e) {
                echo 'DaemonBundle exception: ', $e->getMessage(), "\n";
            }

            if (isset($cnf['appUser']) || isset($cnf['appGroup'])) {
                if (isset($cnf['appUser']) && (function_exists('posix_getpwnam'))) {
                    $user = posix_getpwnam($cnf['appUser']);
                    if ($user) {
                        $cnf['appRunAsUID'] = $user['uid'];
                    }
                }

                if (isset($cnf['appGroup']) && (function_exists('posix_getgrnam'))) {
                    $group = posix_getgrnam($cnf['appGroup']);
                    if ($group) {
                        $cnf['appRunAsGID'] = $group['gid'];
                    }
                }

                if (!isset($cnf['appRunAsGID'])) {
                    $user = posix_getpwuid($cnf['appRunAsUID']);
                    $cnf['appRunAsGID'] = $user['gid'];
                }
            }

            $cnf['logLocation'] = rtrim($cnf['logDir'], '/') . '/' . $cnf['appName'] . 'Daemon.log';
            $cnf['appPidLocation'] = rtrim($cnf['appPidDir'], '/') . '/' . $cnf['appName'] . '/' . $cnf['appName'] . '.pid';
            unset($cnf['logDir'], $cnf['appPidDir']);

            $container->setParameter($name . '.daemon.options', $cnf);
        }
    }
}
