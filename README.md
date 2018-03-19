# Ldap bundle for Pimcore
Enables LDAP authentication to the Pimcore's admin UI.
If a user already exists in Pimcore (and is not in the list of the excluded users) it will be automatically updated with the informations comeing from LDAP. If not a new user will be created automatically.

## Installation

1) Install the bundle using composer `composer require alep/ldap-bundle dev-master`.
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

**enabled**: Enables LDAP authentication (default: `false`).
**service**: LDAP client to use (required, default: `Symfony\Component\Ldap\Ldap`).
**base_dn**: Base DN for the directory (required, example: `dc=example,dc=com`).
**search_dn**: Read-only user's DN, which will be used to authenticate against the LDAP server in order to fetch the user's information (example: `cn=your_search_dn_user,ou=users,dc=example,dc=com`).
**search_password**: Read-only user's password, which will be used to authenticate against the LDAP server in order to fetch the user's information (example: `your_search_dn_user_password`).
**uid_key**: Entry's key to use as its UID. Depends on your LDAP server implementation (required, default: `sAMAccountName`).
**filter**: It lets you configure which LDAP query will be used. The {uid_key} string will be replaced by the value of the uid_key configuration value (by default, sAMAccountName), and the {username} string will be replaced by the username you are trying to load (required, default: `({uid_key}={username})`).
**exclude**: List of Pimcore's usernames to exclude from LDAP authentication (example: `['admin']`).
**default_roles**: List of Pimcore's roles you wish to give to a user fetched from the LDAP server. If you do not configure this key, your users won't have any roles, and will not be considered as authenticated fully (example: `['ROLE_USER']`). All the roles needs to be already configured in Pimcore.
**mapper**: Data mapper service used to map ldap user data to Pimcore user (required, default: `Alep\LdapBundle\DataMapper\DefaultLdapUserMapper`).
