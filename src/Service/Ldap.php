<?php
/**
 * Created by PhpStorm.
 * User: alessandro
 * Date: 2018-12-05
 * Time: 08:34
 */

namespace Alep\LdapBundle\Service;

use Alep\LdapBundle\DataMapper\LdapUserMapperInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Pimcore\Model\User;
use Psr\Log\LoggerInterface;

class Ldap
{
    /**
     * @var LdapInterface
     */
    private $ldap;

    /**
     * @var string
     */
    private $baseDn;

    /**
     * @var string
     */
    private $searchDn;

    /**
     * @var string
     */
    private $searchPassword;

    /**
     * @var string[]
     */
    private $defaultRoles;

    /**
     * @var string
     */
    private $uidKey;

    /**
     * @var string
     */
    private $filter;

    /**
     * @var string[]
     */
    private $excludeRules;

    /**
     * @var LdapUserMapperInterface
     */
    private $mapper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Ldap constructor.
     * @param LdapInterface $ldap
     * @param $baseDn
     * @param $searchDn
     * @param $searchPassword
     * @param $defaultRoles
     * @param $uidKey
     * @param $filter
     * @param $excludeRules
     * @param LdapUserMapperInterface $mapper
     * @param LoggerInterface $logger
     */
    public function __construct(
        LdapInterface $ldap,
        $baseDn,
        $searchDn,
        $searchPassword,
        $defaultRoles,
        $uidKey,
        $filter,
        $excludeRules,
        LdapUserMapperInterface $mapper,
        LoggerInterface $logger
    ) {
        $this->ldap = $ldap;
        $this->baseDn = $baseDn;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->defaultRoles = (is_array($defaultRoles)) ? $defaultRoles : array();
        $this->uidKey = $uidKey;
        $this->filter = str_replace('{uid_key}', $uidKey, $filter);
        $this->excludeRules = (is_array($excludeRules)) ? $excludeRules : array();
        $this->mapper = $mapper;
        $this->logger = $logger;

        $this->ldap->bind($searchDn, $searchPassword);
    }

    /**
     * @param string $username
     * @return bool
     */
    public function isUserExcluded($username)
    {
        //Check users excluding rules
        if (isset($this->excludeRules['users'])) {
            $user = $this->getPimcoreUserByUsername($username);
            $userFullPath = '';
            if ($user instanceof User) {
                $tmp = $user;
                $pathParts = [];
                while ($tmp->getParentId()) {
                    $folder = $this->getPimcoreUserFolderById($tmp->getParentId());
                    $pathParts[] = $folder->getName();
                    $tmp = $folder;
                }
                $userFullPath = '/' . implode('/', array_reverse($pathParts)) . '/' . $username;
            }

            foreach ($this->excludeRules['users'] as $userExcludeRule) {
                if (@preg_match($userExcludeRule, null) !== false) { //Check as regex (@ sign in front of the regex function is to prevent warnings on the valid regex test)
                    if (preg_match($userExcludeRule, $username)  || ($userFullPath && preg_match($userExcludeRule, $userFullPath))) {
                        $this->logger->debug(sprintf("User '%s' excluded by the exclude by user rule '%s'", $username, $userExcludeRule));
                        return true;
                    }
                } elseif ($username == $userExcludeRule) { //Check as string
                    $this->logger->debug(sprintf("User '%s' excluded by the exclude by user rule '%s'", $username, $userExcludeRule));
                    return true;
                }
            }
        }

        //Check roles excluding rules
        if (isset($this->excludeRules['roles'])) {
            $roles = $this->getUserRoleNames($username);
            if (!empty($roles)) {
                foreach ($this->excludeRules['roles'] as $roleExcludeRule) {
                    if (@preg_match($roleExcludeRule, null) !== false) { //Check as regex (@ sign in front of the regex function is to prevent warnings on the valid regex test)
                        if (preg_grep($roleExcludeRule, $roles)) {
                            $this->logger->debug(sprintf("User '%s' excluded by the exclude by role rule '%s'", $username, $roleExcludeRule));
                            return true;
                        }
                    } elseif (in_array($roleExcludeRule, $roles)) { //Check as string
                        $this->logger->debug(sprintf("User '%s' excluded by the exclude by role rule '%s'", $username, $roleExcludeRule));
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param string $username
     * @return string[]
     */
    protected function getUserRoleNames($username)
    {
        $roles = array();

        //Get user
        $user = $this->getPimcoreUserByUsername($username);
        if ($user instanceof User) {
            //If the user is an admin add the role ROLE_PIMCORE_ADMIN automatically
            if ($user->isAdmin()) {
                $roles[] = 'ROLE_PIMCORE_ADMIN';
            }

            //Get user's roles
            foreach ($user->getRoles() as $roleId) {
                $role = $this->getPimcoreUserRoleById($roleId);
                $roles[] = $role->getName();
            }
        }

        return $roles;
    }

    /**
     * @param string $username
     * @param string $password
     * @return Entry
     */
    public function authenticate($username, $password)
    {
        //Check if credentials are valid
        if (empty($password)) {
            $this->logger->error(sprintf("Login failed for user '%s'. The presented password is not valid.", $username));
            throw new BadCredentialsException('The presented password is not valid.');
        }

        //Get user from ldap
        $ldapUser = $this->getLdapUser($username);

        if (!($ldapUser instanceof Entry)) {
            $this->logger->error(sprintf("Login failed for user '%s'. The presented username is not valid.", $username));
            throw new BadCredentialsException('The presented username is not valid.');
        }

        //Check credentials in ldap
        $this->checkLdapCredentials($ldapUser->getDn(), $password);

        return $ldapUser;
    }

    /**
     * @param string $username
     * @return User\AbstractUser
     */
    protected function getPimcoreUserByUsername(string $username)
    {
        return User::getByName($username);
    }

    /**
     * @param int $id
     * @return User\AbstractUser
     */
    protected function getPimcoreUserFolderById(int $id)
    {
        return User\Folder::getById($id);
    }

    /**
     * @param int $id
     * @return User\AbstractUser
     */
    protected function getPimcoreUserRoleById(int $id)
    {
        return User\Role::getById($id);
    }

    /**
     * @param string $name
     * @return User\AbstractUser
     */
    protected function getPimcoreUserRoleByName(string $name)
    {
        return User\Role::getByName($name);
    }

    /**
     * @param string $username
     * @return mixed|null|Entry
     */
    protected function getLdapUser($username)
    {
        //Search for ldap user
        $filter = str_replace('{username}', $username, $this->filter);

        $this->logger->debug(sprintf("Searching for ldap user '%s' with the base dn '%s' and the filter '%s'.", $username, $this->baseDn, $filter));

        $queryResults = $this->ldap->query(
            $this->baseDn,
            $filter
        )->execute();

        //Check if ldap user exists
        if ($queryResults->count() === 1) {
            return $queryResults[0];
        }

        return null;
    }

    /**
     * @param string $userdn
     * @param string $password
     */
    public function checkLdapCredentials($userdn, $password)
    {
        try {
            $this->ldap->bind($userdn, $password);
        } catch (ConnectionException $e) {
            $this->logger->error(sprintf("Login failed for user '%s'. The presented password is not valid.", $userdn));
            throw new BadCredentialsException('The presented password is not valid.');
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param Entry $ldapUser
     * @return User|User\AbstractUser
     */
    public function updatePimcoreUser($username, $password, $ldapUser)
    {
        try {
            //Get Pimcore user
            $user = $this->getPimcoreUserByUsername($username);

            //If Pimcore user doesn't exists create a new one
            if (!($user instanceof User)) {
                $user = new User();
                $user->setParentId(0);
            }

            //Update user's data
            $this->mapper::mapDataToUser($user, $username, $password, $ldapUser);

            //Add default roles
            $user->setRoles(array_unique(
                array_merge(
                    $user->getRoles(),
                    $this->getDefaultRolesIds()
                )
            ));

            $user->save();

            return $user;
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            $this->logger->error(sprintf('Unable to update Pimcore user %s', $username));
            throw new BadCredentialsException(sprintf('Unable to update Pimcore user %s', $username));
        }
    }

    /**
     * @return array
     */
    protected function getDefaultRolesIds()
    {
        $defaultRolesIds = array();

        foreach ($this->defaultRoles as $default_role) {
            $pimcoreRole = $this->getPimcoreUserRoleByName($default_role);
            if ($pimcoreRole) {
                $defaultRolesIds[] = $pimcoreRole->getId();
            }
        }

        return $defaultRolesIds;
    }
}
