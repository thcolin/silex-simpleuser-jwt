<?php

  namespace SimpleUser\JWT\Languages;

  class English {
    /* Messages */
    const REGISTRATIONS_DISABLED = 'Registrations are disabled';
    const REGISTER_SUCCESS = 'Account created';

    const INVITATIONS_DISABLED = 'Invitations are disabled';
    const INVITATIONS_FORBIDDEN = "You're not allowed to send invitations";
    const INVITATIONS_SUCCESS = 'The user should receive an email soon with a confirmation of the invitation';

    const FORGET_DISABLED = 'Forget function are disabled';
    const FORGET_SUCCESS = 'Email to reset the password sent';

    const RESET_DISABLED = 'Reset function are disabled';
    const RESET_SUCCESS = 'Password reseted';

    /* Errors */
    const EMAIL_MISSING = 'Email missing';
    const EMAIL_OR_PASSWORD_MISSING = 'Email or password missing';
    const EMAIL_INVALID = 'Email not valid';
    const EMAIL_ALREADY_REGISTERED = 'Email already registered';
    const EMAIL_UNKNOWN = 'Email not registered';
    const PASSWORD_INVALID = 'Invalid password';
    const ACCOUNT_DISABLED = 'Account disabled';
    const TOKEN_INVALID = 'Token invalid';
    const TOKEN_EXPIRED = 'Token expired';
  }

?>
