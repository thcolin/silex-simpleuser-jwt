Simple User (JWT) Provider for Silex
====================================

An implementation of [jasongrimes/silex-simpleuser](https://github.com/jasongrimes/silex-simpleuser) with [JSON Web Token (JWT)](http://jwt.io/) frequently used in javascript frontend framework (AngularJS, ...)

Methods
-------
- register
- login
- invite
- friends (list of your invits)
- forget (password)
- reset (password)
- update

Config
------
Check ```tests/UserControllerTests.php``` for full exemple (db, jwt, mailer, twig...)
```php
use Silex\Application;
use SimpleUser\JWT\UserProvider;

$app = new Application();

// Useful to catch error and send them directly in JSON
$app -> error(function(Exception $e, $code) use($app){
  return $app -> json(['error' => $e -> getMessage(), 'type' => get_class($e)], $code);
});

// Default options
$app['user.jwt.options'] = [
  'language' => 'SimpleUser\JWT\Languages\English', // This class contains messages constants, you can create your own with the same structure
  'controller' => 'SimpleUser\JWT\UserController', // User controller, you can rewrite it
  'class' => 'SimpleUser\JWT\User', // If you want your own class, extends 'SimpleUser\JWT\User'
  'registrations' => [
    'enabled' => true,
    'confirm' => false // Send a mail to the user before enable it
  ],
  'invite' => [
    'enabled' => false // Allow user to send invitations
  ],
  'forget' => [
    'enabled' => false // Enable the 'forget password' function
  ],
  'tables' => [ // SQL tables
    'users' => 'users',
    'customfields' => 'user_custom_fields'
  ],
  'mailer' => [
    'enabled' => false,
    'from' => [
      'email' => 'do-not-reply@'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']:gethostname()),
      'name' => null
    ],
    // Email templates
    'templates' => [
      'register' => [
        'confirm' => 'confirm.twig',
        'welcome' => 'welcome.twig'
      ],
      'invite' => 'invite.twig',
      'forget' => 'forget.twig'
    ],
    // Routes name for email templates generation (optional if you don't want to use url in your email)
    'routes' => [
      'login' => 'user.jwt.login',
      'reset' => 'user.jwt.reset'
    ]
  ]
];

$app -> register(new UserProvider());
```

Routes
------
There is a ```Controller``` in the library :
```php
$app -> mount('/', new UserProvider());
```

* ```POST``` ```/register``` ```{email, password}``` : Register with email and password
* ```POST``` ```/login``` ```{email, password}``` : Return the JWT of the user
* ```POST``` ```/invite``` ```{email}``` : Email of your friend
* ```GET``` ```/friends``` : Return the list of friends
* ```POST``` ```/forget``` ```{email}``` : Email of the user who forget his password
* ```POST``` ```/reset/{token}``` ```{password}``` : Token sent by email, new password
* ```POST``` ```/profil/{id}``` ```{email, password, name, username, customFields}``` : All the postfields are optional

Client side
-----------
When you send request to your application, add HTTP header ```X-Access-Token``` with the token. On server side, in your Controller you can access to the ```$user``` like this :
```php
$user = $app['security'] -> getToken() -> getUser();
```

Dependencies
------------
### Database
Exemple with a SQLite database
```php
use Silex\Provider\DoctrineServiceProvider;

$app -> register(new DoctrineServiceProvider(), [
  'db.options' => [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__.'/app.db',
    'charset' => 'UTF8'
  ]
]);
```

### JWT
```php
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SecurityJWTServiceProvider;

$app['security.jwt'] = [
  'secret_key' => 'YOUR_OWN_SECRET_KEY',
  'life_time' => 2592000,
  'algorithm' => ['HS256'],
  'options' => [
    'header_name' => 'X-Access-Token',
    'username_claim' => 'email' // Needed for silex-simpleuser-jwt
  ]
];

$app -> register(new SecurityServiceProvider());
$app -> register(new SecurityJWTServiceProvider());
```

### Mailer
Needed only if you want to use ```confirm```, ```reset``` or ```invite``` functions
```php
use Silex\Provider\SwiftmailerServiceProvider;

$app -> register(new SwiftmailerServiceProvider(), [
  'swiftmailer.options' => [
    'host' => '127.0.0.1',
    'port' => '25'
  ]
]);
```

### Twig
Needed only if you want to use ```confirm```, ```reset``` or ```invite``` functions (generate email templates)
```php
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\TwigServiceProvider;

$app -> register(new TwigServiceProvider(), [
  'twig.path' => __DIR__.'/views'
]);

$app -> register(new UrlGeneratorServiceProvider());
```

Roles
-----
```php
/**
 * All this Roles are hardcoded in the library
 * ROLE_REGISTERED : Added to registered users
 * ROLE_INVITED : Added to invited users
 * ROLE_ALLOW_INVITE : Allow the user to invite friends
 * ROLE_ADMIN : Allow the user to update others users informations
 */

$app['security.role_hierarchy'] = [
  'ROLE_INVITED' => ['ROLE_USER'],
  'ROLE_REGISTERED' => ['ROLE_INVITED', 'ROLE_ALLOW_INVITE'],
  'ROLE_ADMIN' => ['ROLE_REGISTERED']
];
```

Firewalls
---------
The firewalls are optional but, it's always good to secure your application
```php
$app['security.firewalls'] = [
  'login' => [
    'pattern' => 'register|login|forget|reset',
    'anonymous' => true
  ],
  'secured' => [
    'pattern' => '.*$',
    'users' => $app['user.manager'], // Array with the all the users
    'jwt' => [
			'use_forward' => true,
			'require_previous_session' => false,
			'stateless' => true
    ]
  ]
];
```

Tests
-----
There are unit tests in ```tests/```, you can launch them with ```phpunit```. You need to launch [MailCatcher](https://mailcatcher.me/) before making tests.
