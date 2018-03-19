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

namespace Alep\LdapBundle\EventListener;

use Alep\LdapBundle\DataMapper\LdapUserMapperInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Pimcore\Event\Admin\Login\LoginCredentialsEvent;
use Pimcore\Event\Admin\Login\LoginFailedEvent;
use Pimcore\Model\User;

class LoginListener
{
    /**
     * @var Ldap
     */
    private $ldap;

    /**
     * @var string
     */
    private $base_dn;

    /**
     * @var string
     */
    private $search_dn;

    /**
     * @var string
     */
    private $search_password;

    /**
     * @var string[]
     */
    private $default_roles;

    /**
     * @var string
     */
    private $uid_key;

    /**
     * @var string
     */
    private $filter;

    /**
     * @var string[]
     */
    private $exclude;

    /**
     * @var LdapUserMapperInterface
     */
    private $mapper;

    /**
     * LoginListener constructor.
     * @param Ldap $ldap
     * @param string $base_dn
     * @param string $search_dn
     * @param string $search_password
     * @param string[] $default_roles
     * @param string $uid_key
     * @param string $filter
     * @param string[] $exclude
     * @param LdapUserMapperInterface $mapper
     */
    public function __construct(Ldap $ldap, $base_dn, $search_dn, $search_password, $default_roles, $uid_key, $filter, $exclude, LdapUserMapperInterface $mapper)
    {
        $this->ldap = $ldap;
        $this->base_dn = $base_dn;
        $this->search_dn = $search_dn;
        $this->search_password = $search_password;
        $this->default_roles = (is_array($default_roles)) ? $default_roles : array();
        $this->uid_key = $uid_key;
        $this->filter = str_replace('{uid_key}', $uid_key, $filter);
        $this->exclude = (is_array($exclude)) ? $exclude : array();
        $this->mapper = $mapper;

        $this->ldap->bind($search_dn, $search_password);
    }

    /**
     * @param LoginCredentialsEvent $event
     */
    public function onAdminLoginCredentials(LoginCredentialsEvent $event)
    {
        //Get credentials from the login event
        $credentials = $event->getCredentials();
        $username = $credentials['username'];
        $password = $credentials['password'];

        //Check if this user has to be excluded
        if(in_array($username, $this->exclude)) return;

        //Authenticate via ldap
        $ldap_user = $this->authenticate($username, $password);

        //Update Pimcore user
        $this->updatePimcoreUser($username, $password, $ldap_user);
    }

    /**
     * @param LoginFailedEvent $event
     */
    public function onAdminLoginFailed(LoginFailedEvent $event)
    {
        //Get credentials from the login event
        $username = $event->getCredential('username');
        $password = $event->getCredential('password');

        //Check if this user has to be excluded
        if(in_array($username, $this->exclude)) return;

        //authenticate via ldap
        $ldap_user = $this->authenticate($username, $password);

        //Update Pimcore user
        $pimcore_user = $this->updatePimcoreUser($username, $password, $ldap_user);

        //Update session
        $event->setUser($pimcore_user);
    }

    /**
     * @param string $username
     * @param string $password
     * @return Entry
     */
    private function authenticate($username, $password) {
        //Check if credentials are valid
        if(empty($password)) {
            throw new BadCredentialsException('The presented password is invalid.');
        }

        //Get user from ldap
        $ldap_user = $this->getLdapUser($username);

        if(!($ldap_user instanceof Entry)) {
            throw new BadCredentialsException('The presented username is invalid.');
        }

        //Check credentials in ldap
        $this->checkLdapCredentials($ldap_user->getDn(), $password);

        return $ldap_user;
    }

    /**
     * @param string $username
     * @return mixed|null|Entry
     */
    private function getLdapUser($username)
    {
        //Search for ldap user
        $query_results = $this->ldap->query(
            $this->base_dn,
            str_replace('{username}', $username, $this->filter)
        )->execute();

        //Check if ldap user exists
        if ($query_results->count() === 1) {
            return $query_results[0];
        }

        return null;
    }

    /**
     * @param string $userdn
     * @param string $password
     */
    private function checkLdapCredentials($userdn, $password)
    {
        try {
            $this->ldap->bind($userdn, $password);
        } catch (ConnectionException $e) {
            throw new BadCredentialsException('The presented password is invalid.');
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param Entry $ldap_user
     * @return User|User\AbstractUser
     */
    private function updatePimcoreUser($username, $password, $ldap_user)
    {
        try {
            //Get Pimcore user
            $user = User::getByName($username);

            //If Pimcore user doesn't exists create a new one
            if(!($user instanceof User)) {
                $user = new User();
                $user->setParentId(0);
            }

            //Update user's informations
            $this->mapper::mapDataToUser($user, $username, $password, $ldap_user);

            //Add default roles
            $user->setRoles(array_merge(
                $user->getRoles(),
                $this->getDefaultRolesIds()
            ));

            $user->save();

            return $user;
        } catch(\Exception $exception) {
            throw new BadCredentialsException('Unable to update Pimcore user '.$username);
        }
    }

    private function getDefaultRolesIds() {
        $default_roles_ids = array();

        foreach($this->default_roles as $default_role) {
            $pimcore_role = User\Role::getByName($default_role);
            if($pimcore_role) {
                $default_roles_ids[] = $pimcore_role->getId();
            }
        }

        return $default_roles_ids;
    }
}