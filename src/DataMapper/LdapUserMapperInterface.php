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

interface LdapUserMapperInterface
{

    /**
     * @param User $user
     * @param $username
     * @param $password
     * @param Entry $userData
     */
    public static function mapDataToUser(User $user, $username, $password, Entry $userData);
}
