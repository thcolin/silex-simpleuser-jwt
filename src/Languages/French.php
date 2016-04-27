<?php

  namespace SimpleUser\JWT\Languages;

  class French {
    /* Messages */
    const REGISTRATIONS_DISABLED = "L'inscription au service est désactivé";
    const REGISTER_SUCCESS = 'Votre compte est créé';

    const INVITATIONS_DISABLED = "Le système d'invitation est désactivé";
    const INVITATIONS_FORBIDDEN = "Vous n'êtes pas autorisé à inviter vos amis";
    const INVITATIONS_SUCCESS = "L'utilisateur devrait recevoir rapidement un email confirmant son invitation";

    const FORGET_DISABLED = "La fonction d'oublie de mot de passe est désactivé";
    const FORGET_SUCCESS = "Un email vous à été envoyé pour la réinitialisation de votre mot de passe";

    const RESET_DISABLED = "La fonction d'oublie de mot de passe est désactivé";
    const RESET_SUCCESS = "Votre mot de passe vient d'être réinitialisé";

    /* Errors */
    const EMAIL_MISSING = 'Email manquant';
    const EMAIL_OR_PASSWORD_MISSING = 'Email ou mot de passe manquant';
    const EMAIL_INVALID = 'Email non valide';
    const EMAIL_ALREADY_REGISTERED = 'Cet email est déjà enregistré';
    const EMAIL_UNKNOWN = 'Email inconnu';
    const PASSWORD_INVALID = 'Mot de passe incorrect';
    const ACCOUNT_DISABLED = 'Votre compte est désactivé';
    const TOKEN_INVALID = 'La clé de réinitialisation est invalide';
    const TOKEN_EXPIRED = 'La clé de réinitialisation à expirée';
  }

?>
