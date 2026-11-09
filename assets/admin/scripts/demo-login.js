/*
 * Comptes de démonstration de la connexion du back-office (templates/admin/yack/security/demo_accounts.html.twig) :
 * un clic, ou Entrée et Espace au clavier, remplit l'identifiant et le mot de passe, puis place le focus sur « Connexion ».
 */
document.addEventListener('click', (event) => {
    const account = event.target.closest('[data-yack-demo-login]');

    if (!account?.form) {
        return;
    }

    account.form.elements._username.value = account.dataset.yackDemoLogin;
    account.form.elements._password.value = account.closest('[data-yack-demo-password]').dataset.yackDemoPassword;
    account.form.querySelector('[type="submit"]')?.focus();
});
