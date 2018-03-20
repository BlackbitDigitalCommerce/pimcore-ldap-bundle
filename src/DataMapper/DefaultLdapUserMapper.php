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

namespace Alep\LdapBundle\DataMapper;

use Pimcore\Model\User;
use Symfony\Component\Ldap\Entry;
use Pimcore\Tool\Authentication;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class DefaultLdapUserMapper implements LdapUserMapperInterface
{

    /**
     * @param User $user
     * @param $username
     * @param $password
     * @param Entry $ldap_user
     */
    public static function mapDataToUser(User $user, $username, $password, Entry $ldap_user)
    {
        $user->setUsername($username);

        $user->setPassword(self::encodePassword($username, $password));

        $ldap_givenName = $ldap_user->getAttribute('givenName');
        $firstname = (is_array($ldap_givenName)) ? implode(', ', $ldap_givenName) : (string) $ldap_givenName;
        $user->setFirstname($firstname);

        $ldap_sn = $ldap_user->getAttribute('sn');
        $lastname = (is_array($ldap_sn)) ? implode(', ', $ldap_sn) : (string) $ldap_sn;
        $user->setLastname($lastname);

        $ldap_mail = $ldap_user->getAttribute('mail');
        $email = (is_array($ldap_mail)) ? implode(', ', $ldap_mail) : (string) $ldap_mail;
        $user->setEmail($email);

        $user->setActive(true);
    }

    /**
     * @param $username
     * @param $password
     * @return string
     */
    private static function encodePassword($username, $password)
    {
        try {
            return Authentication::getPasswordHash($username, $password);
        } catch (\Exception $exception) {
            throw new BadCredentialsException('Unable to create password hash for user: ' . $username);
        }
    }
}
