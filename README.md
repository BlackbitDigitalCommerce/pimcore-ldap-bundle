[![CircleCI](https://circleci.com/gh/alexpozzi/pimcore-ldap-bundle.svg?style=svg)](https://circleci.com/gh/alexpozzi/pimcore-ldap-bundle)
[![License](https://poser.pugx.org/alep/ldap-bundle/license)](https://packagist.org/packages/alep/ldap-bundle)
[![Total Downloads](https://poser.pugx.org/alep/ldap-bundle/downloads)](https://packagist.org/packages/alep/ldap-bundle)
[![Latest Stable Version](https://poser.pugx.org/alep/ldap-bundle/v/stable)](https://packagist.org/packages/alep/ldap-bundle)
[![Latest Unstable Version](https://poser.pugx.org/alep/ldap-bundle/v/unstable)](https://packagist.org/packages/alep/ldap-bundle)

# LDAP bundle for Pimcore
Enables LDAP authentication to the Pimcore's admin UI.

If a user already exists in Pimcore (and is not in the list of the excluded users) it will be automatically updated with the informations coming from LDAP. If not a new user will be created automatically.


## Requirements

Pimcore >= 5.1.0


## Installation

1) Install the bundle using composer `composer require blackbit_digital_commerce/pimcore-ldap`.
2) Open Pimcore Admin UI, navigate to `Tools` > `Extensions` and activate the bundle.


## Configuration

1) Configure the Symfony LDAP client (see http://symfony.com/doc/current/security/ldap.html#configuring-the-ldap-client).
```yaml
    # config/services.yaml
    services:
        Symfony\Component\Ldap\Ldap:
            arguments: ['@Symfony\Component\Ldap\Adapter\ExtLdap\Adapter']
        Symfony\Component\Ldap\Adapter\ExtLdap\Adapter:
            arguments:
                -   host: my-server
                    port: 389
                    encryption: tls
                    options:
                        protocol_version: 3
                        referrals: false
```
2) Configure the LDAP bundle.
```yaml
    # config/config.yaml
    alep_ldap:
      enabled: true
      base_dn: "dc=example,dc=com"
```


### Supported options

* **enabled**: Enables LDAP authentication (default: `false`).
* **service**: LDAP client to use (required, default: `Symfony\Component\Ldap\Ldap`).
* **base_dn**: Base DN for the directory (required, example: `dc=example,dc=com`).
* **search_dn**: Read-only user's DN, which will be used to authenticate against the LDAP server in order to fetch the user's information (example: `cn=your_search_dn_user,ou=users,dc=example,dc=com`).
* **search_password**: Read-only user's password, which will be used to authenticate against the LDAP server in order to fetch the user's information (example: `your_search_dn_user_password`).
* **uid_key**: Entry's key to use as its UID. Depends on your LDAP server implementation (required, default: `sAMAccountName`).
* **filter**: It lets you configure which LDAP query will be used. The {uid_key} string will be replaced by the value of the uid_key configuration value (by default, sAMAccountName), and the {username} string will be replaced by the username you are trying to load (required, default: `({uid_key}={username})`).
* **exclude**: [DEPRECATED] List of Pimcore's usernames to exclude from LDAP authentication (example: `['admin']`). If already configured the values will be merged to `exclude_rules.users` configuration.
* **exclude_rules**: List of rules which determine if a user has to be excluded from LDAP authentication (it supports regular expressions, see below).
    * **users**: List of usernames or regular expressions matching usernames (or user full paths if the user already exists) to exclude from LDAP authentication (example: `['admin', '/^noldap.*/i']` to exclude the user `admin` and all users with a username starting with `noldap` like `noldap_alep`).
    * **roles**: List of roles or regular expressions matching role names to exclude from LDAP authentication (example: `['ROLE_PIMCORE_ADMIN', '/^ROLE_NOLDAP.*/i']` to exclude the users with `ROLE_PIMCORE_ADMIN` assigned and all users with a role starting with `ROLE_NOLDAP` like `ROLE_NOLDAP_USERS`).
* **default_roles**: List of Pimcore's roles you wish to give to a user fetched from the LDAP server (example: `['ROLE_LDAP_USERS']`). All the configured default roles needs to be already present in Pimcore.
* **mapper**: Data mapper service used to map ldap user data to Pimcore user (required, default: `Alep\LdapBundle\DataMapper\DefaultLdapUserMapper`). See [Custom data mapper](#custom-data-mapper) to build your own data mapper.
* **logger**: Logger service used by the bundle (example: `monolog.logger`).



### Custom data mapper

To build your own custom data mapper you just have to create a class which implements the [LdapUserMapperInterface](https://github.com/alexpozzi/pimcore-ldap-bundle/blob/master/src/DataMapper/LdapUserMapperInterface.php).
You can use [DefaultLdapUserMapper](https://github.com/alexpozzi/pimcore-ldap-bundle/blob/master/src/DataMapper/DefaultLdapUserMapper.php) as an example.
The [DefaultLdapUserMapper](https://github.com/alexpozzi/pimcore-ldap-bundle/blob/master/src/DataMapper/DefaultLdapUserMapper.php) is the default data mapper used by the bundle and it maps the following ldap attributes to the Pimcore user:
* username -> Username
* password -> Password (encoded using Pimcore's internal functions)
* givenName -> Firstname
* sn -> Lastname
* mail -> Email
