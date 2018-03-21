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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('alep_ldap');

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultValue(false)
                ->end()
                ->scalarNode('service')
                    ->info('This is the name of your configured LDAP client. You can freely chose the name, but it must be unique in your application and it cannot start with a number or contain white spaces.')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('Symfony\Component\Ldap\Ldap')
                ->end()
                ->scalarNode('base_dn')
                    ->info('This is the base DN for the directory')
                    ->defaultNull()
                ->end()
                ->scalarNode('search_dn')
                    ->info('This is your read-only user\'s DN, which will be used to authenticate against the LDAP server in order to fetch the user\'s information.')
                    ->defaultNull()
                ->end()
                ->scalarNode('search_password')
                    ->info('This is your read-only user\'s password, which will be used to authenticate against the LDAP server in order to fetch the user\'s information.')
                    ->defaultNull()
                ->end()
                ->arrayNode('default_roles')
                    ->info('This is the default roles you wish to give to a user fetched from the LDAP server. If you do not configure this key, your users won\'t have any roles, and will not be considered as authenticated fully.')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode('uid_key')
                    ->info('This is the entry\'s key to use as its UID. Depends on your LDAP server implementation.')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('sAMAccountName')
                ->end()
                ->scalarNode('filter')
                    ->info('This key lets you configure which LDAP query will be used. The {uid_key} string will be replaced by the value of the uid_key configuration value (by default, sAMAccountName), and the {username} string will be replaced by the username you are trying to load.')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->defaultValue('({uid_key}={username})')
                ->end()
                ->arrayNode('exclude')
                    ->info('This is a list of usernames to exclude from LDAP authentication.')
                    ->setDeprecated('The "%node%" option is deprecated. Use "exclude_rules" instead.')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('exclude_rules')
                    ->info('This is a list of usernames/roles to exclude from LDAP authentication (supports regular expressions).')
                    ->children()
                        ->arrayNode('users')->scalarPrototype()->end()->end()
                        ->arrayNode('roles')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->scalarNode('mapper')
                    ->info('This is the data mapper service used to map ldap user data to Pimcore user.')
                    ->cannotBeEmpty()
                    ->defaultValue('Alep\LdapBundle\DataMapper\DefaultLdapUserMapper')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
