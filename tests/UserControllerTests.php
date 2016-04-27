<?php

  namespace SimpleUser\JWT\Tests;

  use Silex\Application;
  use Silex\WebTestCase;

  use Silex\Provider\SwiftmailerServiceProvider;
  use Silex\Provider\UrlGeneratorServiceProvider;
  use Silex\Provider\TwigServiceProvider;
  use Silex\Provider\SecurityServiceProvider;
  use Silex\Provider\SecurityJWTServiceProvider;
  use Silex\Provider\DoctrineServiceProvider;
  use SimpleUser\JWT\UserProvider;

  use Exception;

  class UserControllerTests extends WebTestCase{

    /**
     * Bootstrap Silex Application for tests
     * @method createApplication
     * @return $app              Silex\Application
     */
    public function createApplication(){
      $app = new Application(['dev' => true]);

      // errors
      error_reporting(E_ALL^E_STRICT);

      $app -> error(function(Exception $e, $code) use($app){
        return $app -> json(['error' => $e -> getMessage(), 'type' => get_class($e)], $code);
      });

      // database
      $app -> register(new SecurityServiceProvider());
      $app -> register(new DoctrineServiceProvider(), [
        'db.options' => [
          'driver' => 'pdo_sqlite',
          'path' => __DIR__.'/tests.db',
          'charset' => 'UTF8'
        ]
      ]);

      // jwt (json-web-token)
      $app['security.jwt'] = [
        'secret_key' => 'omg-so-secret-test-!',
        'life_time' => 2592000,
        'algorithm' => ['HS256'],
        'options' => [
          'header_name' => 'X-Access-Token',
          'username_claim' => 'email'
        ]
      ];

      $app -> register(new SecurityJWTServiceProvider());

      // mailer
      $app -> register(new SwiftmailerServiceProvider(), [
        'swiftmailer.options' => [
          'host' => '127.0.0.1',
          'port' => '1025'
        ]
      ]);

      // twig
      $app -> register(new TwigServiceProvider(), [
        'twig.path' => __DIR__.'/../templates'
      ]);

      $app -> register(new UrlGeneratorServiceProvider());

      // simple-user-jwt
      $app['user.jwt.options'] = [
        'invite' => [
          'enabled' => true
        ],
        'forget' => [
          'enabled' => true
        ],
        'mailer' => [
          'enabled' => true,
          'from' => [
            'email' => 'do-not-reply@test.com',
            'name' => 'Test'
          ]
        ]
      ];

      $app -> register(new UserProvider());

      // roles
      $app['security.role_hierarchy'] = [
        'ROLE_INVITED' => ['ROLE_USER'],
        'ROLE_REGISTERED' => ['ROLE_INVITED', 'ROLE_ALLOW_INVITE'],
        'ROLE_ADMIN' => ['ROLE_REGISTERED']
      ];

      // not necessary but useful
      $app['security.firewalls'] = [
        'login' => [
          'pattern' => 'register|login|forget|reset',
          'anonymous' => true
        ],
        'secured' => [
          'pattern' => '.*$',
          'users' => $app['user.manager'],
          'jwt' => [
    				'use_forward' => true,
    				'require_previous_session' => false,
    				'stateless' => true
          ]
        ]
      ];

      // controller
      $app -> mount('/', new UserProvider());

      return $app;
    }

    /**
     * Success registration test
     * @method testRegisterSuccess
     * @return void
     */
    public function testRegisterSuccess(){
      $this -> clean('register@register.com');
      $data = $this -> register('register@register.com', 'register');

      $this -> assertArrayHasKey('message', $data);
      $this -> assertEquals($data['message'], 'Account created');
    }

    /**
     * Error registration test (email already registered)
     * @method testRegisterUsedError
     * @return void
     */
    public function testRegisterUsedError(){
      $data = $this -> register('register@register.com', 'register');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\UsedException');
    }

    /**
     * Error registration test (params missing or not correct)
     * @method testRegisterParamsError
     * @return void
     */
    public function testRegisterParamsError(){
      $data = $this -> register('register', 'register');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');

      $data = $this -> register('register@register.com', null);

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');
    }

    /**
     * Success login test
     * @method testLoginSuccess
     * @return void
     */
    public function testLoginSuccess(){
      $data = $this -> login('register@register.com', 'register');

      $this -> assertArrayHasKey('token', $data);
    }

    /**
     * Error login test (unknown user)
     * @method testLoginUnknownError
     * @return void
     */
    public function testLoginUnknownError(){
      $data = $this -> login('null@null.com', 'null');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\UnknownException');
    }

    /**
     * Error login test (password mismatch)
     * @method testLoginMismatchError
     * @return void
     */
    public function testLoginMismatchError(){
      $data = $this -> login('register@register.com', 'mismatch');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\MismatchException');
    }

    /**
     * Error login test (disabled user)
     * @method testLoginDisabledError
     * @return void
     */
    public function testLoginDisabledError(){
      $data = $this -> login('disabled@disabled.com', 'disabled');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\DisabledException');
    }

    /**
     * Error login test (params missing or not correct)
     * @method testLoginParamsError
     * @return void
     */
    public function testLoginParamsError(){
      $data = $this -> login('register', 'register');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');

      $data = $this -> login('register@register.com', null);

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');
    }

    /**
     * Success invite test
     * @method testInviteSuccess
     * @return void
     */
    public function testInviteSuccess(){
      $this -> clean('invite@invite.com');
      $data = $this -> invite(['email' => 'register@register.com', 'password' => 'register'], 'invite@invite.com');

      $this -> assertArrayHasKey('message', $data);
      $this -> assertEquals($data['message'], 'The user should receive an email soon with a confirmation of the invitation');
    }

    /**
     * Error invite test (email already registered)
     * @method testInviteUsedError
     * @return void
     */
    public function testInviteUsedError(){
      $data = $this -> invite(['email' => 'register@register.com', 'password' => 'register'], 'invite@invite.com');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\UsedException');
    }

    /**
     * Error invite test (authorization)
     * @method testInviteAuthorizationError
     * @return void
     */
    public function testInviteAuthorizationError(){
      $data = $this -> invite(['email' => 'friend@friend.com', 'password' => 'friend'], 'invite@invite.com');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\AuthorizationException');
    }

    /**
     * Error invite test (params missing or not correct)
     * @method testInviteParamsError
     * @return void
     */
    public function testInviteParamsError(){
      $data = $this -> invite(['email' => 'register@register.com', 'password' => 'register'], 'invite');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');
    }

    /**
     * Success forget test
     * @method testForgetSuccess
     * @return void
     */
    public function testForgetSuccess(){
      $data = $this -> forget('invite@invite.com');

      $this -> assertArrayHasKey('message', $data);
    }

    /**
     * Error forget test (email not registered)
     * @method testForgetUnknownError
     * @return void
     */
    public function testForgetUnknownError(){
      $data = $this -> forget('null@null.com');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\UnknownException');
    }

    /**
     * Error forget test (params missing or not correct)
     * @method testForgetParamsError
     * @return void
     */
    public function testForgetParamsError(){
      $data = $this -> forget('invite');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');
    }

    /**
     * Success reset test
     * @method testResetSuccess
     * @return void
     */
    public function testResetSuccess(){
      $data = $this -> forget('invite@invite.com');
      $data = $this -> reset($data['token'], 'invite');

      $this -> assertArrayHasKey('token', $data);
    }

    /**
     * Error reset test (params missing or not correct)
     * @method testResetParamsError
     * @return void
     */
    public function testResetParamsError(){
      $data = $this -> reset('this-is-not-a-token', 'invite');

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'InvalidArgumentException');
    }

    /**
     * Success friends test
     * @method testFriendsSuccess
     * @return void
     */
    public function testFriendsSuccess(){
      $data = $this -> friends('register@register.com', 'register');

      $this -> assertArrayHasKey('friends', $data);
      $this -> assertCount(1, $data['friends']);

      $data = $this -> friends('invite@invite.com', 'invite');

      $this -> assertArrayHasKey('friends', $data);
      $this -> assertCount(0, $data['friends']);
    }

    /**
     * Success update test
     * @method testUpdateSuccess
     * @return void
     */
    public function testUpdateSuccess(){
      $this -> clean('update@update.com');
      $data = $this -> update('register@register.com', 'register', [
        'email' => 'update@update.com'
      ]);

      $this -> assertArrayHasKey('token', $data);
    }

    /**
     * Error update test (email already registered)
     * @method testUpdateUsedError
     * @return void
     */
    public function testUpdateUsedError(){
      $data = $this -> update('invite@invite.com', 'invite', [
        'email' => 'update@update.com'
      ]);

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\UsedException');
    }

    /**
     * Success update other user test
     * @method testUpdateOtherSuccess
     * @return void
     */
    public function testUpdateOtherSuccess(){
      $data = $this -> update('admin@admin.com', 'admin', [
        'password' => 'disabled'
      ], 1);

      $this -> assertArrayHasKey('token', $data);
    }

    /**
     * Error update other user test (Unauthorized)
     * @method testUpdateOtherError
     * @return void
     */
    public function testUpdateOtherError(){
      $data = $this -> update('invite@invite.com', 'invite', [
        'email' => 'disabled@disabled.com'
      ], 1);

      $this -> assertArrayHasKey('error', $data);
      $this -> assertEquals($data['type'], 'SimpleUser\JWT\Exceptions\AuthorizationException');
    }

    /**
     * Delete a user for the test
     * @method clean
     * @param  string $email Email of the user to delete
     * @return void
     */
    private function clean($email){
      try{
        $User = $this -> app['user.manager'] -> loadUserByUsername($email);
        $this -> app['user.manager'] -> delete($User);
      } catch(Exception $e){}
    }

    /**
     * Register call to the app
     * @method register
     * @param  string   $email    Email for the user
     * @param  string   $password Password for the user
     * @return array              Array of the return call
     */
    private function register($email, $password){
      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/register', [
        'email' => $email,
        'password' => $password
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Login call to the app
     * @method login
     * @param  string   $email    Email of the user
     * @param  string   $password Password of the user
     * @return array              Array of the return call
     */
    private function login($email, $password){
      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/login', [
        'email' => $email,
        'password' => $password
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Invite call to the app
     * @method invite
     * @param  array    $credentials    Credentials of the user to login
     * @param  string   $email          Email of the user to invite
     * @return array                    Array of the return call
     */
    private function invite($credentials, $email){
      $data = $this -> login($credentials['email'], $credentials['password']);

      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/invite', [
        'email' => $email
      ], [], [
        'HTTP_X_ACCESS_TOKEN' => $data['token']
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Return friends invited by the current user
     * @method friends
     * @param  string  $email    Email credential
     * @param  string  $password Password credential
     * @return array             Array of the return call
     */
    private function friends($email, $password){
      $data = $this -> login($email, $password);

      $client = $this -> createClient();
      $crawler = $client -> request('GET', '/friends', [], [], [
        'HTTP_X_ACCESS_TOKEN' => $data['token']
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Forget (Password) call to the app
     * @method forget
     * @param  string   $email    Email of the user
     * @return array              Array of the return call
     */
    private function forget($email){
      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/forget', [
        'email' => $email
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Reset (password) call to the app
     * @method reset
     * @param  string   $token    Reset token
     * @param  string   $password Password to set
     * @return array              Array of the return call
     */
    private function reset($token, $password){
      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/reset/'.$token, [
        'password' => $password
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

    /**
     * Update call to the app
     * @method update
     * @param  string $email    Email credential
     * @param  string $password Password credential
     * @param  array  $profil   New profil data
     * @return array            Array of the return call
     */
    private function update($email, $password, $profil, $id = null){
      $data = $this -> login($email, $password);

      $client = $this -> createClient();
      $crawler = $client -> request('POST', '/profil'.($id ? '/'.$id:null), $profil, [], [
        'HTTP_X_ACCESS_TOKEN' => $data['token']
      ]);

      return json_decode($client -> getResponse() -> getContent(), true);
    }

  }

?>
