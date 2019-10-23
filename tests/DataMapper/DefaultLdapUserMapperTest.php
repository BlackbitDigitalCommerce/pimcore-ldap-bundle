<?php
/**
 * Created by PhpStorm.
 * User: alessandro
 * Date: 2018-12-05
 * Time: 09:45
 */

namespace Alep\LdapBundle\tests\DataMapper;

use Alep\LdapBundle\DataMapper\DefaultLdapUserMapper;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\User;
use Symfony\Component\Ldap\Entry;

class DefaultLdapUserMapperTest extends TestCase
{
    /**
     * @dataProvider providerMapDataToUser
     */
    public function testMapDataToUser($username, $ldapGivenName, $ldapSn, $ldapMail, $expectedUsername, $expectedFirstName, $expectedLastName, $expectedEmail)
    {
        $pimcoreUser = new User();
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

        DefaultLdapUserMapper::mapDataToUser($pimcoreUser, $username, '', $mockLdapUser);

        $this->assertSame($expectedUsername, $pimcoreUser->getName());
        $this->assertSame($expectedFirstName, $pimcoreUser->getFirstname());
        $this->assertSame($expectedLastName, $pimcoreUser->getLastname());
        $this->assertSame($expectedEmail, $pimcoreUser->getEmail());
    }

    public function providerMapDataToUser()
    {
        return array(
            //username, password, ldap given name, ldap sn, ldap mail, expected username, expected password, expected first name, expected last name,  expected email
            array(
                'test_username', 'test_firstname', 'test_surname', 'test_email@email.test',
                'test_username', 'test_firstname', 'test_surname', 'test_email@email.test'
            ),
            array(
                'test_username', ['test_firstname_first', 'test_firstname_second'], ['test_surname_first', 'test_surname_second'], ['test_email_first@email.test', 'test_email_second@email.test'],
                'test_username', 'test_firstname_first, test_firstname_second', 'test_surname_first, test_surname_second', 'test_email_first@email.test, test_email_second@email.test'
            )
        );
    }
}
