# SocialSignIn ZenDesk CRM integration

Allows you to integrate your Zendesk account with SocialSignIn.

The documentation uses 'myserver.example.com' - replace this with a valid hostname for where you are hosting this code. It's recommended you secure it with a valid SSL certificate.

## SocialSignIn App Configuration

Within the SocialSignIn application, head to https://app.socialsignin.net/#/settings/inbox and add a Custom CRM integration.

 * Name - something of your choosing
 * Search Endpoint URL - https://myserver.example.com/search
 * Search Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)
 * Iframe Endpoint URL - https://myserver.example.com/iframe
 * Iframe Endpoint Secret - LongStringlyThingOfYourChoosing (aka SECRET)

( For this integration, the Search and Iframe Endpoint Secrets need to be the same )

### SocialSignIn Secret 

When SocialSignIn make requests on your integration, the requests are signed with a shared secret (SECRET) which you can check against, to ensure a third party isn't trying to access your pipedrive data.

You define this secret when adding the CRM integration within SocialSignIn. It can be a string of any length (although as with all passwords, longer is generally better).


## ZenDesk Setup 

 * You need to know your ZenDesk SubDomain (ZENDESK\_SUBDOMAIN) (e.g. YourCompanyName). This would normally be in the URL you use for ZenDesk (e.g. https://YourCompanyName.zendesk.com)
 * You need to have a ZenDesk User (ZENDESK\_USERNAME) and API Token (ZENDESK\_TOKEN)

The above variables are added to an Apache (or similar) configuration (or hard coded into public/index.php, but this isn't ideal). 

# Installation 

## Apache Configuration Example
 
 * Code is checked out/deployed to /sites/zendesk.crm-integration and it's to respond to the domain name myserver.example.com

```raw
<VirtualHost *:443>
    ServerName myserver.example.com
    SSLEngine on
    SSLCertificateFile /path/to/ssl_certificate.pem
    SSLCertificateChainFile /path/to/ssl_intermediate.crt

    DocumentRoot /sites/zendesk.crm-integration/public

    SetEnv ZENDESK_SUBDOMAIN mycompanyname
    SetEnv ZENDESK_USERNAME  something@mycompanyname.com
    SetEnv ZENDESK_TOKEN     ALongStringOfAlphaNumerics
    SetEnv SECRET            TheSecretYouSpecifiedWithinSocialSignInApp
    
    <Directory "/sites/zendesk.crm-integration/public">
        AllowOverride All
    </Directory>

    CustomLog /var/log/apache2/zendesk-access.log combined
    ErrorLog /var/log/apache2/zendesk-error.log
</VirtualHost>
```

## Software Dependencies

You need to have :

 * A webserver (e.g. Apache 2.4)
 * PHP 7.0+
 * Composer ( https://getcomposer.org/composer.phar )


Within the root of the application, run :

```bash
php composer.phar install
```

## Logging

If /sites/zendesk.crm-integration/logs/app.log is writeable by the web server, then Monolog will write to it.

