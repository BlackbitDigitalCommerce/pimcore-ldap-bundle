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
     * @param Entry $ldapUser
     */
    public static function mapDataToUser(User $user, $username, $password, Entry $ldapUser)
    {
        $user->setUsername($username);

        $user->setPassword(self::encodePassword($username, $password));

        $ldapGivenName = $ldapUser->getAttribute('givenName');
        $firstName = (is_array($ldapGivenName)) ? implode(', ', $ldapGivenName) : (string) $ldapGivenName;
        $user->setFirstname($firstName);

        $ldapSn = $ldapUser->getAttribute('sn');
        $lastName = (is_array($ldapSn)) ? implode(', ', $ldapSn) : (string) $ldapSn;
        $user->setLastname($lastName);

        $ldapMail = $ldapUser->getAttribute('mail');
        $email = (is_array($ldapMail)) ? implode(', ', $ldapMail) : (string) $ldapMail;
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
            throw new BadCredentialsException(sprintf('Unable to create password hash for user: %s', $username));
        }
    }
}
