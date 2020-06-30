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

use Alep\LdapBundle\Service\Ldap;
use Pimcore\Event\Admin\Login\LoginCredentialsEvent;
use Pimcore\Event\Admin\Login\LoginFailedEvent;
use Psr\Log\LoggerInterface;

class LoginListener
{
    /** @var Ldap */
    private $ldap;

    /** @var LoggerInterface */
    private $logger;

    /**
     * LoginListener constructor.
     * @param Ldap $ldap
     */
    public function __construct(Ldap $ldap, LoggerInterface $logger)
    {
        $this->ldap = $ldap;
        $this->logger = $logger;
    }

    /**
     * @param LoginCredentialsEvent $event
     */
    public function onAdminLoginCredentials(LoginCredentialsEvent $event)
    {
        //Get credentials from the login event
        $credentials = $event->getCredentials();

        //If authentication via token skip the LDAP authentication
        if (isset($credentials['token'])) {
            return;
        }

        $username = $credentials['username'];
        $password = $credentials['password'];

        //Check if this user has to be excluded
        if ($this->ldap->isUserExcluded($username)) {
            return;
        }

        try {
            //Authenticate via ldap
            $ldapUser = $this->ldap->authenticate($username, $password);

            //Update Pimcore user
            $this->ldap->updatePimcoreUser($username, $password, $ldapUser);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            return;
        }
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
        if ($this->ldap->isUserExcluded($username)) {
            return;
        }

        try {
            //authenticate via ldap
            $ldapUser = $this->ldap->authenticate($username, $password);

            //Update Pimcore user
            $pimcoreUser = $this->ldap->updatePimcoreUser($username, $password, $ldapUser);

            //Update session
            $event->setUser($pimcoreUser);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            return;
        }
    }
}
