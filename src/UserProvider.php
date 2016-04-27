<?php

  namespace SimpleUser\JWT;

  use Silex\Application;
  use Silex\ServiceProviderInterface;
  use Silex\ControllerProviderInterface;
  use SimpleUser\UserServiceProvider;

  use RuntimeException;
  use LogicException;

  class UserProvider implements ServiceProviderInterface, ControllerProviderInterface{

    /**
     * Define the services on the applications (should be registered)
     * @method register
     * @param  Application $app
     * @return void
     */
    public function register(Application $app){
      if(!isset($app['security.jwt.encoder'])) {
        throw new RuntimeException('Missing dependencies: SecurityJWTServiceProvider');
      }

      if(!isset($app['db'])) {
        throw new RuntimeException('Missing dependencies: DoctrineServiceProvider');
      }

      if(!isset($app['security.voters'])) {
        throw new RuntimeException('Missing dependencies: SecurityServiceProvider');
      }

      // clean simple-user-jwt options
      $app['user.jwt.options'] = (isset($app['user.jwt.options']) ? $app['user.jwt.options']:[]);
      $app['user.jwt.options'] = array_replace_recursive([
        'class' => 'SimpleUser\JWT\User',
        'language' => 'SimpleUser\JWT\Languages\English',
        'registrations' => [
          'enabled' => true,
          'confirm' => false
        ],
        'invite' => [
          'enabled' => false
        ],
        'forget' => [
          'enabled' => false
        ],
        'tables' => [
          'users' => 'users',
          'customfields' => 'user_custom_fields'
        ],
        'mailer' => [
          'enabled' => false,
          'from' => [
            'email' => 'do-not-reply@'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST']:gethostname()),
            'name' => null
          ],
          'templates' => [
            'register' => [
              'confirm' => 'confirm.twig',
              'welcome' => 'welcome.twig'
            ],
            'invite' => 'invite.twig',
            'forget' => 'forget.twig'
          ],
          'routes' => [
            'login' => 'user.jwt.login',
            'reset' => 'user.jwt.reset'
          ]
        ]
      ], $app['user.jwt.options']);

      // mailer check
      if($app['user.jwt.options']['mailer']['enabled']){
        if(!isset($app['mailer'])) {
          throw new RuntimeException('Missing dependencies: SwiftMailerServiceProvider');
        }

        if(!isset($app['url_generator'])) {
          throw new RuntimeException('Missing dependencies: UrlGeneratorServiceProvider');
        }

        if(!isset($app['twig'])) {
          throw new RuntimeException('Missing dependencies: TwigServiceProvider');
        }
      } else{
        if($app['user.jwt.options']['invite']['enabled']){
          throw new LogicException('If you want to enable invite, you need to configure the mailer');
        }
        if($app['user.jwt.options']['forget']['enabled']){
          throw new LogicException('If you want to enable forget, you need to configure the mailer');
        }
      }

      // mailer
      $app['user.jwt.mailer'] = $app -> share(function($app) {
          $mailer = new Mailer(new \Swift_Mailer($app['swiftmailer.transport']), $app['url_generator'], $app['twig']);
          $mailer -> setFromAddress($app['user.jwt.options']['mailer']['from']['email']);
          $mailer -> setFromName($app['user.jwt.options']['mailer']['from']['name']);

          return $mailer;
      });

      // generate simple-user options
      $app['user.options'] = [
        'mailer' => [
            'enabled' => false,
        ],
        'userClass' => $app['user.jwt.options']['class'],
        'userTableName' => $app['user.jwt.options']['tables']['users'],
        'userCustomFieldsTableName' => $app['user.jwt.options']['tables']['customfields'],
      ];

      // register simple-user
      $app -> register(new UserServiceProvider(), [
        'user.options' => [
          'userClass' => 'SimpleUser\JWT\User'
        ]
      ]);

      // cnam/security-jwt-service-provider need the users list in $app['users']
      $app['users'] = $app -> share(function() use($app){
        return $app['user.manager'];
      });
    }

    /**
     * Configure the application before it handle a request
     * @method boot
     * @param  Application $app
     * @return void
     */
    public function boot(Application $app){}

    /**
     * Define controllers routes (should be mounted)
     * @method connect
     * @param  Application $app
     * @return ControllerCollection
     */
    public function connect(Application $app){
      $controllers = $app['controllers_factory'];

      $accountController = 'SimpleUser\JWT\UserController';
      $controllers -> post('/register', $accountController.'::register') -> bind('user.jwt.register');
      $controllers -> post('/login', $accountController.'::login') -> bind('user.jwt.login');
      $controllers -> post('/invite', $accountController.'::invite') -> bind('user.jwt.invite');
      $controllers -> get('/friends', $accountController.'::friends') -> bind('user.jwt.friends');
      $controllers -> post('/forget', $accountController.'::forget') -> bind('user.jwt.forget');
      $controllers -> post('/reset/{token}', $accountController.'::reset') -> bind('user.jwt.reset');
      $controllers -> post('/profil/{id}', $accountController.'::update') -> bind('user.jwt.update') -> value('id', null);

      return $controllers;
    }

  }

?>
