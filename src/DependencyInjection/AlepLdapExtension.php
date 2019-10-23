<?php

/**
 * GMDE S.R.L.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2018 GMDE S.R.L. (https://www.gmde.it)
 * @license    GNU General Public License version 3 (GPLv3)
 * @author     Alessandro Pozzi (a.pozzi@gmde.it)
 */

namespace Alep\LdapBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class AlepLdapExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        if (!$config['enabled']) {
            $container->removeDefinition('Alep\LdapBundle\EventListener\LoginListener');
            $container->removeDefinition('Alep\LdapBundle\Service\Ldap');
        } else {

            //Support for deprecated exclude configuration
            if (isset($config['exclude']) && is_array($config['exclude']) && !empty($config['exclude'])) {
                if (isset($config['exclude_rules']) && is_array($config['exclude_rules'])) {
                    if (isset($config['exclude_rules']['users']) && is_array($config['exclude_rules']['users'])) {
                        $config['exclude_rules']['users'] = array_merge($config['exclude_rules']['users'], $config['exclude']);
                    } else {
                        $config['exclude_rules']['users'] = $config['exclude'];
                    }
                } else {
                    $config['exclude_rules'] = array(
                      'users' => $config['exclude']
                    );
                }
            }

            $loginListenerDefinition = $container->getDefinition('Alep\LdapBundle\Service\Ldap');

            $arguments = array(
                new Reference($config['service']),
                $config['base_dn'],
                $config['search_dn'],
                $config['search_password'],
                $config['default_roles'],
                $config['uid_key'],
                $config['filter'],
                $config['exclude_rules'],
                new Reference($config['mapper'])
            );

            //Add logger service if specified
            if (!empty($config['logger'])) {
                $arguments[] = new Reference($config['logger']);
            }

            $loginListenerDefinition->setArguments($arguments);
        }
    }
}
