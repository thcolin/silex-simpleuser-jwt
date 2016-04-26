<?php

  namespace SimpleUser\JWT;

  use SimpleUser\Mailer as ParentMailer;

  class Mailer extends ParentMailer{

    public function send($template, $route, User $user, $context = []){
      $url = $this -> urlGenerator -> generate($route, $context, true);
      $context['url'] = $url;
      $context['user'] = $user;
      $this -> sendMessage($template, $context, $this -> getFromEmail(), $user -> getEmail());
    }

  }

?>
