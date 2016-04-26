<?php

  namespace SimpleUser\JWT;

  use Silex\Application;
  use Symfony\Component\HttpFoundation\Request;

  use Exception;
  use InvalidArgumentException;
  use SimpleUser\JWT\Exceptions\AuthorizationException;
  use SimpleUser\JWT\Exceptions\UnknownException;
  use SimpleUser\JWT\Exceptions\DisabledException;
  use SimpleUser\JWT\Exceptions\MismatchException;
  use SimpleUser\JWT\Exceptions\ConfigException;
  use SimpleUser\JWT\Exceptions\ExpiredException;
  use SimpleUser\JWT\Exceptions\UsedException;

  class UserController{

    /**
     * Register a user (email & password only)
     * @method register
     * @param  string         $email    Email of the future user
     * @param  string         $password Password of the future user
     * @return json|Exception
     */
    public function register(Application $app, Request $request){
      $email = $request -> request -> get('email');
      $password = $request -> request -> get('password');

      if(!$app['user.jwt.options']['registrations']['enabled']){
        throw new Exception('Registrations are disabled');
      }

      if(!$email OR !$password){
        throw new InvalidArgumentException('Email, password missing');
      }

      if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Email not valid');
      }

      if($app['user.manager'] -> findOneBy(['email' => $email])){
        throw new UsedException('Email already registered');
      }

      $user = $app['user.manager'] -> createUser($email, $password);
      $user -> addRole('ROLE_REGISTERED');

      $answer = ['message' => 'Account created'];

      if($app['user.jwt.options']['registrations']['confirm']){
        $user -> setEnabled(false);
        $user -> setTimePasswordResetRequested(time());
        $user -> setConfirmationToken($app['user.tokenGenerator'] -> generateToken());
        $app['user.jwt.mailer'] -> send(
          $app['user.jwt.options']['mailer']['templates']['register']['confirm'],
          $app['user.jwt.options']['mailer']['routes']['reset'],
          $user,
          ['token' => $user -> getConfirmationToken()]
        );
      } else{
        $answer['token'] = $app['security.jwt.encoder'] -> encode($user -> serialize());
        if($app['user.jwt.options']['mailer']['templates']['register']['welcome']){
          $app['user.jwt.mailer'] -> send(
            $app['user.jwt.options']['mailer']['templates']['register']['welcome'],
            $app['user.jwt.options']['mailer']['routes']['login'],
            $user
          );
        }
      }

      $app['user.manager'] -> insert($user);

      return $app -> json($answer);
    }

    /**
     * Authenticate a user by his email & password
     * @method login
     * @param  string         $email    Email of the user
     * @param  string         $password Clear password of the user
     * @return json|Exception
     */
    public function login(Application $app, Request $request){
      $email = $request -> request -> get('email');
      $password = $request -> request -> get('password');

      if(!$email OR !$password){
        throw new InvalidArgumentException('Email or password missing');
      }

      if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Email not valid');
      }

      $user = $app['user.manager'] -> findOneBy(['email' => $email]);

      if(!$user){
        throw new UnknownException('Email not registered');
      }

      if(!$app['security.encoder.digest'] -> isPasswordValid($user -> getPassword(), $password, $user -> getSalt())){
        throw new MismatchException('Invalid password');
      }

      if(!$user -> isEnabled()){
        throw new DisabledException('Account disabled');
      }

      return $app -> json(['token' => $app['security.jwt.encoder'] -> encode($user -> serialize())]);
    }

    /**
     * Create a user with a temp password and send him an email
     * @method invite
     * @param  string         $email            Email of the invited user
     * @return json|Exception
     */
    public function invite(Application $app, Request $request){
      $email = $request -> request -> get('email');

      if(!$app['user.jwt.options']['invite']['enabled']){
        throw new Exception('Invitations are disabled');
      }

      if(!$app['security.authorization_checker'] -> isGranted('ROLE_ALLOW_INVITE')){
        throw new AuthorizationException("You're not allowed to send invitations");
      }

      if(!$email){
        throw new InvalidArgumentException('Email missing');
      }

      if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Email not valid');
      }

      if($app['user.manager'] -> findOneBy(['email' => $email])){
        throw new UsedException('Email already registered');
      }

      $user = $app['user.manager'] -> createUser($email, $app['user.tokenGenerator'] -> generateToken());
      $user -> setCustomField('invited_by', $app['security'] -> getToken() -> getUser() -> getId());
      $user -> addRole('ROLE_INVITED');

      $user -> setEnabled(false);
      $user -> setTimePasswordResetRequested(time());
      $user -> setConfirmationToken($app['user.tokenGenerator'] -> generateToken());

      $app['user.manager'] -> insert($user);

      $app['user.jwt.mailer'] -> send(
        $app['user.jwt.options']['mailer']['templates']['invite'],
        $app['user.jwt.options']['mailer']['routes']['reset'],
        $user,
        ['token' => $user -> getConfirmationToken()]
      );

      $answer = ['message' => $email.' should receive an email soon with a confirmation of the invitation'];

      if(isset($app['dev']) && $app['dev']){
        $answer['token'] = $user -> getConfirmationToken();
      }

      return $app -> json($answer);
    }

    /**
     * Return invited users by the logged user
     * @method friends
     * @return json|Exception
     */
    public function friends(Application $app, Request $request){
      $user = $app['security'] -> getToken() -> getUser();

      $friends = $app['user.manager'] -> findby(['customFields' => ['invited_by' => $user -> getId()]]);

      return $app -> json(['friends' => $friends]);
    }

    /**
     * Set a reset password token and send an email to the user
     * @method forget
     * @param  string         $email Email of the user who forget his password
     * @return json|Exception
     */
    public function forget(Application $app, Request $request){
      $email = $request -> request -> get('email');

      if(!$app['user.jwt.options']['forget']['enabled']){
        throw new Exception('Forget function are disabled');
      }

      if(!$email){
        throw new InvalidArgumentException('Email missing');
      }

      if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        throw new InvalidArgumentException('Email not valid');
      }

      $user = $app['user.manager'] -> findOneBy(['email' => $email]);

      if(!$user){
        throw new UnknownException('Email not registered');
      }

      $user -> setTimePasswordResetRequested(time());
      $user -> setConfirmationToken($app['user.tokenGenerator'] -> generateToken());
      $app['user.manager'] -> update($user);

      $app['user.jwt.mailer'] -> send(
        $app['user.jwt.options']['mailer']['templates']['forget'],
        $app['user.jwt.options']['mailer']['routes']['reset'],
        $user,
        ['token' => $user -> getConfirmationToken()]
      );

      $answer = ['message' => 'Email to reset the password sent'];

      if(isset($app['dev']) && $app['dev']){
        $answer['token'] = $user -> getConfirmationToken();
      }

      return $app -> json($answer);
    }

    /**
     * Reset a password for a given reset password token linked to an user
     * @method invite
     * @param  string         $token    Reset password token
     * @param  string         $password New password
     * @return json|Exception
     */
    public function reset(Application $app, Request $request, $token){
      $password = $request -> request -> get('password');

      if(!$app['user.jwt.options']['forget']['enabled']){
        throw new Exception('Reset function are disabled');
      }

      $user = $app['user.manager'] -> findOneBy(['confirmationToken' => $token]);

      if(!$user){
        throw new InvalidArgumentException('Token invalid');
      }

      if($user -> isPasswordResetRequestExpired($app['user.options']['passwordReset']['tokenTTL'])){
        throw new ExpiredException('Token expired');
      }

      $user -> setEnabled(true);
      $user -> setTimePasswordResetRequested(null);
      $user -> setConfirmationToken(null);
      $app['user.manager'] -> setUserPassword($user, $password);
      $app['user.manager'] -> update($user);

      return $app -> json(['message' => 'Password reseted', 'token' => $app['security.jwt.encoder'] -> encode($user -> serialize())]);
    }

    /**
     * Update the profil of the current logged user
     * @method update
     * @param  int            $id (optional)           ID of the user we want to update
     * @param  string         $email (optional)        New email
     * @param  string         $password (optional)     New password
     * @param  string         $name (optional)         New name
     * @param  string         $username (optional)     New username
     * @param  array          $customFields (optional) New custom fields
     * @return json|Exception
     */
    public function update(Application $app, Request $request, $id){
      $email = $request -> request -> get('email');
      $password = $request -> request -> get('password');
      $name = $request -> request -> get('name');
      $username = $request -> request -> get('username');
      $customFields = $request -> request -> get('customFields');

      if(!$id){
        $user = $app['security'] -> getToken() -> getUser();
      } else if($app['security.authorization_checker'] -> isGranted('ROLE_ADMIN')){
        $user = $app['user.manager'] -> findOneBy(['id' => $id]);
      } else{
        throw new AuthorizationException("You're not allowed to update other users account");
      }

      if($email && filter_var($email, FILTER_VALIDATE_EMAIL)){
        if($app['user.manager'] -> findOneBy(['email' => $email])){
          throw new UsedException('Email already registered');
        }
        $user -> setEmail($email);
      }

      if($password){
        $app['user.manager'] -> setUserPassword($user, $password);
      }

      if($username && $app['user.manager'] -> getUsernameRequired()){
        $user -> setUsername($username);
      }

      if($name){
        $user -> setName($name);
      }

      if($customFields){
        $user -> setCustomFields($customFields);
      }

      $app['user.manager'] -> update($user);

      return $app -> json(['token' => $app['security.jwt.encoder'] -> encode($user -> serialize())]);
    }

  }

?>
