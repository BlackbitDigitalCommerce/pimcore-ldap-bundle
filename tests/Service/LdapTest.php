<?php
/**
 * Created by PhpStorm.
 * User: alessandro
 * Date: 2018-12-05
 * Time: 12:40
 */

namespace Alep\LdapBundle\tests\Service;

use Alep\LdapBundle\DataMapper\DefaultLdapUserMapper;
use PHPUnit\Framework\TestCase;
use Alep\LdapBundle\Service\Ldap;
use Pimcore\Model\User;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface as SymfonyLdap;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class LdapTest extends TestCase
{
    /**
     * @var SymfonyLdap
     */
    private $symfonyLdap;

    /**
     * @var Ldap
     */
    private $ldap;

    public function setUp()
    {
        $this->symfonyLdap = $this->createMock(SymfonyLdap::class);

        $this->ldap = $this->getMockBuilder(Ldap::class)
            ->setConstructorArgs(array(
                $this->symfonyLdap,
                'dc=example,dc=com',
                '',
                '',
                '',
                'sAMAccountName',
                '({uid_key}={username})',
                array(
                    'users' => ['admin', '/^noldap.*/i'],
                    'roles' => ['ROLE_PIMCORE_ADMIN', '/^ROLE_NOLDAP.*/i']
                ),
                new DefaultLdapUserMapper(),
                $this->createMock(LoggerInterface::class)
            ))
            ->setMethods(array(
                'getLdapUser',
                'getUserRoleNames',
                'getPimcoreUserByUsername',
                'getPimcoreUserFolderById',
                'getPimcoreUserRoleById',
                'getPimcoreUserRoleByName',
                'getDefaultRolesIds',
            ))
            ->getMock();
    }

    /**
     * @dataProvider providerIsUserExcludedByUser
     */
    public function testIsUserExcludedByUsername($username, $expectedResult)
    {
        $result = $this->ldap->isUserExcluded($username);

        $this->assertSame($expectedResult, $result);
    }

    public function providerIsUserExcludedByUser()
    {
        return array(
            array('admin', true),
            array('noldap_test', true),
            array('administrator', false),
            array('test_username', false),
            array('test_noldap', false),
        );
    }

    /**
     * @dataProvider providerIsUserExcludedByRole
     */
    public function testIsUserExcludedByRole($roles, $expectedResult)
    {
        $this->ldap->expects($this->once())
            ->method('getUserRoleNames')
            ->willReturn($roles);

        $result = $this->ldap->isUserExcluded('test_username');

        $this->assertSame($expectedResult, $result);
    }

    public function providerIsUserExcludedByRole()
    {
        return array(
            array(['ROLE_PIMCORE_ADMIN'], true),
            array(['ROLE_NOLDAP_USER'], true),
            array(['ROLE_PIMCORE_ADMINISTRATOR'], false),
            array(['ROLE_USER'], false),
        );
    }

    public function testAuthenticateEmptyPassword()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage("The presented password is not valid.");

        $this->ldap->authenticate('test_username', '');
    }

    public function testAuthenticateLdapUserNotFound()
    {
        $this->ldap->expects($this->once())
            ->method('getLdapUser')
            ->willReturn(null);

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage("The presented username is not valid.");

        $this->ldap->authenticate('test_username', 'test_password');
    }

    public function testAuthenticateLdapUserFoundButPasswordWrong()
    {
        $ldapUser = new Entry('cn=test_username,dc=example,dc=com');

        $this->ldap->expects($this->once())
            ->method('getLdapUser')
            ->willReturn($ldapUser);

        $this->symfonyLdap->expects($this->once())
            ->method('bind')
            ->will($this->throwException(new ConnectionException()));

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage("The presented password is not valid.");

        $this->ldap->authenticate('test_username', 'test_password');
    }

    public function testAuthenticateLdapUserFoundAndPasswordCorrect()
    {
        $ldapUser = new Entry('cn=test_username,dc=example,dc=com');

        $this->ldap->expects($this->once())
            ->method('getLdapUser')
            ->willReturn($ldapUser);

        $this->symfonyLdap->expects($this->once())
            ->method('bind');

        $result = $this->ldap->authenticate('test_username', 'test_password');

        $this->assertSame($ldapUser, $result);
    }

    public function testCheckCredentialsPasswordWrong()
    {
        $this->symfonyLdap->expects($this->once())
            ->method('bind')
            ->will($this->throwException(new ConnectionException()));

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage("The presented password is not valid.");

        $this->ldap->checkLdapCredentials('cn=test_username,dc=example,dc=com', 'test_password');
    }

    public function testCheckCredentialsPasswordCorrect()
    {
        $this->symfonyLdap->expects($this->once())
            ->method('bind');

        $this->ldap->checkLdapCredentials('cn=test_username,dc=example,dc=com', 'test_password');
    }

    /**
     * @dataProvider providerUpdatePimcoreUser
     */
    public function testUpdatePimcoreUser($username, $ldapGivenName, $ldapSn, $ldapMail, $userRoles, $defaultRoles)
    {
        $this->ldap->expects($this->once())
            ->method('getDefaultRolesIds')
            ->willReturn($defaultRoles);

        $mockUser = $this->getMockBuilder(User::class)
            ->setMethods(array('save'))
            ->getMock();
        $mockUser->setRoles($userRoles);

        $mockUser->expects($this->once())
            ->method('save');

        $this->ldap->expects($this->once())
            ->method('getPimcoreUserByUsername')
            ->willReturn($mockUser);

        $mockLdapUser = $this->createMock(Entry::class);
        $mockLdapUser->expects($this->exactly(3))
            ->method('getAttribute')
            ->will(
                $this->returnValueMap(
                    array(
                        array('givenName', $ldapGivenName),
                        array('sn', $ldapSn),
                        array('mail', $ldapMail),
                    )
                )
            );

        $result = $this->ldap->updatePimcoreUser($username, 'test_password', $mockLdapUser);

        $this->assertSame($username, $result->getName());
        $this->assertSame($ldapGivenName, $result->getFirstname());
        $this->assertSame($ldapSn, $result->getLastname());
        $this->assertSame($ldapMail, $result->getEmail());
        $this->assertCount(count(array_merge($userRoles, $defaultRoles)), $result->getRoles());
    }

    public function providerUpdatePimcoreUser()
    {
        return array(
            //username, ldapGivenName, ldapSn, ldapMail, userRoles, defaultRoles
            array('test_username', 'test_firstname', 'test_surname', 'test_email@email.test', array(), array()),
            array('test_username', 'test_firstname', 'test_surname', 'test_email@email.test', array(1), array()),
            array('test_username', 'test_firstname', 'test_surname', 'test_email@email.test', array(), array(1)),
            array('test_username', 'test_firstname', 'test_surname', 'test_email@email.test', array(1), array(2)),
        );
    }
}
