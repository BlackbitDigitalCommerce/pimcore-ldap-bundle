<?php
/**
 * Created by PhpStorm.
 * User: alessandro
 * Date: 2018-12-05
 * Time: 17:41
 */

namespace Alep\LdapBundle\tests\EventListener;


use Alep\LdapBundle\EventListener\LoginListener;
use Alep\LdapBundle\Service\Ldap;
use PHPUnit\Framework\TestCase;
use Pimcore\Event\Admin\Login\LoginCredentialsEvent;
use Pimcore\Event\Admin\Login\LoginFailedEvent;
use Pimcore\Model\User;

class LoginListenerTest extends TestCase
{
    /**
     * @var Ldap
     */
    private $ldap;

    /**
     * @var LoginListener
     */
    private $loginListener;

    public function setUp()
    {
        $this->ldap = $this->createMock(Ldap::class);
        $this->loginListener = new LoginListener($this->ldap);
    }

    public function testOnAdminLoginCredentialsWithTokenCredentialsSkipLDAPAuthentication()
    {
        $mockLoginCredentialsEvent = $this->createMock(LoginCredentialsEvent::class);

        $mockLoginCredentialsEvent->expects($this->once())
            ->method('getCredentials')
            ->willReturn(array('token' => 'test_token'));

        $this->ldap->expects($this->never())
            ->method('isUserExcluded');

        $this->ldap->expects($this->never())
            ->method('authenticate');

        $this->loginListener->onAdminLoginCredentials($mockLoginCredentialsEvent);
    }

    public function testOnAdminLoginCredentialsWithCorrectCredentialsUserExcluded()
    {
        $mockLoginCredentialsEvent = $this->createMock(LoginCredentialsEvent::class);

        $mockLoginCredentialsEvent->expects($this->once())
            ->method('getCredentials')
            ->willReturn(array('username' => 'test_username', 'password' => 'test_password'));

        $this->ldap->expects($this->once())
            ->method('isUserExcluded')
            ->willReturn(true);

        $this->ldap->expects($this->never())
            ->method('authenticate');

        $this->loginListener->onAdminLoginCredentials($mockLoginCredentialsEvent);
    }

    public function testOnAdminLoginCredentialsWithCorrectCredentialsUserNotExcluded()
    {
        $mockLoginCredentialsEvent = $this->createMock(LoginCredentialsEvent::class);

        $mockLoginCredentialsEvent->expects($this->once())
            ->method('getCredentials')
            ->willReturn(array('username' => 'test_username', 'password' => 'test_password'));

        $this->ldap->expects($this->once())
            ->method('isUserExcluded')
            ->willReturn(false);

        $this->ldap->expects($this->once())
            ->method('authenticate');

        $this->ldap->expects($this->once())
            ->method('updatePimcoreUser');

        $this->loginListener->onAdminLoginCredentials($mockLoginCredentialsEvent);
    }

    public function testOnAdminLoginFailedWithExcluded()
    {
        $mockLoginFailedEvent = $this->createMock(LoginFailedEvent::class);

        $mockLoginFailedEvent->expects($this->exactly(2))
            ->method('getCredential')
            ->will(
                $this->returnValueMap(
                    array(
                        array('username', 'test_username'),
                        array('password', 'test_password'),
                    )
                )
            );

        $this->ldap->expects($this->once())
            ->method('isUserExcluded')
            ->willReturn(true);

        $this->ldap->expects($this->never())
            ->method('authenticate');

        $this->loginListener->onAdminLoginFailed($mockLoginFailedEvent);
    }

    public function testOnAdminLoginFailedWithUserNotExcluded()
    {
        $mockLoginFailedEvent = $this->createMock(LoginFailedEvent::class);

        $mockLoginFailedEvent->expects($this->exactly(2))
            ->method('getCredential')
            ->will(
                $this->returnValueMap(
                    array(
                        array('username', 'test_username'),
                        array('password', 'test_password'),
                    )
                )
            );

        $this->ldap->expects($this->once())
            ->method('isUserExcluded')
            ->willReturn(false);

        $this->ldap->expects($this->once())
            ->method('authenticate');

        $this->ldap->expects($this->once())
            ->method('updatePimcoreUser')
            ->willReturn(new User());

        $mockLoginFailedEvent->expects($this->once())
            ->method('setUser');

        $this->loginListener->onAdminLoginFailed($mockLoginFailedEvent);
    }

}